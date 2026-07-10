<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischVakSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentVerwijderenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, SynthetischVakSeeder::class, GebruikerSeeder::class, SynthetischeStudentSeeder::class]);
    }

    public function test_beheerder_verwijdert_student_volledig(): void
    {
        $student = Student::where('studentnummer', '261001')->first();
        $inschId = $student->inschrijvingen()->first()->id;

        $this->actingAs(User::where('rol', Rol::Beheerder)->first())
            ->delete(route('studenten.destroy', $student), ['bevestig_nummer' => '261001'])
            ->assertRedirect(route('studenten.index'));

        $this->assertDatabaseMissing('studenten', ['id' => $student->id]);
        $this->assertDatabaseMissing('inschrijvingen', ['id' => $inschId]); // cascade
        $this->assertDatabaseHas('audit_logs', ['actie' => 'verwijdering', 'veld' => 'student']);
    }

    public function test_verkeerd_studentnummer_verwijdert_niet(): void
    {
        $student = Student::where('studentnummer', '261001')->first();

        $this->actingAs(User::where('rol', Rol::Beheerder)->first())
            ->delete(route('studenten.destroy', $student), ['bevestig_nummer' => '999999'])
            ->assertSessionHas('fout');

        $this->assertDatabaseHas('studenten', ['id' => $student->id]);
    }

    public function test_alleen_beheerder_mag_verwijderen(): void
    {
        $student = Student::where('studentnummer', '261001')->first();

        foreach ([Rol::Studentenzaken, Rol::Examencommissie, Rol::Directie] as $rol) {
            $this->actingAs(User::where('rol', $rol)->first())
                ->delete(route('studenten.destroy', $student), ['bevestig_nummer' => '261001'])
                ->assertForbidden();
        }

        $this->assertDatabaseHas('studenten', ['id' => $student->id]);
    }
}
