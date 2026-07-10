<?php

namespace Tests\Feature;

use App\Enums\CursusinschrijvingStatus;
use App\Enums\Rol;
use App\Models\Cursist;
use App\Models\Cursus;
use App\Models\Cursusinschrijving;
use App\Models\User;
use App\Support\Cursusrapport;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase E — cursusrapportage & dashboards. Inschrijvingen en cursusgelden per
 * cursus, met CSV-export. De cursusdirecteur ziet uitsluitend de eigen
 * cursus(sen); Financiën, Beheer en Bestuur zien alle cursussen.
 */
class CursusrapportTest extends TestCase
{
    use RefreshDatabase;

    private User $hafsa;

    private User $omar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, GebruikerSeeder::class]);
        $this->hafsa = User::where('email', 'h.bakkali@iuasr.nl')->firstOrFail();
        $this->omar = User::where('email', 'o.faruk@iuasr.nl')->firstOrFail();
    }

    private function inschrijvingMetBetaling(string $code, float $bedrag, float $betaald, string $nummer): Cursusinschrijving
    {
        $cursus = Cursus::where('code', $code)->firstOrFail();
        $cursist = Cursist::create(['cursistnummer' => $nummer, 'voornaam' => 'Test', 'achternaam' => 'Persoon '.$nummer]);
        $inschrijving = $cursist->inschrijvingen()->create([
            'cursus_id' => $cursus->id, 'inschrijfdatum' => now(),
            'status' => CursusinschrijvingStatus::Actief, 'totaalbedrag' => $bedrag,
        ]);
        if ($betaald > 0) {
            $inschrijving->betalingen()->create([
                'betaalmethode' => 'ideal', 'bedrag' => $betaald, 'betaaldatum' => now(),
                'betalingsstatus' => 'betaald',
            ]);
        }

        return $inschrijving;
    }

    public function test_rapport_toegankelijk_voor_de_betrokken_rollen(): void
    {
        foreach ([Rol::Cursusadministratie, Rol::Financien, Rol::Beheerder, Rol::Bestuur] as $rol) {
            $gebruiker = User::where('rol', $rol)->firstOrFail();
            $this->actingAs($gebruiker)->get(route('cursussen.rapport'))->assertOk()->assertSee('Cursusrapportage');
        }
    }

    public function test_rapport_geweigerd_voor_andere_rollen(): void
    {
        foreach ([Rol::Studentenzaken, Rol::Docent, Rol::Directie] as $rol) {
            $gebruiker = User::where('rol', $rol)->firstOrFail();
            $this->actingAs($gebruiker)->get(route('cursussen.rapport'))->assertForbidden();
        }
    }

    public function test_financiele_totalen_kloppen(): void
    {
        $this->inschrijvingMetBetaling('ARAB-TAAL', 265.0, 100.0, 'C260800');

        $cursussen = Cursus::query()->zichtbaarVoor($this->hafsa)->with('inschrijvingen.betalingen')->get();
        $totaal = Cursusrapport::financieelTotaal($cursussen);

        $this->assertSame(265.0, $totaal['verschuldigd']);
        $this->assertSame(100.0, $totaal['betaald']);
        $this->assertSame(165.0, $totaal['openstaand']);
        $this->assertSame(38, $totaal['betaalgraad']); // 100/265
    }

    public function test_geannuleerde_inschrijving_telt_niet_mee_financieel(): void
    {
        $inschrijving = $this->inschrijvingMetBetaling('ARAB-TAAL', 265.0, 0.0, 'C260801');
        $inschrijving->update(['status' => CursusinschrijvingStatus::Geannuleerd]);

        $cursussen = Cursus::query()->zichtbaarVoor($this->hafsa)->with('inschrijvingen.betalingen')->get();
        $totaal = Cursusrapport::financieelTotaal($cursussen);

        $this->assertSame(0.0, $totaal['verschuldigd']);
        $this->assertSame(0.0, $totaal['openstaand']);
    }

    public function test_rapport_is_gescoped_voor_de_directeur(): void
    {
        $this->inschrijvingMetBetaling('IJAZA', 430.0, 0.0, 'C260802'); // Omar's cursus

        // Hafsa ziet twee cursussen (ARAB + HIFZ), niet IJAZA.
        $hafsaCursussen = Cursus::query()->zichtbaarVoor($this->hafsa)->with('inschrijvingen.betalingen')->get();
        $this->assertSame(0.0, Cursusrapport::financieelTotaal($hafsaCursussen)['verschuldigd']);

        // Omar ziet IJAZA met de openstaande 430.
        $omarCursussen = Cursus::query()->zichtbaarVoor($this->omar)->with('inschrijvingen.betalingen')->get();
        $this->assertSame(430.0, Cursusrapport::financieelTotaal($omarCursussen)['openstaand']);
    }

    public function test_csv_export_bevat_alleen_eigen_cursisten(): void
    {
        $this->inschrijvingMetBetaling('ARAB-TAAL', 265.0, 265.0, 'C260803'); // Hafsa
        $this->inschrijvingMetBetaling('IJAZA', 430.0, 0.0, 'C260804');       // Omar

        $response = $this->actingAs($this->hafsa)->get(route('cursussen.rapport.export'));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));

        $inhoud = $response->streamedContent();
        $this->assertStringContainsString('C260803', $inhoud);
        $this->assertStringNotContainsString('C260804', $inhoud);
    }

    public function test_export_wordt_gelogd(): void
    {
        $this->actingAs($this->hafsa)->get(route('cursussen.rapport.export'))->assertOk();

        $this->assertDatabaseHas('audit_logs', ['actie' => 'inzage', 'veld' => 'cursusrapport_export']);
    }

    public function test_dashboard_toont_financiele_kpi(): void
    {
        $this->inschrijvingMetBetaling('ARAB-TAAL', 265.0, 100.0, 'C260805');

        $this->actingAs($this->hafsa)->get(route('cursussen.dashboard'))
            ->assertOk()->assertSee('Cursusgeld voldaan')->assertSee('Openstaand');
    }
}
