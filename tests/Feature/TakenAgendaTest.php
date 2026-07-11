<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Enums\TaakStatus;
use App\Models\Afspraak;
use App\Models\Organisatie;
use App\Models\Relatietaak;
use App\Models\User;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\OrganisatieSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module Relatiebeheer & Stagebeheer — Fase E (taken & agenda). Bewaakt de CRUD,
 * het afvinken, de opleidinggebonden scoping en de module-brede planning.
 */
class TakenAgendaTest extends TestCase
{
    use RefreshDatabase;

    private User $relatiebeheerder; // PABO
    private Organisatie $paboOrg;   // R260001
    private Organisatie $mgvOrg;    // R260003

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class, OrganisatieSeeder::class]);

        $this->relatiebeheerder = User::where('email', 'l.haddad@iuasr.nl')->firstOrFail(); // PABO
        $this->paboOrg = Organisatie::where('relatienummer', 'R260001')->firstOrFail();
        $this->mgvOrg = Organisatie::where('relatienummer', 'R260003')->firstOrFail();
    }

    public function test_relatiekaart_toont_taken_en_agenda(): void
    {
        $this->actingAs($this->relatiebeheerder)->get(route('relaties.show', $this->paboOrg))
            ->assertOk()
            ->assertSee('Taken')
            ->assertSee('Agenda')
            ->assertSee('Samenwerkingsovereenkomst verlengen');
    }

    public function test_taak_toevoegen(): void
    {
        $this->actingAs($this->relatiebeheerder)->post(route('relatietaken.store', $this->paboOrg), [
            'titel' => 'Nieuwe contactpersoon opvoeren',
            'prioriteit' => 'normaal',
        ])->assertRedirect(route('relaties.show', $this->paboOrg));

        $this->assertDatabaseHas('relatie_taken', [
            'organisatie_id' => $this->paboOrg->id,
            'titel' => 'Nieuwe contactpersoon opvoeren',
            'aangemaakt_door_id' => $this->relatiebeheerder->id,
        ]);
    }

    public function test_taak_afronden_en_heropenen(): void
    {
        $taak = Relatietaak::where('organisatie_id', $this->paboOrg->id)->firstOrFail();

        $this->actingAs($this->relatiebeheerder)->post(route('relatietaken.afronden', $taak))->assertRedirect();
        $this->assertSame(TaakStatus::Afgerond, $taak->fresh()->status);
        $this->assertNotNull($taak->fresh()->afgerond_op);

        $this->actingAs($this->relatiebeheerder)->post(route('relatietaken.afronden', $taak))->assertRedirect();
        $this->assertSame(TaakStatus::Open, $taak->fresh()->status);
        $this->assertNull($taak->fresh()->afgerond_op);
    }

    public function test_afspraak_plannen(): void
    {
        $this->actingAs($this->relatiebeheerder)->post(route('afspraken.store', $this->paboOrg), [
            'type' => 'schoolbezoek',
            'datum' => '2026-11-03',
            'status' => 'gepland',
        ])->assertRedirect(route('relaties.show', $this->paboOrg));

        $this->assertDatabaseHas('agenda_afspraken', [
            'organisatie_id' => $this->paboOrg->id,
            'type' => 'schoolbezoek',
            'medewerker_id' => $this->relatiebeheerder->id,
        ]);
    }

    public function test_planningpagina_toont_afspraken_en_taken(): void
    {
        $this->actingAs($this->relatiebeheerder)->get(route('agenda'))
            ->assertOk()
            ->assertSee('Aankomende afspraken')
            ->assertSee('Openstaande taken');
    }

    public function test_scoping_geen_taak_bij_vreemde_organisatie(): void
    {
        $this->actingAs($this->relatiebeheerder)->post(route('relatietaken.store', $this->mgvOrg), [
            'titel' => 'Onbevoegd',
            'prioriteit' => 'normaal',
        ])->assertForbidden();

        $this->assertDatabaseMissing('relatie_taken', ['titel' => 'Onbevoegd']);
    }

    public function test_directie_kan_agenda_zien_maar_niet_muteren(): void
    {
        $paboDirectie = User::where('email', 'm.groen@iuasr.nl')->firstOrFail();

        $this->actingAs($paboDirectie)->get(route('agenda'))->assertOk();
        $this->actingAs($paboDirectie)->post(route('afspraken.store', $this->paboOrg), [
            'type' => 'overleg', 'datum' => '2026-11-01', 'status' => 'gepland',
        ])->assertForbidden();
    }
}
