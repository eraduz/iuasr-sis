<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Opleiding;
use App\Models\User;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * In de gebruikerslijst (Beheer → Gebruikers & rollen) staat naast de rol Directie
 * de afkorting (code) van de toegewezen opleiding(en), zodat in één oogopslag
 * zichtbaar is welke directeur welke opleiding beheert.
 */
class GebruikerlijstOpleidingCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_gebruikerslijst_toont_opleidingcode_bij_directie(): void
    {
        $this->seed([ReferentieSeeder::class, GebruikerSeeder::class]);
        $beheer = User::where('rol', Rol::Beheerder)->firstOrFail();

        // Bram de Wit is directeur van ISLTH + PMGV.
        $this->actingAs($beheer)->get(route('gebruikers'))
            ->assertOk()
            ->assertSee('ISLTH')
            ->assertSee('PMGV')
            ->assertSee('PABO')
            ->assertSee('MGV');
    }

    public function test_gebruikerslijst_toont_cursuscode_bij_cursusadministratie(): void
    {
        $this->seed([ReferentieSeeder::class, GebruikerSeeder::class]);
        $beheer = User::where('rol', Rol::Beheerder)->firstOrFail();

        // Hafsa Bakkali dirigeert ARAB-TAAL; Omar Faruk HIFZ + IJAZA.
        $this->actingAs($beheer)->get(route('gebruikers'))
            ->assertOk()
            ->assertSee('ARAB-TAAL')
            ->assertSee('HIFZ')
            ->assertSee('IJAZA');
    }

    public function test_niet_directie_gebruiker_krijgt_geen_opleidingcode(): void
    {
        $this->seed(ReferentieSeeder::class);
        $beheer = User::create(['naam' => 'Beheer', 'email' => 'b@iuasr.test', 'rol' => Rol::Beheerder]);
        // Een niet-directie gebruiker met (per ongeluk) een opleidingkoppeling: die
        // code hoort niet in de lijst te verschijnen.
        $sz = User::create(['naam' => 'SZ', 'email' => 'sz@iuasr.test', 'rol' => Rol::Studentenzaken]);
        $sz->opleidingen()->attach(Opleiding::where('code', 'ISLTH')->value('id'));

        $html = $this->actingAs($beheer)->get(route('gebruikers'))->assertOk()->getContent();
        // De SZ-rij bevat geen ISLTH-pill (alleen directie krijgt de codes).
        $this->assertStringNotContainsString('title="Bachelor Islamitische Theologie">ISLTH', $html);
    }
}
