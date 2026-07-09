<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotitieTest extends TestCase
{
    use RefreshDatabase;

    private function student(): Student
    {
        return Student::create(['studentnummer' => '260700', 'voornaam' => 'Test', 'achternaam' => 'Notitie']);
    }

    public function test_studentenzaken_kan_een_notitie_met_datum_toevoegen(): void
    {
        $sz = User::create(['naam' => 'F. Yildiz', 'email' => 'sz@iuasr.test', 'rol' => Rol::Studentenzaken]);
        $student = $this->student();

        $this->actingAs($sz)->post(route('studenten.notities.store', $student), [
            'tekst' => 'Telefonisch contact gehad over ontbrekend NT2-certificaat.',
        ])->assertRedirect();

        $this->assertDatabaseHas('student_notities', [
            'student_id' => $student->id,
            'gebruiker_id' => $sz->id,
            'tekst' => 'Telefonisch contact gehad over ontbrekend NT2-certificaat.',
        ]);

        // Notitie heeft een datum (created_at) en verschijnt op de studentpagina.
        $notitie = $student->notities()->first();
        $this->assertNotNull($notitie->created_at);
        $this->actingAs($sz)->get(route('studenten.show', $student))
            ->assertOk()
            ->assertSee('ontbrekend NT2-certificaat');
    }

    public function test_lege_notitie_wordt_geweigerd(): void
    {
        $sz = User::create(['naam' => 'SZ', 'email' => 'sz2@iuasr.test', 'rol' => Rol::Studentenzaken]);
        $student = $this->student();

        $this->actingAs($sz)->post(route('studenten.notities.store', $student), ['tekst' => ''])
            ->assertSessionHasErrors('tekst');
    }

    public function test_examencommissie_kan_geen_notitie_toevoegen(): void
    {
        $ec = User::create(['naam' => 'EC', 'email' => 'ec@iuasr.test', 'rol' => Rol::Examencommissie]);
        $student = $this->student();

        $this->actingAs($ec)->post(route('studenten.notities.store', $student), ['tekst' => 'x'])
            ->assertForbidden();
    }

    public function test_directie_en_bestuur_lezen_notities_maar_beheren_niet(): void
    {
        $sz = User::create(['naam' => 'SZ', 'email' => 'sz3@iuasr.test', 'rol' => Rol::Studentenzaken]);
        $student = $this->student();

        // Directie is opleidinggebonden: de student krijgt een actieve inschrijving
        // en het directielid wordt aan die opleiding toegewezen.
        $faculteit = \App\Models\Faculteit::create(['code' => 'FTST', 'naam' => 'Testfaculteit']);
        $opleiding = \App\Models\Opleiding::create(['faculteit_id' => $faculteit->id, 'code' => 'TST', 'naam' => 'Testopleiding', 'soort' => 'bachelor']);
        $periode = \App\Models\Periode::create(['code' => '2099-2100', 'naam' => 'Studiejaar 2099 / 2100', 'actief' => true]);
        \App\Models\Inschrijving::create([
            'student_id' => $student->id, 'opleiding_id' => $opleiding->id, 'periode_id' => $periode->id,
            'status' => \App\Enums\InschrijvingStatus::Actief, 'inschrijfdatum' => '2099-09-01',
        ]);

        $this->actingAs($sz)->post(route('studenten.notities.store', $student), [
            'tekst' => 'Interne notitie voor toezicht.',
        ]);

        $directie = User::create(['naam' => 'Dir', 'email' => 'dir@iuasr.test', 'rol' => Rol::Directie]);
        $directie->opleidingen()->attach($opleiding->id);
        $bestuur = User::create(['naam' => 'Bestuur', 'email' => 'bestuur@iuasr.test', 'rol' => Rol::Bestuur]);

        foreach ([$directie, $bestuur] as $user) {
            // Mogen lezen (dossier + notitie).
            $this->actingAs($user)->get(route('studenten.show', $student))
                ->assertOk()->assertSee('Interne notitie voor toezicht');

            // Mogen niet beheren (toevoegen).
            $this->actingAs($user)->post(route('studenten.notities.store', $student), ['tekst' => 'x'])
                ->assertForbidden();
        }
    }

    public function test_notitie_kan_verwijderd_worden(): void
    {
        $sz = User::create(['naam' => 'SZ', 'email' => 'sz3@iuasr.test', 'rol' => Rol::Studentenzaken]);
        $student = $this->student();
        $notitie = $student->notities()->create(['gebruiker_id' => $sz->id, 'tekst' => 'Te verwijderen']);

        $this->actingAs($sz)->delete(route('studenten.notities.destroy', [$student, $notitie]))->assertRedirect();
        $this->assertDatabaseMissing('student_notities', ['id' => $notitie->id]);
    }
}
