<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Nt2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, GebruikerSeeder::class, SynthetischeStudentSeeder::class]);
        Carbon::setTestNow('2026-07-07');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_nt2_deadline_is_een_jaar_na_inschrijfdatum(): void
    {
        $student = Student::where('studentnummer', '261010')->first(); // inschrijf 2025-09-01
        $this->assertSame('2026-09-01', $student->nt2Deadline()->format('Y-m-d'));
        $this->assertSame('open', $student->nt2Status());
        $this->assertSame(56, $student->nt2DagenResterend());
    }

    public function test_nt2_status_verlopen_behaald_en_niet_vereist(): void
    {
        $verlopen = Student::where('studentnummer', '261002')->first(); // deadline 2026-05-01
        $this->assertSame('verlopen', $verlopen->nt2Status());
        $this->assertLessThan(0, $verlopen->nt2DagenResterend());

        $behaald = Student::where('studentnummer', '261003')->first();
        $this->assertSame('behaald', $behaald->nt2Status());

        $nietVereist = Student::where('studentnummer', '261001')->first();
        $this->assertSame('niet_vereist', $nietVereist->nt2Status());
        $this->assertNull($nietVereist->nt2Deadline());
    }

    public function test_dashboard_studentenzaken_toont_nt2_bewaking(): void
    {
        $sz = User::where('rol', Rol::Studentenzaken)->first();

        $this->actingAs($sz)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('NT2-examen')
            ->assertSee('Mehmet')   // 261002 (verstreken) staat in de lijst
            ->assertDontSee('Bouzidi'); // 261003 (behaald) hoort NIET in de lijst
    }

    public function test_studentenzaken_legt_nt2_resultaat_vast(): void
    {
        $sz = User::where('rol', Rol::Studentenzaken)->first();
        $student = Student::where('studentnummer', '261010')->first();

        $this->actingAs($sz)->put(route('studenten.update', $student), [
            'voornaam' => $student->voornaam,
            'achternaam' => $student->achternaam,
            'nt2_examen_vereist' => '1',
            'nt2_behaald_op' => '2026-06-15',
        ])->assertRedirect();

        $this->assertSame('2026-06-15', $student->fresh()->nt2_behaald_op->format('Y-m-d'));
        $this->assertSame('behaald', $student->fresh()->nt2Status());
    }
}
