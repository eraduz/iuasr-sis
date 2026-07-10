<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Database\Seeders\SynthetischVakSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Globale bestuurspagina en de snelkoppelingen op de modulekiezer: het
 * Schoolbestuur krijgt een instellingsbreed overzicht, de Beheerder bereikt de
 * systeemtaken (back-up, gebruikers, audit-log) rechtstreeks vanaf de hoofdpagina.
 */
class BestuurPaginaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, GebruikerSeeder::class]);
    }

    private function user(Rol $rol): User
    {
        return User::where('rol', $rol)->firstOrFail();
    }

    public function test_bestuur_kan_de_bestuurspagina_openen(): void
    {
        $this->actingAs($this->user(Rol::Bestuur))->get(route('bestuur'))
            ->assertOk()->assertSee('globaal overzicht', false)->assertSee('Studiesucces');
    }

    public function test_beheerder_kan_de_bestuurspagina_openen(): void
    {
        $this->actingAs($this->user(Rol::Beheerder))->get(route('bestuur'))->assertOk();
    }

    public function test_andere_rollen_mogen_niet_bij_de_bestuurspagina(): void
    {
        foreach ([Rol::Studentenzaken, Rol::Financien, Rol::Docent, Rol::Directie, Rol::Cursusadministratie] as $rol) {
            $this->actingAs($this->user($rol))->get(route('bestuur'))->assertForbidden();
        }
    }

    public function test_bestuurspagina_rendert_met_data(): void
    {
        // Met synthetische studenten/vakken mogen de aggregaties niet breken.
        $this->seed([SynthetischVakSeeder::class, SynthetischeStudentSeeder::class]);

        $this->actingAs($this->user(Rol::Bestuur))->get(route('bestuur'))
            ->assertOk()->assertSee('Studenten per opleiding')->assertSee('Cursussen');
    }

    public function test_modulekiezer_toont_systeembeheer_voor_beheerder(): void
    {
        $this->actingAs($this->user(Rol::Beheerder))->get(route('modules.kiezen'))
            ->assertOk()->assertSee('Systeembeheer')->assertSee('Back-up')->assertSee('Audit-log');
    }

    public function test_modulekiezer_toont_bestuurstegel_voor_bestuur(): void
    {
        $this->actingAs($this->user(Rol::Bestuur))->get(route('modules.kiezen'))
            ->assertOk()->assertSee('Globaal overzicht')->assertDontSee('Systeembeheer');
    }

    public function test_modulekiezer_toont_geen_systeembeheer_voor_studentenzaken(): void
    {
        $this->actingAs($this->user(Rol::Studentenzaken))->get(route('modules.kiezen'))
            ->assertOk()->assertDontSee('Systeembeheer');
    }
}
