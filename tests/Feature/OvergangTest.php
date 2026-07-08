<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Inschrijving;
use App\Models\Resultaat;
use App\Models\User;
use App\Models\Vak;
use App\Support\Overgangsbeoordeling;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OvergangTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, GebruikerSeeder::class, SynthetischeStudentSeeder::class]);
    }

    private function inschrijvingVan(string $studentnummer): Inschrijving
    {
        return Inschrijving::whereHas('student', fn ($q) => $q->where('studentnummer', $studentnummer))->firstOrFail();
    }

    private function slaagVak(Inschrijving $insch, Vak $vak, float $cijfer = 7.0): void
    {
        foreach ($vak->toetsonderdelen as $od) {
            Resultaat::create([
                'inschrijving_id' => $insch->id, 'student_id' => $insch->student_id,
                'toetsonderdeel_id' => $od->id, 'poging' => 'tentamen', 'poging_nr' => 1,
                'cijfer' => $cijfer, 'voldoende' => true,
            ]);
        }
    }

    private function leerjaarVakken(Inschrijving $insch)
    {
        return Vak::where('opleiding_id', $insch->opleiding_id)->where('leerjaar', $insch->leerjaar)
            ->where('actief', true)->with('toetsonderdelen')->get();
    }

    public function test_positief_advies_bij_behaalde_ec_boven_de_drempel(): void
    {
        $insch = $this->inschrijvingVan('261001'); // ISLTH, leerjaar 1, drempel 30 EC
        foreach ($this->leerjaarVakken($insch) as $vak) {
            $this->slaagVak($insch, $vak);
        }

        $advies = Overgangsbeoordeling::voor($insch->fresh());
        $this->assertSame(30, $advies['drempel']);
        $this->assertGreaterThanOrEqual(30, $advies['behaald']);
        $this->assertSame('positief', $advies['status']);
    }

    public function test_negatief_advies_bij_te_weinig_ec(): void
    {
        $insch = $this->inschrijvingVan('261011'); // ISLTH, leerjaar 1
        // Slechts één vak gehaald (6 EC) — ver onder 30.
        $this->slaagVak($insch, $this->leerjaarVakken($insch)->first());

        $advies = Overgangsbeoordeling::voor($insch->fresh());
        $this->assertSame('negatief', $advies['status']);
        $this->assertLessThan(30, $advies['behaald']);
    }

    public function test_rapport_alleen_voor_examencommissie_en_directie(): void
    {
        $this->actingAs(User::where('rol', Rol::Examencommissie)->first())->get(route('overgang'))->assertOk();
        $this->actingAs(User::where('rol', Rol::Directie)->first())->get(route('overgang'))->assertOk();
        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())->get(route('overgang'))->assertForbidden();
        $this->actingAs(User::where('rol', Rol::Docent)->first())->get(route('overgang'))->assertForbidden();
    }
}
