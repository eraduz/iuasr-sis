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
use Database\Seeders\SynthetischVakSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Directie is opleidinggebonden: een directielid ziet uitsluitend studenten,
 * cijfers en rapporten van de eigen opleiding(en). Een student met een dubbele
 * inschrijving is zichtbaar voor de directie van elke opleiding waarin hij/zij
 * actief is ingeschreven.
 */
class DirectieScopingTest extends TestCase
{
    use RefreshDatabase;

    private function inschrijf(Student $s, string $code): void
    {
        Inschrijving::create([
            'student_id' => $s->id,
            'opleiding_id' => Opleiding::where('code', $code)->value('id'),
            'periode_id' => Periode::where('actief', true)->value('id'),
            'status' => InschrijvingStatus::Actief,
            'inschrijfdatum' => '2026-09-01',
        ]);
    }

    private function directieVoor(string $code): User
    {
        $u = User::create(['naam' => 'Dir '.$code, 'email' => 'dir'.strtolower($code).'@iuasr.test', 'rol' => Rol::Directie]);
        $u->opleidingen()->attach(Opleiding::where('code', $code)->value('id'));

        return $u;
    }

    public function test_directie_ziet_alleen_studenten_van_eigen_opleiding(): void
    {
        $this->seed(ReferentieSeeder::class);
        $theo = Student::create(['studentnummer' => '270001', 'voornaam' => 'T', 'achternaam' => 'Theo']);
        $this->inschrijf($theo, 'ISLTH');
        $pabo = Student::create(['studentnummer' => '270002', 'voornaam' => 'P', 'achternaam' => 'Pabo']);
        $this->inschrijf($pabo, 'PABO');

        $paboDir = $this->directieVoor('PABO');

        // Studentenlijst: alleen de PABO-student.
        $this->actingAs($paboDir)->get(route('studenten.index'))
            ->assertOk()->assertSee('270002')->assertDontSee('270001');

        // Dossier binnen eigen opleiding mag; daarbuiten niet.
        $this->actingAs($paboDir)->get(route('studenten.show', $pabo))->assertOk();
        $this->actingAs($paboDir)->get(route('studenten.show', $theo))->assertForbidden();
    }

    public function test_dubbel_ingeschreven_student_zichtbaar_voor_beide_directies(): void
    {
        $this->seed(ReferentieSeeder::class);
        $student = Student::create(['studentnummer' => '270003', 'voornaam' => 'D', 'achternaam' => 'Dubbel']);
        $this->inschrijf($student, 'ISLTH');
        $this->inschrijf($student, 'PABO');

        $paboDir = $this->directieVoor('PABO');
        $theoDir = $this->directieVoor('ISLTH');

        $this->actingAs($paboDir)->get(route('studenten.show', $student))->assertOk();
        $this->actingAs($theoDir)->get(route('studenten.show', $student))->assertOk();
    }

    public function test_directie_zonder_toewijzing_ziet_geen_studenten(): void
    {
        $this->seed(ReferentieSeeder::class);
        $theo = Student::create(['studentnummer' => '270004', 'voornaam' => 'T', 'achternaam' => 'Theo']);
        $this->inschrijf($theo, 'ISLTH');

        $dir = User::create(['naam' => 'Dir', 'email' => 'dir@iuasr.test', 'rol' => Rol::Directie]);

        $this->actingAs($dir)->get(route('studenten.index'))->assertOk()->assertDontSee('270004');
        $this->actingAs($dir)->get(route('studenten.show', $theo))->assertForbidden();
    }

    public function test_beheerder_wijst_opleiding_toe_aan_directie(): void
    {
        $this->seed(ReferentieSeeder::class);
        $beheerder = User::create(['naam' => 'B', 'email' => 'b@iuasr.test', 'rol' => Rol::Beheerder]);
        $dir = User::create(['naam' => 'D', 'email' => 'd@iuasr.test', 'rol' => Rol::Directie]);
        $paboId = Opleiding::where('code', 'PABO')->value('id');

        $this->actingAs($beheerder)->put(route('gebruikers.opleidingen', $dir), ['opleidingen' => [$paboId]])
            ->assertRedirect();
        $this->assertTrue($dir->fresh()->opleidingIds()->contains($paboId));

        // Opleidingtoewijzing geldt alleen voor de rol Directie.
        $ec = User::create(['naam' => 'E', 'email' => 'e@iuasr.test', 'rol' => Rol::Examencommissie]);
        $this->actingAs($beheerder)->put(route('gebruikers.opleidingen', $ec), ['opleidingen' => [$paboId]])
            ->assertForbidden();
    }
}
