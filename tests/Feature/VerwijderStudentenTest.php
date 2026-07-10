<?php

namespace Tests\Feature;

use App\Models\Inschrijving;
use App\Models\Opleiding;
use App\Models\Periode;
use App\Models\Student;
use App\Models\Taak;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Het commando `sis:studenten-verwijderen` voor het opschonen van synthetische
 * testdata: verwijdert studenten op nummer (los of als reeks), ruimt gekoppelde
 * gegevens op via de database-constraints en logt elke verwijdering.
 */
class VerwijderStudentenTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $nummer): Student
    {
        $student = Student::create([
            'studentnummer' => $nummer,
            'voornaam' => 'Test', 'achternaam' => 'Persoon '.$nummer,
        ]);
        Inschrijving::create([
            'student_id' => $student->id,
            'opleiding_id' => Opleiding::where('code', 'ISLTH')->value('id'),
            'periode_id' => Periode::where('actief', true)->value('id'),
            'leerjaar' => 1, 'status' => 'actief', 'inschrijfdatum' => now(),
        ]);

        return $student;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferentieSeeder::class);
    }

    public function test_dry_run_verwijdert_niets(): void
    {
        $this->student('900001');

        $this->artisan('sis:studenten-verwijderen', ['nummers' => ['900001']])
            ->assertSuccessful();

        $this->assertDatabaseHas('studenten', ['studentnummer' => '900001']);
    }

    public function test_force_verwijdert_de_student_en_inschrijving(): void
    {
        $student = $this->student('900002');

        $this->artisan('sis:studenten-verwijderen', ['nummers' => ['900002'], '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('studenten', ['studentnummer' => '900002']);
        $this->assertDatabaseMissing('inschrijvingen', ['student_id' => $student->id]);
        $this->assertDatabaseHas('audit_logs', ['actie' => 'verwijdering', 'veld' => 'student']);
    }

    public function test_reeks_verwijdert_alle_studenten_in_het_bereik(): void
    {
        foreach (['900010', '900011', '900012'] as $nr) {
            $this->student($nr);
        }

        $this->artisan('sis:studenten-verwijderen', ['nummers' => ['900010-900012'], '--force' => true])
            ->assertSuccessful();

        $this->assertSame(0, Student::whereIn('studentnummer', ['900010', '900011', '900012'])->count());
    }

    public function test_gekoppelde_taak_blijft_bestaan_maar_wordt_losgekoppeld(): void
    {
        $student = $this->student('900020');
        $taak = Taak::create(['titel' => 'Bel student', 'status' => 'open', 'student_id' => $student->id]);

        $this->artisan('sis:studenten-verwijderen', ['nummers' => ['900020'], '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('taken', ['id' => $taak->id]);
        $this->assertNull($taak->fresh()->student_id);
    }

    public function test_onbekend_nummer_wordt_overgeslagen(): void
    {
        $this->artisan('sis:studenten-verwijderen', ['nummers' => ['999999'], '--force' => true])
            ->assertSuccessful();
    }
}
