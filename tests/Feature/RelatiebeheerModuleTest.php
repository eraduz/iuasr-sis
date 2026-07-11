<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Opleiding;
use App\Models\Organisatie;
use App\Models\User;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\OrganisatieSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module Relatiebeheer & Stagebeheer — Fase A (organisaties). Bewaakt de
 * moduletoegang, de opleidinggebonden scoping en de CRUD-rolscheiding.
 */
class RelatiebeheerModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $relatiebeheerder; // PABO
    private User $stagecoordinator; // ISLTH + MGV
    private User $beheerder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class, OrganisatieSeeder::class]);

        $this->relatiebeheerder = User::where('rol', Rol::Relatiebeheerder)->firstOrFail();
        $this->stagecoordinator = User::where('rol', Rol::Stagecoordinator)->firstOrFail();
        $this->beheerder = User::where('rol', Rol::Beheerder)->firstOrFail();
    }

    public function test_relatiebeheerder_ziet_de_organisatielijst(): void
    {
        $this->actingAs($this->relatiebeheerder)->get(route('relaties'))->assertOk();
    }

    public function test_beheerder_ziet_de_organisatielijst(): void
    {
        $this->actingAs($this->beheerder)->get(route('relaties'))->assertOk();
    }

    public function test_studentenzaken_heeft_geen_toegang(): void
    {
        $sz = User::where('rol', Rol::Studentenzaken)->firstOrFail();
        $this->actingAs($sz)->get(route('relaties'))->assertForbidden();
    }

    public function test_directie_kan_inzien_maar_niet_aanmaken(): void
    {
        $directie = User::where('rol', Rol::Directie)->firstOrFail();
        $this->actingAs($directie)->get(route('relaties'))->assertOk();
        $this->actingAs($directie)->get(route('relaties.create'))->assertForbidden();
    }

    public function test_opleiding_scoping_beperkt_de_zichtbaarheid(): void
    {
        // Relatiebeheerder is aan PABO gekoppeld: ziet PABO-organisaties, geen MGV.
        $zichtbaar = Organisatie::query()->zichtbaarVoor($this->relatiebeheerder)->pluck('naam');

        $this->assertTrue($zichtbaar->contains('Basisschool De Regenboog'));
        $this->assertFalse($zichtbaar->contains('Zorggroep Rijnmond'));

        // De stagecoördinator (ISLTH + MGV) ziet juist de MGV-relatie wel.
        $stage = Organisatie::query()->zichtbaarVoor($this->stagecoordinator)->pluck('naam');
        $this->assertTrue($stage->contains('Zorggroep Rijnmond'));
        $this->assertFalse($stage->contains('Basisschool De Regenboog'));
    }

    public function test_organisatie_aanmaken_genereert_relatienummer_en_koppelt_opleiding(): void
    {
        $paboId = Opleiding::where('code', 'PABO')->value('id');

        $this->actingAs($this->relatiebeheerder)->post(route('relaties.store'), [
            'naam' => 'Basisschool Al-Iman',
            'plaats' => 'Rotterdam',
            'opleidingen' => [$paboId],
            'actief' => '1',
        ])->assertRedirect();

        $org = Organisatie::where('naam', 'Basisschool Al-Iman')->firstOrFail();
        $this->assertNotEmpty($org->relatienummer);
        $this->assertStringStartsWith('R', $org->relatienummer);
        $this->assertTrue($org->opleidingen->pluck('id')->contains($paboId));
    }

    public function test_relatiebeheerder_kan_geen_vreemde_opleiding_koppelen(): void
    {
        $mgvId = Opleiding::where('code', 'MGV')->value('id');

        $this->actingAs($this->relatiebeheerder)->post(route('relaties.store'), [
            'naam' => 'Onbevoegde koppeling',
            'opleidingen' => [$mgvId],
        ])->assertSessionHasErrors('opleidingen.0');

        $this->assertDatabaseMissing('organisaties', ['naam' => 'Onbevoegde koppeling']);
    }

    public function test_relatiebeheerder_kan_organisatie_buiten_opleiding_niet_bewerken(): void
    {
        $mgvOrg = Organisatie::where('naam', 'Zorggroep Rijnmond')->firstOrFail();

        $this->actingAs($this->relatiebeheerder)->get(route('relaties.edit', $mgvOrg))->assertForbidden();
    }

    public function test_organisatie_inactiveren_verwijdert_niet(): void
    {
        $org = Organisatie::where('naam', 'Basisschool De Regenboog')->firstOrFail();

        $this->actingAs($this->relatiebeheerder)->post(route('relaties.status', $org))->assertRedirect();

        $this->assertFalse($org->fresh()->actief);
        $this->assertDatabaseHas('organisaties', ['id' => $org->id]);
    }

    public function test_module_verschijnt_op_het_keuzescherm(): void
    {
        $this->actingAs($this->relatiebeheerder)->get(route('modules.kiezen'))
            ->assertOk()
            ->assertSee('Relatiebeheer');
    }
}
