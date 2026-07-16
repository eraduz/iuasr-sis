<?php

namespace Tests\Feature;

use App\Enums\Bestuursorgaan;
use App\Enums\Bestuurstitel;
use App\Enums\Rol;
use App\Models\Bestuurslid;
use App\Models\Bestuursvergadering;
use App\Models\User;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StichtingsbestuurModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $bestuur;
    private User $studentenzaken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferentieSeeder::class);
        $this->bestuur = User::create(['naam' => 'SB', 'email' => 'sb@iuasr.test', 'rol' => Rol::Stichtingsbestuur]);
        $this->studentenzaken = User::create(['naam' => 'SZ', 'email' => 'sz@iuasr.test', 'rol' => Rol::Studentenzaken]);
    }

    private function lid(array $overschrijf = []): Bestuurslid
    {
        return Bestuurslid::create(array_merge([
            'orgaan' => Bestuursorgaan::Stichtingsbestuur->value,
            'titel' => Bestuurstitel::Voorzitter->value,
            'voornaam' => 'Test',
            'achternaam' => 'Lid',
            'actief' => true,
        ], $overschrijf));
    }

    public function test_module_verschijnt_op_het_keuzescherm(): void
    {
        $this->actingAs($this->bestuur)->get(route('modules.kiezen'))->assertOk()->assertSee('Stichtingsbestuur');
    }

    public function test_rol_ziet_de_schermen(): void
    {
        $this->actingAs($this->bestuur)->get(route('stichtingsbestuur.dashboard'))->assertOk();
        $this->actingAs($this->bestuur)->get(route('stichtingsbestuur.leden'))->assertOk();
        $this->actingAs($this->bestuur)->get(route('stichtingsbestuur.vergaderingen'))->assertOk();
    }

    public function test_andere_rollen_hebben_geen_toegang(): void
    {
        $this->actingAs($this->studentenzaken)->get(route('stichtingsbestuur.dashboard'))->assertForbidden();
        $this->actingAs($this->studentenzaken)->post(route('stichtingsbestuur.leden.store'), [])->assertForbidden();
        $this->assertFalse($this->studentenzaken->magStichtingsbestuurInzien());
    }

    public function test_lid_toevoegen_en_gelogd(): void
    {
        $this->actingAs($this->bestuur)->post(route('stichtingsbestuur.leden.store'), [
            'orgaan' => Bestuursorgaan::Stichtingsbestuur->value,
            'titel' => Bestuurstitel::Penningmeester->value,
            'voornaam' => 'Fatima',
            'achternaam' => 'El Idrissi',
            'bevoegdheid' => 'Financieel beheer',
            'actief' => '1',
        ])->assertRedirect(route('stichtingsbestuur.leden'));

        $this->assertDatabaseHas('bestuursleden', ['achternaam' => 'El Idrissi', 'bevoegdheid' => 'Financieel beheer']);
        $this->assertDatabaseHas('audit_logs', ['veld' => 'bestuurslid', 'actie' => 'aanmaak']);
    }

    public function test_commissaris_krijgt_geen_bevoegdheid(): void
    {
        $this->actingAs($this->bestuur)->post(route('stichtingsbestuur.leden.store'), [
            'orgaan' => Bestuursorgaan::RaadVanToezicht->value,
            'titel' => Bestuurstitel::Commissaris->value,
            'voornaam' => 'Omar',
            'achternaam' => 'Chakir',
            'bevoegdheid' => 'Dit hoort niet bewaard te worden',
        ])->assertRedirect();

        $this->assertDatabaseHas('bestuursleden', ['achternaam' => 'Chakir', 'bevoegdheid' => null]);
    }

    public function test_vergadering_met_aanwezigheid(): void
    {
        $lid1 = $this->lid();
        $lid2 = $this->lid(['titel' => Bestuurstitel::Secretaris->value, 'achternaam' => 'Twee']);

        $this->actingAs($this->bestuur)->post(route('stichtingsbestuur.vergaderingen.store'), [
            'datum' => '2026-03-12',
            'orgaan' => Bestuursorgaan::Stichtingsbestuur->value,
            'onderwerpen' => 'Begroting',
            'besluiten' => 'Goedgekeurd',
            'aanwezigheid' => [$lid1->id => 'fysiek', $lid2->id => 'online'],
        ])->assertRedirect();

        $vergadering = Bestuursvergadering::firstOrFail();
        $this->assertSame($this->bestuur->id, $vergadering->genotuleerd_door_id);
        $this->assertDatabaseHas('bestuursvergadering_aanwezigheden', ['bestuurslid_id' => $lid1->id, 'aanwezigheid' => 'fysiek']);
        $this->assertDatabaseHas('bestuursvergadering_aanwezigheden', ['bestuurslid_id' => $lid2->id, 'aanwezigheid' => 'online']);
        $this->assertSame(2, $vergadering->aanwezigheden()->count());
    }

    public function test_lege_aanwezigheid_verwijdert_de_registratie(): void
    {
        $lid = $this->lid();
        $vergadering = Bestuursvergadering::create(['datum' => '2026-01-01', 'orgaan' => Bestuursorgaan::Stichtingsbestuur->value]);
        $vergadering->aanwezigheden()->create(['bestuurslid_id' => $lid->id, 'aanwezigheid' => 'fysiek']);

        $this->actingAs($this->bestuur)->put(route('stichtingsbestuur.vergaderingen.update', $vergadering), [
            'datum' => '2026-01-01',
            'orgaan' => Bestuursorgaan::Stichtingsbestuur->value,
            'aanwezigheid' => [$lid->id => ''],
        ])->assertRedirect();

        $this->assertDatabaseMissing('bestuursvergadering_aanwezigheden', ['bestuurslid_id' => $lid->id]);
    }

    public function test_vergadering_detail_rendert(): void
    {
        $vergadering = Bestuursvergadering::create(['datum' => '2026-02-02', 'orgaan' => Bestuursorgaan::RaadVanToezicht->value, 'onderwerpen' => 'Toezicht']);

        $this->actingAs($this->bestuur)->get(route('stichtingsbestuur.vergaderingen.show', $vergadering))
            ->assertOk()->assertSee('Toezicht');
    }
}
