<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Enums\TaalNiveau;
use App\Models\AuditLog;
use App\Models\Opleiding;
use App\Models\Periode;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InschrijvingTest extends TestCase
{
    use RefreshDatabase;

    private function studentenzaken(): User
    {
        return User::create(['naam' => 'SZ', 'email' => 'sz@iuasr.test', 'rol' => Rol::Studentenzaken]);
    }

    public function test_inschrijven_kent_automatisch_een_studentnummer_toe(): void
    {
        $this->seed(ReferentieSeeder::class);
        $opleiding = Opleiding::where('code', 'ISLTH')->first();
        $periode = Periode::where('actief', true)->first();

        $response = $this->actingAs($this->studentenzaken())->post('/inschrijven', [
            'voornaam' => 'Test',
            'achternaam' => 'Student',
            'opleiding_id' => $opleiding->id,
            'periode_id' => $periode->id,
            'leerjaar' => 1,
            'inschrijfdatum' => '2026-09-01',
        ]);

        $student = Student::where('achternaam', 'Student')->first();
        $this->assertNotNull($student);
        // Formaat: jaarprefix 26 + 4-cijferig volgnummer = 6 tekens (bv. 260001).
        $this->assertMatchesRegularExpression('/^26\d{4}$/', $student->studentnummer);
        $response->assertRedirect(route('studenten.show', $student));

        // Inschrijving en audit-regel zijn aangemaakt.
        $this->assertDatabaseHas('inschrijvingen', ['student_id' => $student->id, 'status' => 'actief']);
        $this->assertDatabaseHas('audit_logs', ['onderwerp_type' => 'Student', 'actie' => 'aanmaak']);
        // BSN is niet vastgelegd in deze fase.
        $this->assertNull($student->getRawOriginal('bsn'));
    }

    public function test_inschrijven_slaat_taalbeheersing_op(): void
    {
        $this->seed(ReferentieSeeder::class);
        $opleiding = Opleiding::where('code', 'ISLTH')->first();
        $periode = Periode::where('actief', true)->first();

        $this->actingAs($this->studentenzaken())->post('/inschrijven', [
            'voornaam' => 'Taal',
            'achternaam' => 'Beheersing',
            'opleiding_id' => $opleiding->id,
            'periode_id' => $periode->id,
            'inschrijfdatum' => '2026-09-01',
            'taal_nederlands' => 'onvoldoende',
            'taal_arabisch' => 'goed',
            'nt2_examen_vereist' => '1',
        ]);

        $student = Student::where('achternaam', 'Beheersing')->first();
        $this->assertSame(TaalNiveau::Onvoldoende, $student->taal_nederlands);
        $this->assertSame(TaalNiveau::Goed, $student->taal_arabisch);
        $this->assertTrue($student->nt2_examen_vereist);
    }

    public function test_bsn_inzage_wordt_gelogd(): void
    {
        $this->seed(ReferentieSeeder::class);
        $student = Student::create(['studentnummer' => '260099', 'voornaam' => 'A', 'achternaam' => 'B']);

        $this->actingAs($this->studentenzaken())
            ->get(route('studenten.bsn', $student))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'onderwerp_type' => 'Student',
            'onderwerp_id' => $student->id,
            'actie' => 'inzage',
            'veld' => 'bsn',
        ]);
    }

    public function test_studentnummer_loopt_op_binnen_de_jaarreeks(): void
    {
        $this->seed(ReferentieSeeder::class);
        $opleiding = Opleiding::where('code', 'ISLTH')->first();
        $periode = Periode::where('actief', true)->first();
        $sz = $this->studentenzaken();

        foreach (['Een', 'Twee'] as $naam) {
            $this->actingAs($sz)->post('/inschrijven', [
                'voornaam' => $naam, 'achternaam' => 'Reeks',
                'opleiding_id' => $opleiding->id, 'periode_id' => $periode->id,
                'inschrijfdatum' => '2026-09-01',
            ]);
        }

        $nummers = Student::where('achternaam', 'Reeks')->orderBy('studentnummer')->pluck('studentnummer');
        $this->assertCount(2, $nummers);
        $this->assertNotEquals($nummers[0], $nummers[1]);
    }
}
