<?php

namespace Tests\Feature;

use App\Enums\CursusinschrijvingStatus;
use App\Enums\Rol;
use App\Models\Cursist;
use App\Models\Cursus;
use App\Models\Cursusbetaling;
use App\Models\Cursusinschrijving;
use App\Models\User;
use App\Support\Cursusgeldstatus;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase C — cursusgelden & boekhouding. De Financiële Administratie registreert en
 * corrigeert cursusgeldbetalingen; alleen betalingen met status 'Betaald' tellen
 * mee. De cursusadministratie mag hier niet bij.
 */
class CursusbetalingTest extends TestCase
{
    use RefreshDatabase;

    private User $boekhouding;

    private User $cursusadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, GebruikerSeeder::class]);
        $this->boekhouding = User::where('rol', Rol::Financien)->firstOrFail();
        $this->cursusadmin = User::where('rol', Rol::Cursusadministratie)->firstOrFail();
    }

    private function inschrijving(float $bedrag = 330.0): Cursusinschrijving
    {
        $cursus = Cursus::where('code', 'HIFZ')->firstOrFail();
        $cursist = Cursist::create(['cursistnummer' => 'C260100', 'voornaam' => 'Sara', 'achternaam' => 'El Amrani']);

        return $cursist->inschrijvingen()->create([
            'cursus_id' => $cursus->id,
            'inschrijfdatum' => now(),
            'status' => CursusinschrijvingStatus::Actief,
            'totaalbedrag' => $bedrag,
        ]);
    }

    public function test_boekhouding_ziet_het_betalingenoverzicht(): void
    {
        $this->inschrijving();

        $this->actingAs($this->boekhouding)->get(route('cursussen.betalingen'))
            ->assertOk()->assertSee('Cursusgelden')->assertSee('Sara El Amrani');
    }

    public function test_boekhouding_kan_het_cursusdashboard_openen(): void
    {
        // Nodig omdat de modulekiezer de Financiën-rol naar cursussen.dashboard stuurt.
        $this->actingAs($this->boekhouding)->get(route('cursussen.dashboard'))->assertOk();
    }

    public function test_betaling_registreren_verlaagt_het_openstaande_bedrag(): void
    {
        $inschrijving = $this->inschrijving(330.0);

        $this->actingAs($this->boekhouding)->post(route('cursussen.betaling.registreer', $inschrijving), [
            'betaalmethode' => 'ideal', 'bedrag' => '330.00', 'betaaldatum' => now()->toDateString(),
            'betalingsstatus' => 'betaald',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $geld = Cursusgeldstatus::voor($inschrijving->fresh()->load('betalingen'));
        $this->assertSame(0.0, $geld['openstaand']);
        $this->assertSame(Cursusgeldstatus::VOLDAAN, $geld['status']);
    }

    public function test_deelbetaling_geeft_status_deels(): void
    {
        $inschrijving = $this->inschrijving(330.0);

        $this->actingAs($this->boekhouding)->post(route('cursussen.betaling.registreer', $inschrijving), [
            'betaalmethode' => 'contant', 'bedrag' => '100.00', 'betaaldatum' => now()->toDateString(),
            'betalingsstatus' => 'betaald',
        ])->assertSessionHasNoErrors();

        $geld = Cursusgeldstatus::voor($inschrijving->fresh()->load('betalingen'));
        $this->assertSame(230.0, $geld['openstaand']);
        $this->assertSame(Cursusgeldstatus::DEELS, $geld['status']);
    }

    public function test_alleen_status_betaald_telt_mee(): void
    {
        $inschrijving = $this->inschrijving(330.0);

        // In afwachting telt niet als voldaan.
        $this->actingAs($this->boekhouding)->post(route('cursussen.betaling.registreer', $inschrijving), [
            'betaalmethode' => 'ideal', 'bedrag' => '330.00', 'betaaldatum' => now()->toDateString(),
            'betalingsstatus' => 'in_afwachting',
        ])->assertSessionHasNoErrors();

        $geld = Cursusgeldstatus::voor($inschrijving->fresh()->load('betalingen'));
        $this->assertSame(330.0, $geld['openstaand']);
        $this->assertSame(Cursusgeldstatus::OPEN, $geld['status']);
    }

    public function test_boekhouding_kan_een_betaling_wijzigen(): void
    {
        $inschrijving = $this->inschrijving(330.0);
        $betaling = $inschrijving->betalingen()->create([
            'betaalmethode' => 'contant', 'bedrag' => 100.0, 'betaaldatum' => now(),
            'betalingsstatus' => 'betaald',
        ]);

        $this->actingAs($this->boekhouding)->put(route('cursussen.betaling.bijwerken', $betaling), [
            'betaalmethode' => 'overboeking', 'bedrag' => '330.00', 'betaaldatum' => now()->toDateString(),
            'betalingsstatus' => 'betaald',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(330.0, (float) $betaling->fresh()->bedrag);
    }

    public function test_boekhouding_kan_een_betaling_verwijderen(): void
    {
        $inschrijving = $this->inschrijving(330.0);
        $betaling = $inschrijving->betalingen()->create([
            'betaalmethode' => 'contant', 'bedrag' => 330.0, 'betaaldatum' => now(),
            'betalingsstatus' => 'betaald',
        ]);

        $this->actingAs($this->boekhouding)->delete(route('cursussen.betaling.verwijderen', $betaling))
            ->assertRedirect();

        $this->assertDatabaseMissing('cursusbetalingen', ['id' => $betaling->id]);
    }

    public function test_cursusadministratie_mag_niet_bij_de_betalingen(): void
    {
        $inschrijving = $this->inschrijving();

        $this->actingAs($this->cursusadmin)->get(route('cursussen.betalingen'))->assertForbidden();
        $this->actingAs($this->cursusadmin)->post(route('cursussen.betaling.registreer', $inschrijving), [
            'betaalmethode' => 'ideal', 'bedrag' => '330.00', 'betaaldatum' => now()->toDateString(),
            'betalingsstatus' => 'betaald',
        ])->assertForbidden();
    }

    public function test_bedrag_moet_positief_zijn(): void
    {
        $inschrijving = $this->inschrijving();

        $this->actingAs($this->boekhouding)->post(route('cursussen.betaling.registreer', $inschrijving), [
            'betaalmethode' => 'ideal', 'bedrag' => '0', 'betaaldatum' => now()->toDateString(),
            'betalingsstatus' => 'betaald',
        ])->assertSessionHasErrors('bedrag');

        $this->assertSame(0, Cursusbetaling::count());
    }

    public function test_wijziging_wordt_gelogd(): void
    {
        $inschrijving = $this->inschrijving(330.0);
        $betaling = $inschrijving->betalingen()->create([
            'betaalmethode' => 'contant', 'bedrag' => 100.0, 'betaaldatum' => now(),
            'betalingsstatus' => 'betaald',
        ]);

        $this->actingAs($this->boekhouding)->put(route('cursussen.betaling.bijwerken', $betaling), [
            'betaalmethode' => 'contant', 'bedrag' => '150.00', 'betaaldatum' => now()->toDateString(),
            'betalingsstatus' => 'betaald',
        ]);

        $this->assertDatabaseHas('audit_logs', ['actie' => 'wijziging', 'veld' => 'cursusbetaling']);
    }
}
