<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\OndertekendDocument;
use App\Models\Student;
use App\Models\User;
use App\Support\Documentondertekening;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OndertekeningTest extends TestCase
{
    use RefreshDatabase;

    private User $sz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, GebruikerSeeder::class, SynthetischeStudentSeeder::class]);
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
        $this->actingAs(User::where('rol', Rol::Beheerder)->first())->get(route('ondertekening'))->assertOk();

        $this->actingAs(User::where('rol', Rol::Docent)->first())->get(route('ondertekening'))->assertForbidden();
        $this->actingAs(User::where('rol', Rol::Financien)->first())->get(route('ondertekening'))->assertForbidden();
    }
}
