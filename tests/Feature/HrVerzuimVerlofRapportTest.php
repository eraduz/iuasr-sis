<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Afdeling;
use App\Models\Medewerker;
use App\Models\User;
use App\Support\HrRapport;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\HrSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Module HR / Personeelszaken — rapportage "Verzuim & verlof per medewerker".
 * Bewaakt de per-medewerker-aggregatie (ziekteverzuim + verlof), de jaar-/
 * afdelingsfilter, de CSV-export en de rolscheiding.
 */
class HrVerzuimVerlofRapportTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class, HrSeeder::class]);

        $this->hr = User::where('email', 'n.aslan@iuasr.nl')->firstOrFail();
    }

    private function rij(array $rijen, string $personeelsnummer): ?array
    {
        return (new Collection($rijen))->firstWhere('personeelsnummer', $personeelsnummer);
    }

    public function test_per_medewerker_toont_verzuim(): void
    {
        $rijen = HrRapport::perMedewerker(null, (int) date('Y'));

        $fadwa = $this->rij($rijen, 'P260005'); // open ziekmelding (seed)
        $this->assertNotNull($fadwa);
        $this->assertSame(1, $fadwa['ziek_meldingen']);
        $this->assertTrue($fadwa['momenteel_ziek']);
        $this->assertGreaterThan(0, $fadwa['ziektedagen']);

        $mehmet = $this->rij($rijen, 'P260004'); // drie herstelde meldingen (seed)
        $this->assertSame(3, $mehmet['ziek_meldingen']);
        $this->assertFalse($mehmet['momenteel_ziek']);
    }

    public function test_per_medewerker_toont_verlof(): void
    {
        $rijen = HrRapport::perMedewerker(null, (int) date('Y'));

        $sophie = $this->rij($rijen, 'P260003');
        $this->assertNotNull($sophie);
        $this->assertSame(24.0, $sophie['verlof_opgenomen']); // één goedgekeurde aanvraag van 24u
        $this->assertSame(1, $sophie['verlof_open']);         // één openstaande aanvraag
        $this->assertGreaterThan(0, $sophie['verlof_recht']);
        $this->assertSame(round($sophie['verlof_recht'] - 24.0, 1), $sophie['verlof_saldo']);
    }

    public function test_jaar_filter_beperkt_verzuim_tot_dat_jaar(): void
    {
        $vorigJaar = HrRapport::perMedewerker(null, (int) date('Y') - 1);
        $fadwa = $this->rij($vorigJaar, 'P260005');

        $this->assertSame(0, $fadwa['ziek_meldingen']); // de melding valt in het huidige jaar
    }

    public function test_pagina_rendert(): void
    {
        $this->actingAs($this->hr)->get(route('hr.verzuimverlof'))
            ->assertOk()
            ->assertSee('Verzuim &amp; verlof per medewerker', false)
            ->assertSee('Yilmaz')
            ->assertSee('Ben Ali');
    }

    public function test_afdeling_filter(): void
    {
        $pabo = Afdeling::where('code', 'ONDW-PABO')->firstOrFail();

        $this->actingAs($this->hr)->get(route('hr.verzuimverlof', ['afdeling' => $pabo->id]))
            ->assertOk()
            ->assertSee('Yilmaz')       // Mehmet zit in het PABO-team
            ->assertDontSee('Ben Ali');  // Fadwa (Administratie) valt buiten het filter
    }

    public function test_csv_export(): void
    {
        $response = $this->actingAs($this->hr)->get(route('hr.verzuimverlof.export', ['jaar' => date('Y')]));
        $response->assertOk();
        $this->assertStringContainsString('ziektedagen', $response->streamedContent());
        $this->assertStringContainsString('Yilmaz', $response->streamedContent());
    }

    public function test_studentenzaken_heeft_geen_toegang(): void
    {
        $sz = User::where('rol', Rol::Studentenzaken)->firstOrFail();
        $this->actingAs($sz)->get(route('hr.verzuimverlof'))->assertForbidden();
    }
}
