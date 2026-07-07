<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Borgt dat de rolscheiding server-side wordt afgedwongen op de routes,
 * onafhankelijk van wat de UI toont. Dit is een niet-onderhandelbaar principe.
 */
class RolscheidingTest extends TestCase
{
    use RefreshDatabase;

    private function gebruiker(Rol $rol): User
    {
        return User::create([
            'naam' => 'Test '.$rol->value,
            'email' => $rol->value.'@iuasr.test',
            'rol' => $rol,
        ]);
    }

    public function test_gast_wordt_naar_login_gestuurd(): void
    {
        $this->get('/studenten')->assertRedirect('/login');
    }

    public function test_studentenzaken_mag_studenten_maar_geen_cijfers(): void
    {
        $u = $this->gebruiker(Rol::Studentenzaken);

        $this->actingAs($u)->get('/studenten')->assertOk();
        // Cijferoverzicht is voorbehouden aan examencommissie/directie.
        $this->actingAs($u)->get('/cijferoverzicht')->assertForbidden();
        // Cijferinvoer is voorbehouden aan de docent.
        $this->actingAs($u)->get('/cijferinvoer')->assertForbidden();
    }

    public function test_docent_komt_niet_bij_studentdossiers(): void
    {
        $u = $this->gebruiker(Rol::Docent);

        $this->actingAs($u)->get('/studenten')->assertForbidden();
        $this->actingAs($u)->get('/mijn-vakken')->assertOk();
    }

    public function test_examencommissie_ziet_studenten_en_cijferoverzicht(): void
    {
        $u = $this->gebruiker(Rol::Examencommissie);

        $this->actingAs($u)->get('/studenten')->assertOk();
        $this->actingAs($u)->get('/cijferoverzicht')->assertOk();
        // Maar mag niet inschrijven (identiteit beheren).
        $this->actingAs($u)->get('/inschrijven')->assertForbidden();
    }

    public function test_beheerder_mag_beheer_maar_niet_cijfers_invoeren(): void
    {
        $u = $this->gebruiker(Rol::Beheerder);

        $this->actingAs($u)->get('/gebruikers')->assertOk();
        $this->actingAs($u)->get('/cijferinvoer')->assertForbidden();
    }
}
