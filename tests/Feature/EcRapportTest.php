<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Inschrijving;
use App\Models\Resultaat;
use App\Models\Student;
use App\Models\User;
use App\Models\Vak;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcRapportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, GebruikerSeeder::class, SynthetischeStudentSeeder::class]);
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
                    'cijfer' => 8.0, 'voldoende' => true,
                ]);
            }
        }
    }

    public function test_ec_rapport_toont_behaalde_ec(): void
    {
        $student = Student::where('studentnummer', '261001')->first();
        $this->slaagVakken($student->inschrijvingen()->first());

        $this->actingAs(User::where('rol', Rol::Examencommissie)->first())
            ->get(route('ec-rapport'))
            ->assertOk()
            ->assertSee($student->volledigeNaam())
            ->assertSee('Behaald EC');
    }

    public function test_zoekbalk_filtert_op_studentnummer(): void
    {
        $student = Student::where('studentnummer', '261001')->first();

        $response = $this->actingAs(User::where('rol', Rol::Examencommissie)->first())
            ->get(route('ec-rapport', ['q' => '261001']));

        $response->assertOk()->assertSee('261001');
        // Een andere student mag niet in de gefilterde lijst staan.
        $ander = Student::where('studentnummer', '!=', '261001')->first();
        $response->assertDontSee($ander->volledigeNaam());
    }

    public function test_ec_rapport_alleen_voor_cijferinzage(): void
    {
        $this->actingAs(User::where('rol', Rol::Examencommissie)->first())->get(route('ec-rapport'))->assertOk();
        $this->actingAs(User::where('rol', Rol::Directie)->first())->get(route('ec-rapport'))->assertOk();
        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())->get(route('ec-rapport'))->assertForbidden();
        $this->actingAs(User::where('rol', Rol::Docent)->first())->get(route('ec-rapport'))->assertForbidden();
    }
}
