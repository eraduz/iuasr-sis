<?php

namespace Tests\Feature;

use App\Enums\InschrijvingStatus;
use App\Enums\Rol;
use App\Models\Inschrijving;
use App\Models\Opleiding;
use App\Models\Periode;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlumniRapportTest extends TestCase
{
    use RefreshDatabase;

    private function maakStudent(string $nummer, string $achternaam, InschrijvingStatus $status, array $extra = []): Student
    {
        $student = Student::create(array_merge([
            'studentnummer' => $nummer, 'voornaam' => 'A', 'achternaam' => $achternaam,
        ], $extra));
        Inschrijving::create([
            'student_id' => $student->id,
            'opleiding_id' => Opleiding::where('code', 'ISLTH')->value('id'),
            'periode_id' => Periode::where('actief', true)->value('id'),
            'status' => $status,
            'inschrijfdatum' => '2026-09-01',
        ]);

        return $student;
    }

    private function seedTwee(): void
    {
        $this->seed(ReferentieSeeder::class);
        $this->maakStudent('260001', 'Afgestudeerd', InschrijvingStatus::Afgestudeerd, [
            'email' => 'alumnus@student.iuasr.nl', 'telefoon' => '06 12 34 56 78',
        ]);
        $this->maakStudent('260002', 'Actief', InschrijvingStatus::Actief);
    }

    public function test_studentenzaken_ziet_alumni_met_contactgegevens(): void
    {
        $this->seedTwee();
        $sz = User::create(['naam' => 'SZ', 'email' => 'sz@iuasr.test', 'rol' => Rol::Studentenzaken]);

        $this->actingAs($sz)->get(route('rapporten.alumni'))
            ->assertOk()
            ->assertSee('alumnus@student.iuasr.nl')  // e-mail
            ->assertSee('06 12 34 56 78')            // telefoon
            ->assertSee('260001')                    // afgestudeerde student
            ->assertDontSee('260002');               // actieve student niet in alumni
    }

    public function test_directie_mag_het_alumni_rapport_zien(): void
    {
        $this->seedTwee();
        $directie = User::create(['naam' => 'Dir', 'email' => 'dir@iuasr.test', 'rol' => Rol::Directie]);

        $this->actingAs($directie)->get(route('rapporten.alumni'))->assertOk()->assertSee('260001');
    }

    public function test_examencommissie_mag_het_alumni_rapport_niet_zien(): void
    {
        $this->seedTwee();
        $ec = User::create(['naam' => 'EC', 'email' => 'ec@iuasr.test', 'rol' => Rol::Examencommissie]);

        $this->actingAs($ec)->get(route('rapporten.alumni'))->assertForbidden();
    }
}
