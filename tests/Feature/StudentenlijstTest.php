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

class StudentenlijstTest extends TestCase
{
    use RefreshDatabase;

    private function maakStudent(string $nummer, InschrijvingStatus $status, ?string $achternaam = null): Student
    {
        $student = Student::create(['studentnummer' => $nummer, 'voornaam' => 'S', 'achternaam' => $achternaam ?? $nummer]);
        Inschrijving::create([
            'student_id' => $student->id,
            'opleiding_id' => Opleiding::where('code', 'ISLTH')->value('id'),
            'periode_id' => Periode::where('actief', true)->value('id'),
            'status' => $status,
            'inschrijfdatum' => '2026-09-01',
        ]);

        return $student;
    }

    public function test_lijst_toont_standaard_alleen_actieve_studenten(): void
    {
        $this->seed(ReferentieSeeder::class);
        $sz = User::create(['naam' => 'SZ', 'email' => 'sz@iuasr.test', 'rol' => Rol::Studentenzaken]);
        $this->maakStudent('260001', InschrijvingStatus::Actief);
        $this->maakStudent('260002', InschrijvingStatus::Uitgeschreven);

        $this->actingAs($sz)->get('/studenten')
            ->assertOk()
            ->assertSee('260001')       // actief: zichtbaar
            ->assertDontSee('260002');  // uitgeschreven: standaard verborgen
    }

    public function test_alle_statussen_toont_ook_uitgeschrevenen(): void
    {
        $this->seed(ReferentieSeeder::class);
        $sz = User::create(['naam' => 'SZ', 'email' => 'sz2@iuasr.test', 'rol' => Rol::Studentenzaken]);
        $this->maakStudent('260001', InschrijvingStatus::Actief);
        $this->maakStudent('260002', InschrijvingStatus::Uitgeschreven);

        $this->actingAs($sz)->get('/studenten?status=alle')
            ->assertOk()
            ->assertSee('260001')
            ->assertSee('260002');
    }

    public function test_filter_op_uitgeschreven(): void
    {
        $this->seed(ReferentieSeeder::class);
        $sz = User::create(['naam' => 'SZ', 'email' => 'sz3@iuasr.test', 'rol' => Rol::Studentenzaken]);
        $this->maakStudent('260001', InschrijvingStatus::Actief);
        $this->maakStudent('260002', InschrijvingStatus::Uitgeschreven);

        $this->actingAs($sz)->get('/studenten?status=uitgeschreven')
            ->assertOk()
            ->assertSee('260002')
            ->assertDontSee('260001');
    }

    public function test_az_index_filtert_op_beginletter_van_de_achternaam(): void
    {
        $this->seed(ReferentieSeeder::class);
        $sz = User::create(['naam' => 'SZ', 'email' => 'sz4@iuasr.test', 'rol' => Rol::Studentenzaken]);
        $this->maakStudent('260010', InschrijvingStatus::Actief, 'Aardappel');
        $this->maakStudent('260020', InschrijvingStatus::Actief, 'Bakker');

        $this->actingAs($sz)->get('/studenten?letter=A')
            ->assertOk()->assertSee('260010')->assertDontSee('260020');

        $this->actingAs($sz)->get('/studenten?letter=B')
            ->assertOk()->assertSee('260020')->assertDontSee('260010');
    }
}
