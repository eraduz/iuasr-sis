<?php

namespace Tests\Feature;

use App\Enums\Betaalregeling;
use App\Enums\InschrijvingStatus;
use App\Enums\Rol;
use App\Models\Betaling;
use App\Models\CollegegeldTarief;
use App\Models\Inschrijving;
use App\Models\Periode;
use App\Models\User;
use App\Support\Collegegeldstatus;
use App\Support\Collegegeldtermijnen;
use Carbon\Carbon;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollegegeldtermijnenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class,
            SynthetischeStudentSeeder::class]);

        // Vast jaartarief van € 4.000 voor de actieve periode (2025-2026).
        CollegegeldTarief::updateOrCreate(
            ['periode_id' => $this->periode()->id, 'opleiding_id' => null],
            ['bedrag' => 4000.00, 'aantal_termijnen' => 5],
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function periode(): Periode
    {
        return Periode::where('actief', true)->firstOrFail();
    }

    private function inschrijving(): Inschrijving
    {
        return Inschrijving::where('status', 'actief')
            ->where('periode_id', $this->periode()->id)
            ->with(['student', 'periode', 'betalingen'])->firstOrFail();
    }

    public function test_vijf_termijnen_op_de_juiste_vervaldata(): void
    {
        $termijnen = Collegegeldtermijnen::voor($this->inschrijving());

        $this->assertCount(5, $termijnen);
        $this->assertSame(
            ['01-09-2025', '01-11-2025', '01-01-2026', '01-03-2026', '01-05-2026'],
            $termijnen->map(fn ($t) => $t['vervaldatum']->format('d-m-Y'))->all(),
        );
    }

    public function test_termijnbedragen_tellen_op_tot_het_jaarbedrag(): void
    {
        $termijnen = Collegegeldtermijnen::voor($this->inschrijving());

        $this->assertSame(800.0, $termijnen[0]['bedrag']);
        $this->assertSame(4000.0, round($termijnen->sum('bedrag'), 2));
    }

    public function test_afrondingsrestje_komt_op_de_laatste_termijn(): void
    {
        CollegegeldTarief::where('periode_id', $this->periode()->id)->update(['bedrag' => 4001.00]);

        $termijnen = Collegegeldtermijnen::voor($this->inschrijving());

        $this->assertSame(800.2, $termijnen[0]['bedrag']);
        $this->assertSame(4001.0, round($termijnen->sum('bedrag'), 2));
    }

    public function test_volledige_regeling_geeft_een_factuur_in_september(): void
    {
        $insch = $this->inschrijving();
        $insch->update(['betaalregeling' => Betaalregeling::Volledig]);

        $termijnen = Collegegeldtermijnen::voor($insch->fresh()->load('betalingen'));

        $this->assertCount(1, $termijnen);
        $this->assertSame(4000.0, $termijnen[0]['bedrag']);
        $this->assertSame('01-09-2025', $termijnen[0]['vervaldatum']->format('d-m-Y'));
        $this->assertSame('Volledig jaarbedrag', $termijnen[0]['naam']);
    }

    public function test_vervallen_termijn_zonder_betaling_is_achterstallig(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-12-10')); // sep + nov zijn vervallen
        $insch = $this->inschrijving();

        $termijnen = Collegegeldtermijnen::voor($insch);

        $this->assertSame(Collegegeldtermijnen::ACHTERSTALLIG, $termijnen[0]['status']);
        $this->assertSame(Collegegeldtermijnen::ACHTERSTALLIG, $termijnen[1]['status']);
        $this->assertSame(Collegegeldtermijnen::OPEN, $termijnen[2]['status']);
        $this->assertSame(1600.0, Collegegeldtermijnen::achterstallig($insch));
    }

    public function test_betaalde_termijn_is_geen_achterstand(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-12-10'));
        $insch = $this->inschrijving();

        Betaling::create(['student_id' => $insch->student_id, 'inschrijving_id' => $insch->id,
            'termijn' => 1, 'bedrag' => 800, 'datum' => '2025-09-05']);
        Betaling::create(['student_id' => $insch->student_id, 'inschrijving_id' => $insch->id,
            'termijn' => 2, 'bedrag' => 800, 'datum' => '2025-11-02']);

        $termijnen = Collegegeldtermijnen::voor($insch->fresh()->load('betalingen'));

        $this->assertSame(Collegegeldtermijnen::BETAALD, $termijnen[0]['status']);
        $this->assertSame(Collegegeldtermijnen::BETAALD, $termijnen[1]['status']);
        $this->assertSame(0.0, Collegegeldtermijnen::achterstallig($insch->fresh()->load('betalingen')));
    }

    public function test_deelbetaling_op_vervallen_termijn_blijft_achterstallig(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-12-10'));
        $insch = $this->inschrijving();

        Betaling::create(['student_id' => $insch->student_id, 'inschrijving_id' => $insch->id,
            'termijn' => 1, 'bedrag' => 800, 'datum' => '2025-09-05']);
        Betaling::create(['student_id' => $insch->student_id, 'inschrijving_id' => $insch->id,
            'termijn' => 2, 'bedrag' => 300, 'datum' => '2025-11-02']);

        $termijnen = Collegegeldtermijnen::voor($insch->fresh()->load('betalingen'));

        $this->assertSame(Collegegeldtermijnen::ACHTERSTALLIG, $termijnen[1]['status']);
        $this->assertSame(500.0, $termijnen[1]['open']);
        $this->assertSame(500.0, Collegegeldtermijnen::achterstallig($insch->fresh()->load('betalingen')));
    }

    public function test_deelbetaling_op_toekomstige_termijn_heet_deels_betaald(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-09-15'));
        $insch = $this->inschrijving();

        Betaling::create(['student_id' => $insch->student_id, 'inschrijving_id' => $insch->id,
            'termijn' => 3, 'bedrag' => 100, 'datum' => '2025-09-15']);

        $termijnen = Collegegeldtermijnen::voor($insch->fresh()->load('betalingen'));

        $this->assertSame(Collegegeldtermijnen::DEELS, $termijnen[2]['status']);
    }

    public function test_betaling_zonder_termijn_gaat_naar_de_oudste_openstaande(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-12-10'));
        $insch = $this->inschrijving();

        // € 1.000 zonder termijnnummer: 800 naar termijn 1, 200 naar termijn 2.
        Betaling::create(['student_id' => $insch->student_id, 'inschrijving_id' => $insch->id,
            'termijn' => null, 'bedrag' => 1000, 'datum' => '2025-09-05']);

        $termijnen = Collegegeldtermijnen::voor($insch->fresh()->load('betalingen'));

        $this->assertSame(800.0, $termijnen[0]['betaald']);
        $this->assertSame(Collegegeldtermijnen::BETAALD, $termijnen[0]['status']);
        $this->assertSame(200.0, $termijnen[1]['betaald']);
        $this->assertSame(600.0, $termijnen[1]['open']);
    }

    public function test_uitschrijving_laat_latere_termijnen_vervallen_en_stelt_de_laatste_bij(): void
    {
        // Uitschrijving per 31 januari = 5 maanden (sep t/m jan) = € 1.666,67.
        Carbon::setTestNow(Carbon::parse('2026-02-10'));
        $insch = $this->inschrijving();
        $insch->update(['status' => InschrijvingStatus::Uitgeschreven, 'uitschrijfdatum' => '2026-01-31']);

        $termijnen = Collegegeldtermijnen::voor($insch->fresh()->load('betalingen'));

        $this->assertSame(800.0, $termijnen[0]['bedrag']);
        $this->assertSame(800.0, $termijnen[1]['bedrag']);
        $this->assertSame(66.67, $termijnen[2]['bedrag']);   // bijgesteld
        $this->assertTrue($termijnen[3]['vervallen']);
        $this->assertTrue($termijnen[4]['vervallen']);
        $this->assertSame(1666.67, round($termijnen->sum('bedrag'), 2));
        $this->assertSame(1666.67, Collegegeldtermijnen::totaal($insch->fresh()->load('betalingen')));
    }

    public function test_vroege_uitschrijving_verlaagt_ook_de_eerste_termijn(): void
    {
        // Uitschrijving per 30 september = 1 maand = € 333,33; alleen termijn 1 geldt.
        Carbon::setTestNow(Carbon::parse('2025-10-10'));
        $insch = $this->inschrijving();
        $insch->update(['status' => InschrijvingStatus::Uitgeschreven, 'uitschrijfdatum' => '2025-09-30']);

        $termijnen = Collegegeldtermijnen::voor($insch->fresh()->load('betalingen'));

        $this->assertSame(333.33, $termijnen[0]['bedrag']);
        $this->assertTrue($termijnen[1]['vervallen']);
        $this->assertSame(333.33, round($termijnen->sum('bedrag'), 2));
    }

    public function test_aangemelde_student_heeft_nog_geen_termijnen(): void
    {
        $insch = $this->inschrijving();
        $insch->update(['status' => InschrijvingStatus::Aangemeld]);

        $this->assertTrue(Collegegeldtermijnen::voor($insch->fresh()->load('betalingen'))->isEmpty());
    }

    public function test_zonder_tarief_geen_termijnen(): void
    {
        CollegegeldTarief::query()->delete();

        $this->assertTrue(Collegegeldtermijnen::voor($this->inschrijving())->isEmpty());
    }

    public function test_achterstand_ontstaat_pas_bij_een_vervallen_termijn(): void
    {
        // Half september: alleen termijn 1 is vervallen. Betaalt de student die,
        // dan is er GEEN achterstand, ook al staat er nog € 3.200 open.
        Carbon::setTestNow(Carbon::parse('2025-09-20'));
        $insch = $this->inschrijving();
        Betaling::create(['student_id' => $insch->student_id, 'inschrijving_id' => $insch->id,
            'termijn' => 1, 'bedrag' => 800, 'datum' => '2025-09-05']);

        $status = Collegegeldstatus::voor($insch->student->fresh());

        $this->assertFalse($status['achterstand']);
        $this->assertSame(0.0, $status['achterstallig']);
        $this->assertSame(3200.0, $status['openstaand']);
        $this->assertSame(4000.0, $status['verschuldigd']);
    }

    /**
     * De boek-knop stuurt alle velden als STRING, zoals een browser dat doet.
     * Met integers zou een strikte vergelijking in de controller verborgen blijven.
     */
    public function test_financien_boekt_een_termijn_met_een_klik(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-12-10'));
        $insch = $this->inschrijving();

        $this->actingAs(User::where('rol', Rol::Financien)->first())
            ->post(route('financien.betaling', $insch->student), [
                'inschrijving_id' => (string) $insch->id, 'termijn' => '1',
                'bedrag' => '800.00', 'datum' => '2025-09-05',
            ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('betalingen', [
            'inschrijving_id' => $insch->id, 'termijn' => 1, 'bedrag' => 800.00,
        ]);
    }

    public function test_betaling_zonder_termijnkeuze_wordt_automatisch_toegerekend(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-12-10'));
        $insch = $this->inschrijving();

        // Het select-veld stuurt een lege string wanneer 'automatisch' is gekozen.
        $this->actingAs(User::where('rol', Rol::Financien)->first())
            ->post(route('financien.betaling', $insch->student), [
                'inschrijving_id' => (string) $insch->id, 'termijn' => '',
                'bedrag' => '800.00', 'datum' => '2025-09-05',
            ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('betalingen', ['inschrijving_id' => $insch->id, 'termijn' => null]);

        $termijnen = Collegegeldtermijnen::voor($insch->fresh()->load('betalingen'));
        $this->assertSame(Collegegeldtermijnen::BETAALD, $termijnen[0]['status']);
    }

    public function test_boeken_op_een_vervallen_termijn_wordt_geweigerd(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-10'));
        $insch = $this->inschrijving();
        $insch->update(['status' => InschrijvingStatus::Uitgeschreven, 'uitschrijfdatum' => '2026-01-31']);

        $this->actingAs(User::where('rol', Rol::Financien)->first())
            ->post(route('financien.betaling', $insch->student), [
                'inschrijving_id' => (string) $insch->id, 'termijn' => '5',
                'bedrag' => '800.00', 'datum' => '2026-05-01',
            ])->assertSessionHasErrors('termijn');

        $this->assertDatabaseMissing('betalingen', ['inschrijving_id' => $insch->id, 'termijn' => 5]);
    }

    public function test_studentenzaken_wijzigt_de_betaalregeling_en_dit_wordt_gelogd(): void
    {
        $insch = $this->inschrijving();

        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->post(route('inschrijving.betaalregeling', $insch), ['betaalregeling' => 'volledig'])
            ->assertRedirect();

        $this->assertSame(Betaalregeling::Volledig, $insch->fresh()->betaalregeling);
        $this->assertDatabaseHas('audit_logs', ['veld' => 'betaalregeling', 'actie' => 'wijziging']);
    }

    public function test_alleen_studentenzaken_en_beheer_wijzigen_de_betaalregeling(): void
    {
        $insch = $this->inschrijving();
        $body = ['betaalregeling' => 'volledig'];

        foreach ([Rol::Studentenzaken, Rol::Beheerder] as $rol) {
            $this->actingAs(User::where('rol', $rol)->first())
                ->post(route('inschrijving.betaalregeling', $insch), $body)->assertRedirect();
        }

        // De Financiële Administratie boekt betalingen, maar maakt geen afspraken.
        foreach ([Rol::Financien, Rol::Directie, Rol::Docent] as $rol) {
            $this->actingAs(User::where('rol', $rol)->first())
                ->post(route('inschrijving.betaalregeling', $insch), $body)->assertForbidden();
        }
    }
}
