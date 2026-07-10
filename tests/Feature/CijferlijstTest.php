<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Inschrijving;
use App\Models\OndertekendDocument;
use App\Models\Resultaat;
use App\Models\Student;
use App\Models\User;
use App\Models\Vak;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischVakSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CijferlijstTest extends TestCase
{
    use RefreshDatabase;

    private User $examencommissie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, SynthetischVakSeeder::class, GebruikerSeeder::class, SynthetischeStudentSeeder::class]);
        $this->examencommissie = User::where('rol', Rol::Examencommissie)->first();
    }

    private function slaagVakken(Inschrijving $insch): void
    {
        $vakken = Vak::where('opleiding_id', $insch->opleiding_id)->where('leerjaar', $insch->leerjaar)
            ->where('actief', true)->with('toetsonderdelen')->get();
        foreach ($vakken as $vak) {
            foreach ($vak->toetsonderdelen as $od) {
                Resultaat::create([
                    'inschrijving_id' => $insch->id, 'student_id' => $insch->student_id,
                    'toetsonderdeel_id' => $od->id, 'poging' => 'tentamen', 'poging_nr' => 1,
                    'cijfer' => 7.5, 'voldoende' => true,
                ]);
            }
        }
    }

    public function test_transcript_toont_vakken_cijfers_en_behaalde_ec(): void
    {
        $student = Student::where('studentnummer', '261001')->first();
        $this->slaagVakken($student->inschrijvingen()->first());

        $this->actingAs($this->examencommissie)->get(route('cijferlijst', ['student' => $student->id]))
            ->assertOk()
            ->assertSee($student->volledigeNaam())
            ->assertSee('ISLTH-ARA-101')
            ->assertSee('Behaald');
    }

    public function test_ondertekende_cijferlijst_pdf_wordt_gegenereerd(): void
    {
        Storage::fake('local');
        $student = Student::where('studentnummer', '261001')->first();
        $this->slaagVakken($student->inschrijvingen()->first());

        $this->actingAs($this->examencommissie)
            ->post(route('cijferlijst.pdf', $student), ['ontvanger' => 'Student'])
            ->assertOk();

        $doc = OndertekendDocument::where('type', 'cijferlijst')->first();
        $this->assertNotNull($doc);
        $this->assertSame($student->id, $doc->student_id);
        $this->assertSame(64, strlen($doc->sha256));
        $this->assertDatabaseHas('audit_logs', ['veld' => 'cijferlijst', 'actie' => 'uitgifte']);
    }

    public function test_cijferlijst_niet_voor_studentenzaken(): void
    {
        $this->actingAs($this->examencommissie)->get(route('cijferlijst'))->assertOk();
        $this->actingAs(User::where('rol', Rol::Directie)->first())->get(route('cijferlijst'))->assertOk();
        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())->get(route('cijferlijst'))->assertForbidden();
        $this->actingAs(User::where('rol', Rol::Docent)->first())->get(route('cijferlijst'))->assertForbidden();
    }
}
