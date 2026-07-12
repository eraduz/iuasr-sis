<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\ExamencommissieNotitie;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamencommissieNotitieTest extends TestCase
{
    use RefreshDatabase;

    private User $ec;
    private User $sz;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferentieSeeder::class);
        $this->ec = User::create(['naam' => 'Commissie', 'email' => 'ec@iuasr.test', 'rol' => Rol::Examencommissie]);
        $this->sz = User::create(['naam' => 'SZ', 'email' => 'sz@iuasr.test', 'rol' => Rol::Studentenzaken]);
        $this->student = Student::create(['studentnummer' => '261200', 'voornaam' => 'Test', 'achternaam' => 'Persoon']);
    }

    public function test_examencommissie_voegt_notitie_toe_met_auteur(): void
    {
        $this->actingAs($this->ec)->post(route('studenten.ec-notities.store', $this->student), [
            'tekst' => 'Scriptie nog niet ingeleverd — navragen bij begeleider.',
        ])->assertRedirect();

        $this->assertDatabaseHas('examencommissie_notities', [
            'student_id' => $this->student->id,
            'gebruiker_id' => $this->ec->id,
            'tekst' => 'Scriptie nog niet ingeleverd — navragen bij begeleider.',
        ]);
    }

    public function test_examencommissie_verwijdert_eigen_notitie(): void
    {
        $notitie = ExamencommissieNotitie::create([
            'student_id' => $this->student->id, 'gebruiker_id' => $this->ec->id, 'tekst' => 'Weg hiermee',
        ]);

        $this->actingAs($this->ec)->delete(route('studenten.ec-notities.destroy', [$this->student, $notitie]))
            ->assertRedirect();

        $this->assertDatabaseMissing('examencommissie_notities', ['id' => $notitie->id]);
    }

    public function test_alleen_examencommissie_en_beheer_beheren_deze_notities(): void
    {
        foreach ([Rol::Studentenzaken, Rol::Directie, Rol::Bestuur] as $i => $rol) {
            $user = User::create(['naam' => 'U'.$i, 'email' => "u{$i}@iuasr.test", 'rol' => $rol]);
            $this->actingAs($user)->post(route('studenten.ec-notities.store', $this->student), ['tekst' => 'x'])
                ->assertForbidden();
        }
        $this->assertDatabaseCount('examencommissie_notities', 0);
    }

    public function test_notities_zijn_alleen_zichtbaar_voor_de_examencommissie(): void
    {
        ExamencommissieNotitie::create([
            'student_id' => $this->student->id, 'gebruiker_id' => $this->ec->id,
            'tekst' => 'Vertrouwelijke commissie-aantekening',
        ]);

        // Examencommissie ziet de kaart en de notitie.
        $this->actingAs($this->ec)->get(route('studenten.show', $this->student))
            ->assertOk()
            ->assertSee('Notities examencommissie')
            ->assertSee('Vertrouwelijke commissie-aantekening');

        // Studentenzaken ziet de EC-notities NIET.
        $this->actingAs($this->sz)->get(route('studenten.show', $this->student))
            ->assertOk()
            ->assertDontSee('Notities examencommissie')
            ->assertDontSee('Vertrouwelijke commissie-aantekening');
    }
}
