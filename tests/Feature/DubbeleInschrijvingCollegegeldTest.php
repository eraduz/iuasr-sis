<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Betaling;
use App\Models\CollegegeldTarief;
use App\Models\Inschrijving;
use App\Models\Opleiding;
use App\Models\Periode;
use App\Models\Student;
use App\Models\User;
use App\Support\Collegegeldstatus;
use App\Support\Collegegeldtermijnen;
use Carbon\Carbon;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischVakSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Collegegeld is per STUDIEJAAR eenmaal verschuldigd. Bij een dubbele
 * inschrijving hangt het termijnschema aan de maatgevende inschrijving (het
 * hoogste jaartarief) en tellen betalingen op beide inschrijvingen mee.
 */
class DubbeleInschrijvingCollegegeldTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;
    private Inschrijving $duur;    // ISLTH, € 4.000 — maatgevend
    private Inschrijving $goedkoop; // PABO,  € 2.530

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, SynthetischVakSeeder::class, GebruikerSeeder::class]);
        Carbon::setTestNow('2025-12-10'); // september + november zijn vervallen

        $periode = Periode::where('actief', true)->firstOrFail();
        $islth = Opleiding::where('code', 'ISLTH')->value('id');
        $pabo = Opleiding::where('code', 'PABO')->value('id');

        CollegegeldTarief::create(['periode_id' => $periode->id, 'opleiding_id' => $islth, 'bedrag' => 4000, 'aantal_termijnen' => 5]);
        CollegegeldTarief::create(['periode_id' => $periode->id, 'opleiding_id' => $pabo, 'bedrag' => 2530, 'aantal_termijnen' => 5]);

        $this->student = Student::create([
            'studentnummer' => '261900', 'voornaam' => 'Dubbel', 'achternaam' => 'Ingeschreven',
            'geboortedatum' => '2000-01-01',
        ]);

        $maak = fn (int $opleidingId) => Inschrijving::create([
            'student_id' => $this->student->id, 'opleiding_id' => $opleidingId,
            'periode_id' => $periode->id, 'leerjaar' => 1, 'status' => 'actief',
            'inschrijfdatum' => '2025-09-01',
        ]);

        // Bewust de goedkope opleiding EERST aanmaken: het maatgevende is het
        // hoogste tarief, niet de eerste of laatste inschrijving.
        $this->goedkoop = $maak($pabo);
        $this->duur = $maak($islth);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function geldstatus(): array
    {
        return Collegegeldstatus::voor($this->student->fresh());
    }

    public function test_de_opleiding_met_het_hoogste_tarief_is_maatgevend(): void
    {
        $this->assertTrue(Collegegeldtermijnen::isMaatgevend($this->duur));
        $this->assertFalse(Collegegeldtermijnen::isMaatgevend($this->goedkoop));

        $this->assertSame($this->duur->id, Collegegeldtermijnen::maatgevende($this->goedkoop)->id);
        $this->assertNull(Collegegeldtermijnen::verrekendBij($this->duur));
        $this->assertSame($this->duur->id, Collegegeldtermijnen::verrekendBij($this->goedkoop)->id);
    }

    public function test_alleen_de_maatgevende_inschrijving_heeft_termijnen(): void
    {
        $this->assertCount(5, Collegegeldtermijnen::voor($this->duur));
        $this->assertTrue(Collegegeldtermijnen::voor($this->goedkoop)->isEmpty());

        $this->assertSame(4000.0, Collegegeldtermijnen::totaal($this->duur));
        $this->assertSame(0.0, Collegegeldtermijnen::totaal($this->goedkoop));
    }

    public function test_collegegeld_wordt_niet_verdubbeld(): void
    {
        $this->assertSame(4000.0, $this->geldstatus()['verschuldigd']);
    }

    /** De kern: geld dat op de ándere inschrijving is geboekt telt gewoon mee. */
    public function test_betaling_op_de_niet_maatgevende_inschrijving_telt_mee(): void
    {
        $this->assertSame(1600.0, $this->geldstatus()['achterstallig']); // sep + nov

        Betaling::create([
            'student_id' => $this->student->id, 'inschrijving_id' => $this->goedkoop->id,
            'bedrag' => 1600, 'datum' => '2025-09-05',
        ]);

        $status = $this->geldstatus();
        $this->assertSame(0.0, $status['achterstallig']);
        $this->assertFalse($status['achterstand']);

        $termijnen = Collegegeldtermijnen::voor($this->duur->fresh());
        $this->assertSame(Collegegeldtermijnen::BETAALD, $termijnen[0]['status']);
        $this->assertSame(Collegegeldtermijnen::BETAALD, $termijnen[1]['status']);
    }

    public function test_student_die_alles_betaalde_wordt_niet_geblokkeerd(): void
    {
        Betaling::create([
            'student_id' => $this->student->id, 'inschrijving_id' => $this->goedkoop->id,
            'bedrag' => 4000, 'datum' => '2025-09-05',
        ]);

        $this->assertFalse(Collegegeldstatus::heeftAchterstand($this->student->fresh()));
    }

    public function test_een_termijn_op_de_niet_maatgevende_inschrijving_wordt_op_de_maatgevende_geboekt(): void
    {
        $this->actingAs(User::where('rol', Rol::Financien)->first())
            ->post(route('financien.betaling', $this->student), [
                'inschrijving_id' => (string) $this->goedkoop->id, 'termijn' => '1',
                'bedrag' => '800.00', 'datum' => '2025-09-05',
            ])->assertSessionHasNoErrors()->assertRedirect();

        // De betaling landt op de inschrijving waar de facturen aan hangen.
        $this->assertDatabaseHas('betalingen', ['inschrijving_id' => $this->duur->id, 'termijn' => 1]);
        $this->assertDatabaseMissing('betalingen', ['inschrijving_id' => $this->goedkoop->id]);
    }

    public function test_financienscherm_markeert_de_niet_maatgevende_inschrijving(): void
    {
        $this->actingAs(User::where('rol', Rol::Financien)->first())
            ->get(route('financien.student', $this->student))
            ->assertOk()
            ->assertSee('Geen collegegeld verschuldigd voor deze inschrijving.')
            ->assertSee('maatgevend · dubbele inschrijving');
    }

    public function test_studentdossier_wijst_naar_de_maatgevende_opleiding(): void
    {
        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->get(route('studenten.show', $this->student))
            ->assertOk()
            ->assertSee('Dubbele inschrijving.')
            ->assertSee('het hoogste jaartarief is maatgevend');
    }

    /** Zonder dubbele inschrijving verandert er niets aan het bestaande gedrag. */
    public function test_enkele_inschrijving_blijft_ongewijzigd(): void
    {
        $this->goedkoop->delete();

        $this->assertTrue(Collegegeldtermijnen::isMaatgevend($this->duur->fresh()));
        $this->assertCount(5, Collegegeldtermijnen::voor($this->duur->fresh()));
        $this->assertSame(4000.0, $this->geldstatus()['verschuldigd']);
    }
}
