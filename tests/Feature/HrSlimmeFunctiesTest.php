<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Dienstverband;
use App\Models\Medewerker;
use App\Models\User;
use App\Models\Ziekmelding;
use App\Support\Verzuimsignalering;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\HrSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module HR / Personeelszaken — Fase G (slimme functies): globaal zoeken en de
 * signaleringen (aflopende contracten + verzuim volgens de Wet Verbetering
 * Poortwachter + frequent verzuim).
 */
class HrSlimmeFunctiesTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class, HrSeeder::class]);

        $this->hr = User::where('email', 'n.aslan@iuasr.nl')->firstOrFail();
    }

    public function test_zoeken_vindt_medewerker(): void
    {
        $this->actingAs($this->hr)->get(route('hr.zoeken', ['q' => 'Willemsen']))
            ->assertOk()
            ->assertSee('Willemsen')
            ->assertSee('P260003');
    }

    public function test_zoeken_vindt_afdeling(): void
    {
        $this->actingAs($this->hr)->get(route('hr.zoeken', ['q' => 'PABO']))
            ->assertOk()
            ->assertSee('PABO-team');
    }

    public function test_zoeken_vraagt_minimaal_twee_tekens(): void
    {
        $this->actingAs($this->hr)->get(route('hr.zoeken', ['q' => 'a']))
            ->assertOk()
            ->assertSee('minimaal twee tekens');
    }

    public function test_signaleringen_pagina_rendert(): void
    {
        $this->actingAs($this->hr)->get(route('hr.signaleringen'))
            ->assertOk()
            ->assertSee('Wet Verbetering Poortwachter')
            ->assertSee('Frequent verzuim');
    }

    public function test_poortwachter_mijlpalen_worden_afgeleid(): void
    {
        // Fadwa is 50 dagen ziek: probleemanalyse (week 6 = dag 42) is verstreken,
        // plan van aanpak (week 8 = dag 56) valt binnen het signaleringsvenster.
        $fadwa = Medewerker::where('personeelsnummer', 'P260005')->firstOrFail();
        Ziekmelding::where('medewerker_id', $fadwa->id)->whereNull('hersteld_op')
            ->update(['ziek_van' => now()->subDays(50)->toDateString()]);

        $rij = Verzuimsignalering::langdurig()->firstWhere(fn ($r) => $r['medewerker']->id === $fadwa->id);

        $this->assertNotNull($rij);
        $this->assertSame('verstreken', $rij['mijlpalen']->firstWhere('sleutel', 'probleemanalyse')['status']);
        $this->assertSame('binnenkort', $rij['mijlpalen']->firstWhere('sleutel', 'plan_van_aanpak')['status']);
    }

    public function test_frequent_verzuim_signaleert_meerdere_meldingen(): void
    {
        // Mehmet (P260004) heeft drie herstelde ziekmeldingen in het jaar (seed).
        $mehmet = Medewerker::where('personeelsnummer', 'P260004')->firstOrFail();

        $frequent = Verzuimsignalering::frequent();
        $rij = $frequent->firstWhere(fn ($r) => $r['medewerker']->id === $mehmet->id);

        $this->assertNotNull($rij);
        $this->assertGreaterThanOrEqual(3, $rij['aantal']);
    }

    public function test_aflopende_contracten_worden_gesignaleerd(): void
    {
        $johan = Medewerker::where('personeelsnummer', 'P260006')->firstOrFail();
        Dienstverband::create([
            'medewerker_id' => $johan->id,
            'contracttype' => 'tijdelijk',
            'startdatum' => now()->subYear()->toDateString(),
            'einddatum' => now()->addDays(30)->toDateString(),
            'uren_per_week' => 20,
        ]);

        $this->actingAs($this->hr)->get(route('hr.signaleringen'))
            ->assertOk()
            ->assertSee('Bakker');
    }

    public function test_studentenzaken_heeft_geen_toegang(): void
    {
        $sz = User::where('rol', Rol::Studentenzaken)->firstOrFail();
        $this->actingAs($sz)->get(route('hr.zoeken'))->assertForbidden();
        $this->actingAs($sz)->get(route('hr.signaleringen'))->assertForbidden();
    }
}
