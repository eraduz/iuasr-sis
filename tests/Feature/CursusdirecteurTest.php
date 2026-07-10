<?php

namespace Tests\Feature;

use App\Enums\CursusinschrijvingStatus;
use App\Enums\Rol;
use App\Models\Cursist;
use App\Models\Cursus;
use App\Models\User;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase D — cursusdirecteuren met toegang per cursus. Een cursusdirecteur
 * (rol Cursusadministratie) ziet en beheert uitsluitend de eigen cursus(sen);
 * Financiën (boekhouding), Beheer en Bestuur zien alle cursussen.
 */
class CursusdirecteurTest extends TestCase
{
    use RefreshDatabase;

    private User $hafsa;   // directeur ARAB-TAAL + HIFZ

    private User $omar;    // directeur IJAZA

    private Cursus $arab;

    private Cursus $ijaza;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, GebruikerSeeder::class]);
        $this->hafsa = User::where('email', 'h.bakkali@iuasr.nl')->firstOrFail();
        $this->omar = User::where('email', 'o.faruk@iuasr.nl')->firstOrFail();
        $this->arab = Cursus::where('code', 'ARAB-TAAL')->firstOrFail();
        $this->ijaza = Cursus::where('code', 'IJAZA')->firstOrFail();
    }

    private function cursistOp(Cursus $cursus, string $nummer = 'C260900'): Cursist
    {
        $cursist = Cursist::create(['cursistnummer' => $nummer, 'voornaam' => 'Test', 'achternaam' => 'Persoon']);
        $cursist->inschrijvingen()->create([
            'cursus_id' => $cursus->id, 'inschrijfdatum' => now(),
            'status' => CursusinschrijvingStatus::Actief, 'totaalbedrag' => $cursus->cursusgeld,
        ]);

        return $cursist;
    }

    /* ------------------------------------------------------------ scoping */

    public function test_directeur_ziet_alleen_eigen_cursussen_op_het_dashboard(): void
    {
        $this->actingAs($this->hafsa)->get(route('cursussen.dashboard'))
            ->assertOk()->assertSee('Arabische Taal')->assertSee('Hifz')->assertDontSee('Ijaaza');
    }

    public function test_directeur_beheerlijst_is_gescoped(): void
    {
        $this->assertSame(2, Cursus::query()->zichtbaarVoor($this->hafsa)->count());
        $this->assertSame(1, Cursus::query()->zichtbaarVoor($this->omar)->count());
    }

    public function test_directeur_mag_andermans_cursus_niet_bewerken(): void
    {
        // Hafsa dirigeert IJAZA niet (dat is Omar).
        $this->actingAs($this->hafsa)->get(route('cursussen.edit', $this->ijaza))->assertForbidden();
        $this->actingAs($this->hafsa)->put(route('cursussen.update', $this->ijaza), [
            'code' => 'IJAZA', 'naam' => 'Gekaapt', 'cursusgeld' => '1',
        ])->assertForbidden();
    }

    public function test_directeur_mag_eigen_cursus_wel_bewerken(): void
    {
        $this->actingAs($this->hafsa)->get(route('cursussen.edit', $this->arab))->assertOk();
        $this->actingAs($this->hafsa)->put(route('cursussen.update', $this->arab), [
            'code' => 'ARAB-TAAL', 'naam' => 'Arabische Taal (bijgewerkt)', 'cursusgeld' => '275',
        ])->assertSessionHasNoErrors()->assertRedirect(route('cursussen.beheer'));

        $this->assertSame('Arabische Taal (bijgewerkt)', $this->arab->fresh()->naam);
    }

    public function test_directeur_kan_directeur_niet_wijzigen_via_update(): void
    {
        // Zelfs met directeur_id in het verzoek blijft de eigen directeur behouden.
        $this->actingAs($this->hafsa)->put(route('cursussen.update', $this->arab), [
            'code' => 'ARAB-TAAL', 'naam' => 'Arabische Taal', 'cursusgeld' => '265',
            'directeur_id' => $this->omar->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->hafsa->id, $this->arab->fresh()->directeur_id);
    }

    public function test_directeur_ziet_andermans_cursist_niet(): void
    {
        $cursist = $this->cursistOp($this->ijaza); // alleen bij Omar

        $this->actingAs($this->hafsa)->get(route('cursisten.show', $cursist))->assertForbidden();
        $this->actingAs($this->omar)->get(route('cursisten.show', $cursist))->assertOk();
    }

    public function test_cursistenlijst_is_gescoped(): void
    {
        $this->cursistOp($this->ijaza, 'C260901'); // Omar
        $this->cursistOp($this->arab, 'C260902');  // Hafsa

        $this->actingAs($this->hafsa)->get(route('cursisten'))
            ->assertOk()->assertSee('C260902')->assertDontSee('C260901');
    }

    public function test_directeur_kan_niet_op_andermans_cursus_inschrijven(): void
    {
        $cursist = $this->cursistOp($this->arab, 'C260903'); // Hafsa's cursist

        $this->actingAs($this->hafsa)->post(route('cursisten.inschrijven', $cursist), [
            'cursus_id' => $this->ijaza->id,
        ])->assertForbidden();

        $this->assertDatabaseMissing('cursusinschrijvingen', [
            'cursist_id' => $cursist->id, 'cursus_id' => $this->ijaza->id,
        ]);
    }

    public function test_directeur_mag_geen_cursus_verwijderen(): void
    {
        $this->actingAs($this->hafsa)->delete(route('cursussen.destroy', $this->arab))->assertForbidden();
        $this->assertDatabaseHas('cursussen', ['id' => $this->arab->id]);
    }

    /* -------------------------------------------------------- boekhouding */

    public function test_boekhouding_ziet_alle_cursussen(): void
    {
        $financien = User::where('rol', Rol::Financien)->firstOrFail();
        $this->assertSame(3, Cursus::query()->zichtbaarVoor($financien)->count());
        $this->assertFalse($financien->isCursusBeperkt());
    }

    /* ------------------------------------------------------------ bestuur */

    public function test_bestuur_heeft_toegang_tot_de_cursusmodule(): void
    {
        $bestuur = User::where('rol', Rol::Bestuur)->firstOrFail();
        $this->assertTrue($bestuur->rol->magModule('cursussen'));
        $this->assertFalse($bestuur->isCursusBeperkt());
    }

    public function test_bestuur_ziet_dashboard_en_alle_cursussen(): void
    {
        $bestuur = User::where('rol', Rol::Bestuur)->firstOrFail();
        $this->actingAs($bestuur)->get(route('cursussen.dashboard'))
            ->assertOk()->assertSee('Arabische Taal')->assertSee('Ijaaza');
    }

    public function test_bestuur_ziet_cursisten_maar_kan_niet_beheren(): void
    {
        $bestuur = User::where('rol', Rol::Bestuur)->firstOrFail();
        $cursist = $this->cursistOp($this->arab, 'C260904');

        $this->actingAs($bestuur)->get(route('cursisten'))->assertOk()->assertSee('C260904');
        // Alleen-lezen: geen inschrijf-/wijzigknoppen.
        $this->actingAs($bestuur)->get(route('cursisten.show', $cursist))
            ->assertOk()->assertDontSee('Inschrijven op cursus');

        // En geen toegang tot de beheer-/mutatieroutes.
        $this->actingAs($bestuur)->get(route('cursisten.create'))->assertForbidden();
        $this->actingAs($bestuur)->post(route('cursussen.store'), [
            'code' => 'X', 'naam' => 'X', 'cursusgeld' => '1',
        ])->assertForbidden();
        $this->actingAs($bestuur)->get(route('cursussen.betalingen'))->assertForbidden();
    }

    /* ------------------------------------------------------------ beheerder */

    public function test_beheerder_wijst_een_directeur_toe(): void
    {
        $beheer = User::where('rol', Rol::Beheerder)->firstOrFail();

        $this->actingAs($beheer)->put(route('cursussen.update', $this->ijaza), [
            'code' => 'IJAZA', 'naam' => 'Certificaatprogramma / Ijaaza', 'cursusgeld' => '430',
            'directeur_id' => $this->hafsa->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->hafsa->id, $this->ijaza->fresh()->directeur_id);
        // Nu ziet Hafsa drie cursussen, Omar geen.
        $this->assertSame(3, Cursus::query()->zichtbaarVoor($this->hafsa)->count());
        $this->assertSame(0, Cursus::query()->zichtbaarVoor($this->omar)->count());
    }
}
