<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Contactpersoon;
use App\Models\Organisatie;
use App\Models\User;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\OrganisatieSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module Relatiebeheer & Stagebeheer — Fase B (contactpersonen & relatiekaart).
 * Bewaakt de CRUD, de opleidinggebonden scoping en de weergave op de relatiekaart.
 */
class ContactpersoonTest extends TestCase
{
    use RefreshDatabase;

    private User $relatiebeheerder; // PABO
    private Organisatie $paboOrg;   // R260001 (PABO)
    private Organisatie $mgvOrg;    // R260003 (MGV)

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class, OrganisatieSeeder::class]);

        $this->relatiebeheerder = User::where('email', 'l.haddad@iuasr.nl')->firstOrFail(); // PABO
        $this->paboOrg = Organisatie::where('relatienummer', 'R260001')->firstOrFail();
        $this->mgvOrg = Organisatie::where('relatienummer', 'R260003')->firstOrFail();
    }

    public function test_relatiekaart_toont_contactpersonen(): void
    {
        $this->actingAs($this->relatiebeheerder)->get(route('relaties.show', $this->paboOrg))
            ->assertOk()
            ->assertSee('Contactpersonen')
            ->assertSee('Miriam Bakker');
    }

    public function test_contactpersoon_aanmaken(): void
    {
        $this->actingAs($this->relatiebeheerder)->post(route('contactpersonen.store', $this->paboOrg), [
            'voornaam' => 'Sofie',
            'achternaam' => 'Jansen',
            'functie' => 'Intern begeleider',
            'email' => 's.jansen@deregenboog-po.nl',
            'voorkeur_communicatie' => 'e-mail',
            'actief' => '1',
        ])->assertRedirect(route('relaties.show', $this->paboOrg));

        $this->assertDatabaseHas('contactpersonen', [
            'organisatie_id' => $this->paboOrg->id,
            'achternaam' => 'Jansen',
        ]);
    }

    public function test_relatiebeheerder_kan_geen_contactpersoon_bij_vreemde_organisatie_toevoegen(): void
    {
        // De MGV-organisatie valt buiten de opleiding(en) van de PABO-relatiebeheerder.
        $this->actingAs($this->relatiebeheerder)->get(route('contactpersonen.create', $this->mgvOrg))->assertForbidden();

        $this->actingAs($this->relatiebeheerder)->post(route('contactpersonen.store', $this->mgvOrg), [
            'voornaam' => 'Test',
            'achternaam' => 'Onbevoegd',
        ])->assertForbidden();

        $this->assertDatabaseMissing('contactpersonen', ['achternaam' => 'Onbevoegd']);
    }

    public function test_directie_kan_geen_contactpersoon_toevoegen(): void
    {
        $directie = User::where('rol', Rol::Directie)->firstOrFail();
        $this->actingAs($directie)->get(route('contactpersonen.create', $this->paboOrg))->assertForbidden();
    }

    public function test_contactpersoon_bewerken_en_inactiveren(): void
    {
        $cp = Contactpersoon::where('organisatie_id', $this->paboOrg->id)->firstOrFail();

        $this->actingAs($this->relatiebeheerder)->put(route('contactpersonen.update', $cp), [
            'voornaam' => $cp->voornaam,
            'achternaam' => $cp->achternaam,
            'functie' => 'Gewijzigde functie',
            'actief' => '1',
        ])->assertRedirect(route('relaties.show', $this->paboOrg));

        $this->assertSame('Gewijzigde functie', $cp->fresh()->functie);

        $this->actingAs($this->relatiebeheerder)->post(route('contactpersonen.status', $cp))->assertRedirect();
        $this->assertFalse($cp->fresh()->actief);
    }
}
