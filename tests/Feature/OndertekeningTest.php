<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\OndertekendDocument;
use App\Models\Student;
use App\Models\User;
use App\Support\Documentondertekening;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischVakSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OndertekeningTest extends TestCase
{
    use RefreshDatabase;

    private User $sz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, SynthetischVakSeeder::class, GebruikerSeeder::class, SynthetischeStudentSeeder::class]);
        Storage::fake('local');
        $this->sz = User::where('rol', Rol::Studentenzaken)->first();
    }

    private function genereer(): OndertekendDocument
    {
        $student = Student::where('studentnummer', '261001')->first();
        $this->actingAs($this->sz)->post(route('verklaringen.genereer'), [
            'student' => $student->id, 'type' => 'studentbewijs', 'ontvanger' => 'DUO',
        ])->assertOk();

        return OndertekendDocument::firstOrFail();
    }

    public function test_verklaring_wordt_als_ondertekende_pdf_gegenereerd(): void
    {
        $doc = $this->genereer();

        $this->assertStringStartsWith('IUASR-', $doc->code);
        $this->assertSame('DUO', $doc->ontvanger);
        $this->assertSame(64, strlen($doc->sha256));
        $this->assertSame($this->sz->id, $doc->uitgegeven_door_id);
        Storage::disk('local')->assertExists($doc->pad);
        $this->assertDatabaseHas('audit_logs', ['veld' => 'verklaring']);
    }

    public function test_publieke_verificatie_werkt_zonder_login(): void
    {
        $doc = $this->genereer();

        // Openbaar (geen auth).
        $this->get(route('verificatie', ['code' => $doc->code]))
            ->assertOk()
            ->assertSee('Geldig document')
            ->assertSee('DUO');

        $this->get(route('verificatie', ['code' => 'IUASR-XXXX-YYYY']))
            ->assertOk()
            ->assertSee('Niet gevonden');
    }

    public function test_echtheidskenmerk_detecteert_wijziging(): void
    {
        $doc = $this->genereer();
        $bytes = Documentondertekening::pdfBytes($doc);

        $this->assertNotNull($bytes);
        $this->assertTrue(Documentondertekening::isOngewijzigd($doc, $bytes));
        $this->assertFalse(Documentondertekening::isOngewijzigd($doc, $bytes.'x'));
    }

    public function test_archief_alleen_voor_bevoegde_rollen(): void
    {
        $this->actingAs($this->sz)->get(route('ondertekening'))->assertOk();
        $this->actingAs(User::where('rol', Rol::Directie)->first())->get(route('ondertekening'))->assertOk();
        $this->actingAs(User::where('rol', Rol::Bestuur)->first())->get(route('ondertekening'))->assertOk();
        $this->actingAs(User::where('rol', Rol::Beheerder)->first())->get(route('ondertekening'))->assertOk();

        $this->actingAs(User::where('rol', Rol::Docent)->first())->get(route('ondertekening'))->assertForbidden();
        $this->actingAs(User::where('rol', Rol::Financien)->first())->get(route('ondertekening'))->assertForbidden();
    }

    public function test_iedere_rol_ziet_alleen_eigen_documenten_bestuur_en_beheer_alles(): void
    {
        $doc = $this->genereer(); // eigenaar = $this->sz (Studentenzaken)
        $andereSz = User::create(['naam' => 'Tweede SZ', 'email' => 'sz2@iuasr.nl', 'rol' => Rol::Studentenzaken]);
        $directie = User::where('rol', Rol::Directie)->first();

        // Eigenaar ziet het eigen document.
        $this->actingAs($this->sz)->get(route('ondertekening'))->assertOk()->assertSee($doc->code);

        // Andere Studentenzaken ziet het NIET en mag het niet downloaden.
        $this->actingAs($andereSz)->get(route('ondertekening'))->assertOk()->assertDontSee($doc->code);
        $this->actingAs($andereSz)->get(route('ondertekening.download', $doc))->assertForbidden();
        $this->actingAs($andereSz)->get(route('ondertekening.waarmerk', $doc))->assertForbidden();

        // Directie (opleidingsdirecteur) ziet andermans document evenmin.
        $this->actingAs($directie)->get(route('ondertekening'))->assertOk()->assertDontSee($doc->code);
        $this->actingAs($directie)->get(route('ondertekening.download', $doc))->assertForbidden();

        // Schoolbestuur en Beheerder zien en downloaden alles.
        $this->actingAs(User::where('rol', Rol::Bestuur)->first())->get(route('ondertekening'))->assertOk()->assertSee($doc->code);
        $this->actingAs(User::where('rol', Rol::Bestuur)->first())->get(route('ondertekening.download', $doc))->assertOk();
        $this->actingAs(User::where('rol', Rol::Beheerder)->first())->get(route('ondertekening.download', $doc))->assertOk();
    }

    public function test_eigen_pdf_uploaden_en_waarmerken(): void
    {
        $file = UploadedFile::fake()->create('brief.pdf', 50, 'application/pdf');
        $bytes = (string) file_get_contents($file->getRealPath());

        $response = $this->actingAs($this->sz)->post(route('ondertekening.onderteken'), [
            'titel' => 'Toelatingsbrief', 'ontvanger' => 'Gemeente Rotterdam', 'bestand' => $file,
        ]);

        $doc = OndertekendDocument::where('type', 'upload')->firstOrFail();
        $response->assertRedirect(route('ondertekening.klaar', $doc)); // resultaatscherm
        $this->assertSame('Toelatingsbrief', $doc->titel);
        $this->assertSame('Gemeente Rotterdam', $doc->ontvanger);
        $this->assertSame(hash('sha256', $bytes), $doc->sha256); // hash van het origineel
        Storage::disk('local')->assertExists($doc->pad);
        Storage::disk('local')->assertExists($doc->waarmerk_pad);

        // Resultaatscherm toont beide bestanden.
        $this->actingAs($this->sz)->get(route('ondertekening.klaar', $doc))
            ->assertOk()
            ->assertSee($doc->code)
            ->assertSee('Origineel downloaden')
            ->assertSee('Waarmerk downloaden');

        // Origineel én waarmerk zijn te downloaden.
        $this->actingAs($this->sz)->get(route('ondertekening.download', $doc))->assertOk();
        $this->actingAs($this->sz)->get(route('ondertekening.waarmerk', $doc))->assertOk();

        // Publieke verificatie bevestigt dat het originele bestand ongewijzigd is.
        $this->assertTrue(Documentondertekening::isOngewijzigd($doc, $bytes));
    }

    public function test_upload_module_alleen_voor_bevoegde_rollen(): void
    {
        $this->actingAs(User::where('rol', Rol::Directie)->first())->get(route('ondertekening.uploaden'))->assertOk();
        $this->actingAs(User::where('rol', Rol::Docent)->first())->get(route('ondertekening.uploaden'))->assertForbidden();
        $this->actingAs(User::where('rol', Rol::Examencommissie)->first())->post(route('ondertekening.onderteken'), [])->assertForbidden();
    }

    public function test_niet_pdf_wordt_geweigerd(): void
    {
        $file = UploadedFile::fake()->create('script.exe', 10);

        $this->actingAs($this->sz)->post(route('ondertekening.onderteken'), [
            'titel' => 'x', 'ontvanger' => 'y', 'bestand' => $file,
        ])->assertSessionHasErrors('bestand');
        $this->assertSame(0, OndertekendDocument::where('type', 'upload')->count());
    }
}
