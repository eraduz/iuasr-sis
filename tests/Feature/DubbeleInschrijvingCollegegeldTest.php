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
 * Collegegeld wordt PER OPLEIDING geheven (opdrachtgever, 2026-07-10). Elke
 * inschrijving heeft een eigen termijnschema en een eigen rekening; op de tweede
 * opleiding legt Studentenzaken een korting vast.
 */
class DubbeleInschrijvingCollegegeldTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;
    private Inschrijving $eerste;  // ISLTH, € 4.000, geen korting
    private Inschrijving $tweede;  // PABO,  € 2.530, 50% korting

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

        $maak = fn (int $opleidingId, float $korting = 0, ?string $reden = null) => Inschrijving::create([
            'student_id' => $this->student->id, 'opleiding_id' => $opleidingId,
            'periode_id' => $periode->id, 'leerjaar' => 1, 'status' => 'actief',
            'inschrijfdatum' => '2025-09-01',
            'korting_percentage' => $korting, 'korting_reden' => $reden,
        ]);

        $this->eerste = $maak($islth);
        $this->tweede = $maak($pabo, 50, 'Tweede opleiding');
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

    public function test_elke_opleiding_heeft_een_eigen_termijnschema(): void
    {
        $this->assertCount(5, Collegegeldtermijnen::voor($this->eerste));
        $this->assertCount(5, Collegegeldtermijnen::voor($this->tweede));
    }

    public function test_korting_verlaagt_het_jaarbedrag_van_die_opleiding(): void
    {
        $this->assertSame(4000.0, Collegegeldstatus::tarief($this->eerste));
        $this->assertSame(4000.0, Collegegeldstatus::jaarbedrag($this->eerste));

        // PABO: € 2.530 minus 50% = € 1.265.
        $this->assertSame(2530.0, Collegegeldstatus::tarief($this->tweede));
        $this->assertSame(1265.0, Collegegeldstatus::jaarbedrag($this->tweede));
        $this->assertSame(1265.0, Collegegeldtermijnen::kortingsbedrag($this->tweede));
        $this->assertTrue(Collegegeldtermijnen::heeftKorting($this->tweede));
        $this->assertFalse(Collegegeldtermijnen::heeftKorting($this->eerste));
    }

    public function test_termijnen_volgen_het_bedrag_na_korting(): void
    {
        $termijnen = Collegegeldtermijnen::voor($this->tweede);

        $this->assertSame(253.0, $termijnen[0]['bedrag']);            // 1265 / 5
        $this->assertSame(1265.0, round($termijnen->sum('bedrag'), 2));
    }

    /** De student betaalt voor BEIDE opleidingen; niet meer één keer per studiejaar. */
    public function test_collegegeld_wordt_per_opleiding_opgeteld(): void
    {
        $this->assertSame(5265.0, $this->geldstatus()['verschuldigd']); // 4000 + 1265
        $this->assertSame(5265.0, $this->geldstatus()['jaarbedrag']);
    }

    public function test_betaling_hoort_bij_de_opleiding_waarop_zij_is_geboekt(): void
    {
        // Volledig betalen op ISLTH raakt de rekening van PABO niet.
        Betaling::create([
            'student_id' => $this->student->id, 'inschrijving_id' => $this->eerste->id,
            'bedrag' => 4000, 'datum' => '2025-09-05',
        ]);

        $this->assertSame(0.0, Collegegeldtermijnen::achterstallig($this->eerste->fresh()->load('betalingen')));
        $this->assertSame(506.0, Collegegeldtermijnen::achterstallig($this->tweede->fresh()->load('betalingen'))); // 2 x 253
        $this->assertTrue($this->geldstatus()['achterstand']);
    }

    /** Een achterstand bij één opleiding blokkeert herinschrijven en verklaringen. */
    public function test_achterstand_bij_een_van_beide_blokkeert(): void
    {
        Betaling::create([
            'student_id' => $this->student->id, 'inschrijving_id' => $this->eerste->id,
            'bedrag' => 4000, 'datum' => '2025-09-05',
        ]);

        $this->assertTrue(Collegegeldstatus::heeftAchterstand($this->student->fresh()));

        // Ook PABO voldoen -> geen blokkade meer.
        Betaling::create([
            'student_id' => $this->student->id, 'inschrijving_id' => $this->tweede->id,
            'bedrag' => 1265, 'datum' => '2025-09-05',
        ]);
        $this->assertFalse(Collegegeldstatus::heeftAchterstand($this->student->fresh()));
    }

    public function test_honderd_procent_korting_geeft_geen_facturen(): void
    {
        $this->tweede->update(['korting_percentage' => 100, 'korting_reden' => 'Volledige vrijstelling']);

        $this->assertTrue(Collegegeldtermijnen::voor($this->tweede->fresh())->isEmpty());
        $this->assertSame(4000.0, $this->geldstatus()['verschuldigd']);
    }

    public function test_studentenzaken_legt_korting_vast_met_reden_en_dit_wordt_gelogd(): void
    {
        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->post(route('inschrijving.korting', $this->eerste), [
                'korting_percentage' => '25', 'korting_reden' => 'Medewerkerskorting',
            ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(25.0, $this->eerste->fresh()->korting_percentage);
        $this->assertSame(3000.0, Collegegeldstatus::jaarbedrag($this->eerste->fresh()));
        $this->assertDatabaseHas('audit_logs', ['veld' => 'korting_collegegeld', 'actie' => 'wijziging']);
    }

    public function test_korting_zonder_reden_wordt_geweigerd(): void
    {
        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->post(route('inschrijving.korting', $this->eerste), ['korting_percentage' => '25'])
            ->assertSessionHasErrors('korting_reden');

        $this->assertSame(0.0, $this->eerste->fresh()->korting_percentage);
    }

    public function test_korting_intrekken_mag_zonder_reden(): void
    {
        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->post(route('inschrijving.korting', $this->tweede), ['korting_percentage' => '0'])
            ->assertSessionHasNoErrors();

        $this->tweede->refresh();
        $this->assertSame(0.0, $this->tweede->korting_percentage);
        $this->assertNull($this->tweede->korting_reden);
        $this->assertSame(2530.0, Collegegeldstatus::jaarbedrag($this->tweede));
    }

    public function test_alleen_studentenzaken_en_beheer_stellen_korting_in(): void
    {
        $body = ['korting_percentage' => '10', 'korting_reden' => 'Test'];

        foreach ([Rol::Studentenzaken, Rol::Beheerder] as $rol) {
            $this->actingAs(User::where('rol', $rol)->first())
                ->post(route('inschrijving.korting', $this->eerste), $body)->assertRedirect();
        }

        // De Financiële Administratie boekt betalingen, maar verleent geen korting.
        foreach ([Rol::Financien, Rol::Directie, Rol::Docent] as $rol) {
            $this->actingAs(User::where('rol', $rol)->first())
                ->post(route('inschrijving.korting', $this->eerste), $body)->assertForbidden();
        }
    }

    public function test_financienscherm_toont_beide_opleidingen_met_korting(): void
    {
        $this->actingAs(User::where('rol', Rol::Financien)->first())
            ->get(route('financien.student', $this->student))
            ->assertOk()
            ->assertSee('korting 50%')
            ->assertSee('Tweede opleiding')
            ->assertSee('ISLTH')
            ->assertSee('PABO');
    }

    public function test_boeken_op_de_tweede_opleiding_landt_daar_ook(): void
    {
        $this->actingAs(User::where('rol', Rol::Financien)->first())
            ->post(route('financien.betaling', $this->student), [
                'inschrijving_id' => (string) $this->tweede->id, 'termijn' => '1',
                'bedrag' => '253.00', 'datum' => '2025-09-05',
            ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('betalingen', ['inschrijving_id' => $this->tweede->id, 'termijn' => 1]);
        $this->assertDatabaseMissing('betalingen', ['inschrijving_id' => $this->eerste->id]);
    }
}
