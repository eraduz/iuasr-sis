<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Medewerker;
use App\Models\User;
use App\Support\HrRapport;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\HrSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module HR / Personeelszaken — Fase D (organisatiestructuur & rapportages).
 * Bewaakt de kerncijfers, de per-afdeling-aggregatie en de CSV-export. HR-medewerker
 * en Manager zijn samengevoegd tot één rol die alle medewerkers ziet.
 */
class HrRapportTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;
    private User $leidingg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class, HrSeeder::class]);

        $this->hr = User::where('email', 'n.aslan@iuasr.nl')->firstOrFail();
        $this->leidingg = User::where('email', 'r.smit@iuasr.nl')->firstOrFail();
    }

    public function test_rapportpagina_rendert(): void
    {
        $this->actingAs($this->hr)->get(route('hr.rapport'))
            ->assertOk()
            ->assertSee('Totaal FTE')
            ->assertSee('PABO-team');
    }

    public function test_kerncijfers_en_verzuim(): void
    {
        $kpi = HrRapport::kerncijfers();
        $this->assertSame(6, $kpi['medewerkers']);
        $this->assertSame(1, $kpi['ziek']); // Fadwa is ziek gemeld
        $this->assertGreaterThan(0, $kpi['fte']);
    }

    public function test_gecombineerde_hr_rol_ziet_alle_medewerkers_in_rapport(): void
    {
        // Zonder team-scoping ziet de gecombineerde HR-rol alle 6 medewerkers.
        $ids = Medewerker::query()->zichtbaarVoor($this->leidingg)->pluck('id')->all();
        $this->assertSame(6, HrRapport::kerncijfers($ids)['medewerkers']);
    }

    public function test_csv_export(): void
    {
        $response = $this->actingAs($this->hr)->get(route('hr.rapport.export'));
        $response->assertOk();
        $this->assertStringContainsString('personeelsnummer', $response->streamedContent());
        $this->assertStringContainsString('Willemsen', $response->streamedContent());
    }

    public function test_organisatiestructuur_toont_boom(): void
    {
        $this->actingAs($this->hr)->get(route('hr.organisatie'))
            ->assertOk()
            ->assertSee('Onderwijs')
            ->assertSee('PABO-team');
    }

    public function test_studentenzaken_heeft_geen_toegang(): void
    {
        $sz = User::where('rol', Rol::Studentenzaken)->firstOrFail();
        $this->actingAs($sz)->get(route('hr.rapport'))->assertForbidden();
    }
}
