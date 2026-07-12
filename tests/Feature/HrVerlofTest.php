<?php

namespace Tests\Feature;

use App\Enums\MedewerkerStatus;
use App\Enums\Rol;
use App\Enums\Verlofstatus;
use App\Mail\VerlofaanvraagMelding;
use App\Models\Medewerker;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\HrSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module HR / Personeelszaken — Fase B (verlof & verzuim). Bewaakt de
 * self-service-aanvraag, de goedkeuringsworkflow (gecombineerde HR-rol) en de
 * ziekmelding. HR-medewerker en Manager zijn samengevoegd tot één rol.
 */
class HrVerlofTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;         // HR-medewerker (Nadia)
    private User $leidingg;   // HR-medewerker/leidinggevende (Ruben)

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class, HrSeeder::class]);

        $this->hr = User::where('email', 'n.aslan@iuasr.nl')->firstOrFail();
        $this->leidingg = User::where('email', 'r.smit@iuasr.nl')->firstOrFail();
    }

    public function test_self_service_verlof_aanvragen(): void
    {
        $this->actingAs($this->leidingg)->post(route('verlof.store'), [
            'verloftype' => 'vakantie',
            'van' => date('Y').'-12-23',
            'tot' => date('Y').'-12-27',
            'uren' => 24,
        ])->assertRedirect(route('verlof.mijn'));

        $this->assertDatabaseHas('verlofaanvragen', [
            'medewerker_id' => $this->leidingg->medewerker->id,
            'status' => 'aangevraagd',
        ]);
    }

    public function test_self_service_aanvraag_mailt_personeelszaken(): void
    {
        Mail::fake();

        $this->actingAs($this->leidingg)->post(route('verlof.store'), [
            'verloftype' => 'vakantie',
            'van' => date('Y').'-12-23',
            'tot' => date('Y').'-12-27',
            'uren' => 24,
        ])->assertRedirect(route('verlof.mijn'));

        Mail::assertSent(VerlofaanvraagMelding::class, fn ($mail) => $mail->hasTo('personeelszaken@iuasr.nl'));
    }

    public function test_mijn_verlof_toont_saldo(): void
    {
        $this->actingAs($this->leidingg)->get(route('verlof.mijn'))->assertOk()->assertSee('Saldo');
    }

    public function test_gebruiker_zonder_dossier_geen_selfservice(): void
    {
        $sz = User::where('rol', Rol::Studentenzaken)->firstOrFail(); // geen medewerker gekoppeld
        $this->actingAs($sz)->get(route('verlof.mijn'))->assertForbidden();
    }

    public function test_hr_keurt_aanvraag_goed(): void
    {
        $sophie = Medewerker::where('personeelsnummer', 'P260003')->firstOrFail();
        $aanvraag = $sophie->verlofaanvragen()->where('status', 'aangevraagd')->firstOrFail();

        $this->actingAs($this->leidingg)->post(route('verlof.beoordelen', $aanvraag), ['besluit' => 'goedgekeurd'])->assertRedirect();

        $this->assertSame(Verlofstatus::Goedgekeurd, $aanvraag->fresh()->status);
    }

    public function test_studentenzaken_kan_geen_aanvraag_beoordelen(): void
    {
        $sophie = Medewerker::where('personeelsnummer', 'P260003')->firstOrFail();
        $aanvraag = $sophie->verlofaanvragen()->where('status', 'aangevraagd')->firstOrFail();

        $sz = User::where('rol', Rol::Studentenzaken)->firstOrFail();
        $this->actingAs($sz)->post(route('verlof.beoordelen', $aanvraag), ['besluit' => 'goedgekeurd'])->assertForbidden();
        $this->assertSame(Verlofstatus::Aangevraagd, $aanvraag->fresh()->status);
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

    public function test_verlofformulier_toont_wettelijke_verloftypen(): void
    {
        $this->actingAs($this->leidingg)->get(route('verlof.create'))
            ->assertOk()
            ->assertSee('Zwangerschaps- en bevallingsverlof')
            ->assertSee('Geboorteverlof (partner)')
            ->assertSee('vf-toelichting', false);
    }

    public function test_zwangerschapsverlof_aanvragen(): void
    {
        $this->actingAs($this->leidingg)->post(route('verlof.store'), [
            'verloftype' => 'zwangerschap',
            'van' => date('Y').'-08-20',
            'tot' => date('Y').'-12-10',
            'uren' => 512,
        ])->assertRedirect(route('verlof.mijn'));

        $this->assertDatabaseHas('verlofaanvragen', [
            'medewerker_id' => $this->leidingg->medewerker->id,
            'verloftype' => 'zwangerschap',
        ]);
    }

    public function test_wettelijk_verlof_staat_niet_in_het_saldo(): void
    {
        // WAZO-verlof loopt via UWV en hoort niet in de recht/opgenomen/saldo-tabel.
        $sophie = Medewerker::where('personeelsnummer', 'P260003')->firstOrFail();
        $typen = collect(\App\Support\Verlofoverzicht::voor($sophie))->map(fn ($r) => $r['type']->value);

        $this->assertFalse($typen->contains('zwangerschap'));
        $this->assertFalse($typen->contains('geboorte'));
        $this->assertFalse($typen->contains('ouderschap')); // wettelijk, loopt via UWV
        $this->assertTrue($typen->contains('vakantie'));
    }

    public function test_ouderschapsverlof_rekenregel_en_aanvraag(): void
    {
        // 26x weekuren totaal, waarvan 9 weken (9x) deels betaald via UWV.
        $uren = \App\Support\Wettelijkverlof::ouderschapsverlofUren(36);
        $this->assertSame(936.0, $uren['totaal']);   // 26 x 36
        $this->assertSame(324.0, $uren['betaald']);  // 9 x 36
        $this->assertSame(612.0, $uren['onbetaald']); // rest

        $this->actingAs($this->leidingg)->post(route('verlof.store'), [
            'verloftype' => 'ouderschap',
            'van' => date('Y').'-09-01',
            'tot' => date('Y').'-11-03',
            'uren' => 324,
        ])->assertRedirect(route('verlof.mijn'));

        $this->assertDatabaseHas('verlofaanvragen', [
            'medewerker_id' => $this->leidingg->medewerker->id,
            'verloftype' => 'ouderschap',
        ]);
    }

    public function test_wettelijkverlof_helper_berekent_zwangerschapsperiode(): void
    {
        $wazo = \App\Support\Wettelijkverlof::zwangerschapEnBevalling(
            \Illuminate\Support\Carbon::create(2026, 10, 1)
        );

        $this->assertSame('2026-08-20', $wazo['van']->toDateString()); // 6 weken ervoor
        $this->assertSame('2026-12-10', $wazo['tot']->toDateString()); // 10 weken erna
        $this->assertSame(16, $wazo['weken']);
        $this->assertSame(38.0, \App\Support\Wettelijkverlof::geboorteverlofUren(38));      // 1× weekuren
        $this->assertSame(190.0, \App\Support\Wettelijkverlof::aanvullendGeboorteverlofUren(38)); // 5× weekuren
    }
}
