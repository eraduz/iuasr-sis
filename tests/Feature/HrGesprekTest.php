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
 * plannen, de doelen/competenties, de team-scoping en het overzicht.
 */
class HrGesprekTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class, HrSeeder::class]);

        $this->hr = User::where('email', 'n.aslan@iuasr.nl')->firstOrFail();
        $this->manager = User::where('email', 'r.smit@iuasr.nl')->firstOrFail();
    }

    public function test_manager_plant_gesprek_voor_teamlid(): void
    {
        $sophie = Medewerker::where('personeelsnummer', 'P260003')->firstOrFail();

        $this->actingAs($this->manager)->post(route('gesprekken.store', $sophie), [
            'type' => 'functionering',
            'datum' => date('Y').'-12-01',
            'status' => 'gepland',
        ])->assertRedirect();

        $this->assertDatabaseHas('gesprekken', [
            'medewerker_id' => $sophie->id, 'type' => 'functionering',
        ]);
    }

    public function test_manager_kan_geen_gesprek_buiten_team(): void
    {
        $fadwa = Medewerker::where('personeelsnummer', 'P260005')->firstOrFail(); // niet Rubens team
        $this->actingAs($this->manager)->get(route('gesprekken.create', $fadwa))->assertForbidden();
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

    public function test_overzicht_is_gescoped(): void
    {
        // De manager ziet de gesprekken van het eigen team (Mehmet), HR alles.
        $this->actingAs($this->manager)->get(route('gesprekken'))->assertOk()->assertSee('Mehmet');
        $this->actingAs($this->hr)->get(route('gesprekken'))->assertOk();
    }

    public function test_studentenzaken_heeft_geen_toegang(): void
    {
        $sz = User::where('rol', \App\Enums\Rol::Studentenzaken)->firstOrFail();
        $this->actingAs($sz)->get(route('gesprekken'))->assertForbidden();
    }
}
