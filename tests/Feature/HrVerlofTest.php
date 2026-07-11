<?php

namespace Tests\Feature;

use App\Enums\MedewerkerStatus;
use App\Enums\Rol;
use App\Enums\Verlofstatus;
use App\Models\Medewerker;
use App\Models\User;
use App\Models\Verlofaanvraag;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\HrSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module HR / Personeelszaken — Fase B (verlof & verzuim). Bewaakt de
 * self-service-aanvraag, de goedkeuringsworkflow (manager/HR) en de ziekmelding.
 */
class HrVerlofTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;       // HR-medewerker (Nadia)
    private User $manager;  // Manager (Ruben)

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class, HrSeeder::class]);

        $this->hr = User::where('email', 'n.aslan@iuasr.nl')->firstOrFail();
        $this->manager = User::where('email', 'r.smit@iuasr.nl')->firstOrFail();
    }

    public function test_self_service_verlof_aanvragen(): void
    {
        $this->actingAs($this->manager)->post(route('verlof.store'), [
            'verloftype' => 'vakantie',
            'van' => date('Y').'-12-23',
            'tot' => date('Y').'-12-27',
            'uren' => 24,
        ])->assertRedirect(route('verlof.mijn'));

        $this->assertDatabaseHas('verlofaanvragen', [
            'medewerker_id' => $this->manager->medewerker->id,
            'status' => 'aangevraagd',
        ]);
    }

    public function test_mijn_verlof_toont_saldo(): void
    {
        $this->actingAs($this->manager)->get(route('verlof.mijn'))->assertOk()->assertSee('Saldo');
    }

    public function test_gebruiker_zonder_dossier_geen_selfservice(): void
    {
        $sz = User::where('rol', Rol::Studentenzaken)->firstOrFail(); // geen medewerker gekoppeld
        $this->actingAs($sz)->get(route('verlof.mijn'))->assertForbidden();
    }

    public function test_manager_keurt_teamaanvraag_goed(): void
    {
        $sophie = Medewerker::where('personeelsnummer', 'P260003')->firstOrFail();
        $aanvraag = $sophie->verlofaanvragen()->where('status', 'aangevraagd')->firstOrFail();

        $this->actingAs($this->manager)->post(route('verlof.beoordelen', $aanvraag), ['besluit' => 'goedgekeurd'])->assertRedirect();

        $this->assertSame(Verlofstatus::Goedgekeurd, $aanvraag->fresh()->status);
    }

    public function test_manager_kan_eigen_aanvraag_niet_beoordelen(): void
    {
        // Een eigen aanvraag van de manager: HR is de terugval, hij mag niet zelf.
        $eigen = Verlofaanvraag::create([
            'medewerker_id' => $this->manager->medewerker->id,
            'verloftype' => 'vakantie', 'van' => date('Y').'-11-01', 'tot' => date('Y').'-11-02',
            'uren' => 16, 'status' => 'aangevraagd', 'aangevraagd_door_id' => $this->manager->id,
        ]);

        $this->actingAs($this->manager)->post(route('verlof.beoordelen', $eigen), ['besluit' => 'goedgekeurd'])->assertForbidden();

        // HR mag dat wel.
        $this->actingAs($this->hr)->post(route('verlof.beoordelen', $eigen), ['besluit' => 'goedgekeurd'])->assertRedirect();
        $this->assertSame(Verlofstatus::Goedgekeurd, $eigen->fresh()->status);
    }

    public function test_ziekmelding_en_herstel(): void
    {
        $medewerker = Medewerker::where('personeelsnummer', 'P260006')->firstOrFail();

        $this->actingAs($this->hr)->post(route('ziekmeldingen.store'), [
            'medewerker_id' => $medewerker->id,
            'ziek_van' => date('Y').'-07-01',
        ])->assertRedirect();

        $this->assertSame(MedewerkerStatus::Ziek, $medewerker->fresh()->status);
        $melding = $medewerker->ziekmeldingen()->whereNull('hersteld_op')->firstOrFail();

        $this->actingAs($this->hr)->post(route('ziekmeldingen.herstel', $melding), ['hersteld_op' => date('Y').'-07-10'])->assertRedirect();
        $this->assertSame(MedewerkerStatus::Actief, $medewerker->fresh()->status);
    }

    public function test_verlofrecht_instellen(): void
    {
        $medewerker = Medewerker::where('personeelsnummer', 'P260006')->firstOrFail();

        $this->actingAs($this->hr)->post(route('verlofsaldo.bijwerken', $medewerker), [
            'jaar' => date('Y'),
            'recht' => ['vakantie' => 180, 'studie' => 40],
        ])->assertRedirect(route('medewerkers.show', $medewerker));

        $this->assertDatabaseHas('verlofsaldi', [
            'medewerker_id' => $medewerker->id, 'verloftype' => 'studie', 'recht_uren' => 40,
        ]);
    }

    public function test_verlofoverzicht_toont_opgenomen(): void
    {
        // Sophie heeft een goedgekeurde aanvraag van 24 uur vakantie (opgenomen).
        $sophie = Medewerker::where('personeelsnummer', 'P260003')->firstOrFail();
        $saldo = \App\Support\Verlofoverzicht::voor($sophie);
        $vakantie = collect($saldo)->firstWhere(fn ($r) => $r['type']->value === 'vakantie');

        $this->assertSame(24.0, $vakantie['opgenomen']);
    }
}
