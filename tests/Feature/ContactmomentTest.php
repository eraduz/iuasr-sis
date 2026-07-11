<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Contactpersoon;
use App\Models\Organisatie;
use App\Models\RelatieNotitie;
use App\Models\User;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\OrganisatieSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module Relatiebeheer & Stagebeheer — Fase C (contactmomenten, notities,
 * tijdlijn). Bewaakt de CRUD, de opleidinggebonden scoping en de relatiekaart.
 */
class ContactmomentTest extends TestCase
{
    use RefreshDatabase;

    private User $relatiebeheerder; // PABO
    private Organisatie $paboOrg;   // R260001 (PABO)
    private Organisatie $mgvOrg;    // R260003 (MGV)

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class, OrganisatieSeeder::class]);

        $this->relatiebeheerder = User::where('rol', Rol::Relatiebeheerder)->firstOrFail();
        $this->paboOrg = Organisatie::where('relatienummer', 'R260001')->firstOrFail();
        $this->mgvOrg = Organisatie::where('relatienummer', 'R260003')->firstOrFail();
    }

    public function test_relatiekaart_toont_tijdlijn_en_contactmomenten(): void
    {
        $this->actingAs($this->relatiebeheerder)->get(route('relaties.show', $this->paboOrg))
            ->assertOk()
            ->assertSee('Historie / tijdlijn')
            ->assertSee('Kennismakingsgesprek nieuwe stageperiode');
    }

    public function test_contactmoment_vastleggen(): void
    {
        $this->actingAs($this->relatiebeheerder)->post(route('contactmomenten.store', $this->paboOrg), [
            'datum' => '2026-10-05',
            'onderwerp' => 'Evaluatie eerste stageweek',
            'samenvatting' => 'Positief verlopen.',
        ])->assertRedirect(route('relaties.show', $this->paboOrg));

        $this->assertDatabaseHas('contactmomenten', [
            'organisatie_id' => $this->paboOrg->id,
            'onderwerp' => 'Evaluatie eerste stageweek',
            'medewerker_id' => $this->relatiebeheerder->id,
        ]);
    }

    public function test_scoping_geen_contactmoment_bij_vreemde_organisatie(): void
    {
        $this->actingAs($this->relatiebeheerder)->get(route('contactmomenten.create', $this->mgvOrg))->assertForbidden();
        $this->actingAs($this->relatiebeheerder)->post(route('contactmomenten.store', $this->mgvOrg), [
            'datum' => '2026-10-05',
            'onderwerp' => 'Onbevoegd',
        ])->assertForbidden();
    }

    public function test_contactpersoon_van_andere_organisatie_kan_niet_worden_gekoppeld(): void
    {
        // Een contactpersoon van de MGV-organisatie hoort niet bij de PABO-organisatie.
        $vreemdeCp = Contactpersoon::where('organisatie_id', $this->mgvOrg->id)->firstOrFail();

        $this->actingAs($this->relatiebeheerder)->post(route('contactmomenten.store', $this->paboOrg), [
            'datum' => '2026-10-05',
            'onderwerp' => 'Verkeerde koppeling',
            'contactpersoon_id' => $vreemdeCp->id,
        ])->assertSessionHasErrors('contactpersoon_id');
    }

    public function test_notitie_toevoegen_en_verwijderen(): void
    {
        $this->actingAs($this->relatiebeheerder)->post(route('relaties.notities.store', $this->paboOrg), [
            'categorie' => 'Afspraak',
            'tekst' => 'Volgende week terugbellen over de roosters.',
        ])->assertRedirect(route('relaties.show', $this->paboOrg));

        $notitie = RelatieNotitie::where('organisatie_id', $this->paboOrg->id)
            ->where('tekst', 'Volgende week terugbellen over de roosters.')->firstOrFail();

        $this->actingAs($this->relatiebeheerder)->delete(route('relaties.notities.destroy', $notitie))->assertRedirect();
        $this->assertDatabaseMissing('relatie_notities', ['id' => $notitie->id]);
    }

    public function test_directie_ziet_tijdlijn_maar_kan_niet_registreren(): void
    {
        $paboDirectie = User::where('email', 'm.groen@iuasr.nl')->firstOrFail(); // PABO-directeur

        $this->actingAs($paboDirectie)->get(route('relaties.show', $this->paboOrg))->assertOk();
        $this->actingAs($paboDirectie)->get(route('contactmomenten.create', $this->paboOrg))->assertForbidden();
    }
}
