<?php

namespace Tests\Feature;

use App\Models\Gesprek;
use App\Models\Medewerker;
use App\Models\User;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\HrSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module HR / Personeelszaken — Fase C (gesprekken & performance). Bewaakt het
 * plannen, de doelen/competenties en het overzicht. HR-medewerker en Manager zijn
 * samengevoegd tot één rol die alle medewerkers ziet.
 */
class HrGesprekTest extends TestCase
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

    public function test_hr_plant_gesprek_voor_medewerker(): void
    {
        $sophie = Medewerker::where('personeelsnummer', 'P260003')->firstOrFail();

        $this->actingAs($this->leidingg)->post(route('gesprekken.store', $sophie), [
            'type' => 'functionering',
            'datum' => date('Y').'-12-01',
            'status' => 'gepland',
        ])->assertRedirect();

        $this->assertDatabaseHas('gesprekken', [
            'medewerker_id' => $sophie->id, 'type' => 'functionering',
        ]);
    }

    public function test_gecombineerde_hr_rol_plant_ook_buiten_eigen_team(): void
    {
        // Zonder team-scoping mag de gecombineerde HR-rol voor iedereen plannen.
        $fadwa = Medewerker::where('personeelsnummer', 'P260005')->firstOrFail();
        $this->actingAs($this->leidingg)->get(route('gesprekken.create', $fadwa))->assertOk();
    }

    public function test_doel_en_competentie_toevoegen(): void
    {
        $gesprek = Gesprek::where('type', 'beoordeling')->firstOrFail();

        $this->actingAs($this->hr)->post(route('gesprekken.doel.store', $gesprek), [
            'omschrijving' => 'Cursus didactiek afronden',
            'status' => 'open',
        ])->assertRedirect();

        $this->actingAs($this->hr)->post(route('gesprekken.competentie.store', $gesprek), [
            'competentie' => 'Communicatie',
            'score' => 'uitstekend',
        ])->assertRedirect();

        $this->assertDatabaseHas('gespreksdoelen', ['gesprek_id' => $gesprek->id, 'omschrijving' => 'Cursus didactiek afronden']);
        $this->assertDatabaseHas('competentiescores', ['gesprek_id' => $gesprek->id, 'competentie' => 'Communicatie', 'score' => 'uitstekend']);
    }

    public function test_gesprek_detail_toont_seed(): void
    {
        $gesprek = Gesprek::where('type', 'beoordeling')->firstOrFail();
        $this->actingAs($this->hr)->get(route('gesprekken.show', $gesprek))
            ->assertOk()
            ->assertSee('Nieuwe lesmethode invoeren')
            ->assertSee('Samenwerking');
    }

    public function test_overzicht_toont_alle_gesprekken(): void
    {
        // De gecombineerde HR-rol ziet alle gesprekken (geen team-scoping meer).
        $this->actingAs($this->leidingg)->get(route('gesprekken'))->assertOk()->assertSee('Mehmet');
        $this->actingAs($this->hr)->get(route('gesprekken'))->assertOk()->assertSee('Mehmet');
    }

    public function test_studentenzaken_heeft_geen_toegang(): void
    {
        $sz = User::where('rol', \App\Enums\Rol::Studentenzaken)->firstOrFail();
        $this->actingAs($sz)->get(route('gesprekken'))->assertForbidden();
    }
}
