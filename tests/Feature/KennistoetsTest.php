<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Kennistoets;
use App\Models\Student;
use App\Models\User;
use App\Support\Kennistoetsbewaking;
use Carbon\Carbon;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\KennistoetsSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischVakSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KennistoetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, SynthetischVakSeeder::class, GebruikerSeeder::class,
            SynthetischeStudentSeeder::class, KennistoetsSeeder::class]);
    }

    private function paboStudent(): Student
    {
        return Student::where('studentnummer', '261003')->first(); // PABO jaar 1
    }

    public function test_pabo_student_moet_kennistoetsen_halen(): void
    {
        $status = Kennistoetsbewaking::voor($this->paboStudent());
        $this->assertTrue($status['vereist']);
        $this->assertSame(3, $status['totaal']); // RWT + LKT taal + LKT rekenen
    }

    public function test_niet_pabo_student_hoeft_geen_kennistoetsen(): void
    {
        $islth = Student::where('studentnummer', '261001')->first();
        $this->assertFalse(Kennistoetsbewaking::vereist($islth));
    }

    public function test_deadline_is_twee_jaar_na_inschrijving(): void
    {
        $student = $this->paboStudent();
        $eerste = $student->inschrijvingen->min('inschrijfdatum');
        $verwacht = Carbon::parse($eerste)->addYears(2)->startOfDay();

        $this->assertEquals($verwacht, Kennistoetsbewaking::deadline($student));
    }

    public function test_studentenzaken_registreert_behaalde_toets(): void
    {
        $student = $this->paboStudent();
        $toets = Kennistoets::where('code', 'RWT')->first();

        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->post(route('studenten.kennistoetsen.bijwerken', $student), [
                'kennistoets_id' => $toets->id, 'behaald_op' => '2026-01-15',
            ])->assertRedirect();

        $this->assertDatabaseHas('kennistoets_resultaten', [
            'student_id' => $student->id, 'kennistoets_id' => $toets->id, 'behaald_op' => '2026-01-15',
        ]);
        $this->assertDatabaseHas('audit_logs', ['veld' => 'kennistoets', 'actie' => 'wijziging']);
    }

    public function test_verlopen_termijn_wordt_gesignaleerd(): void
    {
        // Ruim na de deadline (inschrijving 2025 + 2 jaar): alle open toetsen 'verlopen'.
        Carbon::setTestNow(Carbon::parse('2030-01-01'));
        $status = Kennistoetsbewaking::voor($this->paboStudent());
        $this->assertSame('verlopen', $status['status']);
        Carbon::setTestNow();
    }

    public function test_beheerder_beheert_kennistoetsen_via_opzoektabellen(): void
    {
        $pabo = \App\Models\Opleiding::where('code', 'PABO')->first();

        $this->actingAs(User::where('rol', Rol::Beheerder)->first())
            ->get(route('opzoektabellen.tabel', 'kennistoetsen'))->assertOk()->assertSee('RWT');

        $this->actingAs(User::where('rol', Rol::Beheerder)->first())
            ->post(route('opzoektabellen.store', 'kennistoetsen'), [
                'opleiding_id' => $pabo->id, 'code' => 'LKT-EXTRA', 'naam' => 'Extra kennistoets',
                'volgorde' => 9, 'actief' => 1,
            ])->assertRedirect();

        $this->assertDatabaseHas('kennistoetsen', ['code' => 'LKT-EXTRA', 'opleiding_id' => $pabo->id]);
    }

    public function test_rolscheiding_alleen_studentenzaken_registreert(): void
    {
        $student = $this->paboStudent();
        $toets = Kennistoets::where('code', 'RWT')->first();
        $body = ['kennistoets_id' => $toets->id, 'behaald_op' => '2026-01-15'];

        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->post(route('studenten.kennistoetsen.bijwerken', $student), $body)->assertRedirect();
        $this->actingAs(User::where('rol', Rol::Examencommissie)->first())
            ->post(route('studenten.kennistoetsen.bijwerken', $student), $body)->assertForbidden();
    }
}
