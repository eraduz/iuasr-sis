<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\CollegegeldTarief;
use App\Models\Periode;
use App\Models\Student;
use App\Models\User;
use App\Support\Collegegeldstatus;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancienTest extends TestCase
{
    use RefreshDatabase;

    private User $sz;
    private User $fin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, GebruikerSeeder::class, SynthetischeStudentSeeder::class]);
        $this->sz = User::where('rol', Rol::Studentenzaken)->first();
        $this->fin = User::where('rol', Rol::Financien)->first();
        // Vast peilmoment binnen het studiejaar (jan 2026 = 5e maand) voor
        // deterministische pro rata-berekeningen.
        Carbon::setTestNow('2026-01-15');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function tarief(float $bedrag = 2530): CollegegeldTarief
    {
        return CollegegeldTarief::create([
            'periode_id' => Periode::where('actief', true)->value('id'),
            'opleiding_id' => null,
            'bedrag' => $bedrag,
            'aantal_termijnen' => 5,
        ]);
    }

    public function test_studentenadministratie_stelt_collegegeld_in_financien_niet(): void
    {
        $data = [
            'periode_id' => Periode::where('actief', true)->value('id'),
            'opleiding_id' => '',
            'bedrag' => '2530',
            'aantal_termijnen' => '5',
        ];

        $this->actingAs($this->sz)->post(route('collegegeld.store'), $data)->assertRedirect(route('collegegeld'));
        $this->assertDatabaseHas('collegegeld_tarieven', ['bedrag' => 2530.00, 'opleiding_id' => null]);

        // Financiële Administratie stelt géén collegegeld in.
        $this->actingAs($this->fin)->post(route('collegegeld.store'), $data)->assertForbidden();
    }

    public function test_betaling_registreren_bepaalt_de_achterstand(): void
    {
        $this->tarief(2530);
        $student = Student::where('studentnummer', '261011')->first();
        $insch = $student->inschrijvingen()->first();

        // Pro rata t/m januari (5 maanden): 2530 / 12 * 5.
        $verschuldigd = Collegegeldstatus::voor($student->fresh())['verschuldigd'];
        $this->assertEqualsWithDelta(round(2530 / 12 * 5, 2), $verschuldigd, 0.01);

        // Deelbetaling onder het verschuldigde bedrag -> achterstand.
        $this->actingAs($this->fin)->post(route('financien.betaling', $student), [
            'inschrijving_id' => $insch->id, 'bedrag' => '500', 'datum' => '2026-01-15',
        ])->assertRedirect(route('financien.student', $student));

        $status = Collegegeldstatus::voor($student->fresh());
        $this->assertTrue($status['achterstand']);
        $this->assertEqualsWithDelta($verschuldigd - 500, $status['openstaand'], 0.01);

        // Restant voldoen -> geen achterstand meer.
        $this->actingAs($this->fin)->post(route('financien.betaling', $student), [
            'inschrijving_id' => $insch->id, 'bedrag' => (string) round($verschuldigd - 500, 2), 'datum' => '2026-01-20',
        ]);
        $this->assertFalse(Collegegeldstatus::voor($student->fresh())['achterstand']);
    }

    public function test_uitschrijven_rekent_collegegeld_pro_rata(): void
    {
        // 261004 is per 31-12 uitgeschreven -> sept t/m dec = 4 maanden.
        $this->tarief(4000);
        $student = Student::where('studentnummer', '261004')->first();

        $status = Collegegeldstatus::voor($student->fresh());
        $this->assertSame(4, $status['maanden']);
        $this->assertEqualsWithDelta(round(4000 / 12 * 4, 2), $status['verschuldigd'], 0.01);
    }

    public function test_terugbetaling_bij_teveel_betaald(): void
    {
        $this->tarief(4000);
        $student = Student::where('studentnummer', '261004')->first(); // 4 mnd -> 1333,33 verschuldigd
        $insch = $student->inschrijvingen()->first();

        $student->betalingen()->create([
            'inschrijving_id' => $insch->id, 'bedrag' => 2000, 'datum' => '2025-09-15',
        ]);

        $status = Collegegeldstatus::voor($student->fresh());
        $this->assertFalse($status['achterstand']);
        $this->assertEqualsWithDelta(2000 - round(4000 / 12 * 4, 2), $status['terugbetaling'], 0.01);
    }

    public function test_actieve_student_die_vooruit_betaalt_krijgt_geen_terugbetaling(): void
    {
        $this->tarief(4000);
        $student = Student::where('studentnummer', '261011')->first(); // actief
        $insch = $student->inschrijvingen()->first();

        // Volledig jaarbedrag vooruit betaald terwijl de student nog is ingeschreven.
        $student->betalingen()->create([
            'inschrijving_id' => $insch->id, 'bedrag' => 4000, 'datum' => '2025-09-15',
        ]);

        $status = Collegegeldstatus::voor($student->fresh());
        $this->assertSame(0.0, $status['terugbetaling']); // geen terugbetaling: nog ingeschreven
        $this->assertGreaterThan(0, $status['vooruitbetaald']); // maar wel een tegoed
        $this->assertFalse($status['achterstand']);
    }

    public function test_studentenzaken_registreert_geen_betaling(): void
    {
        $this->actingAs($this->sz)->get(route('financien'))->assertForbidden();
        $student = Student::first();
        $this->actingAs($this->sz)->post(route('financien.betaling', $student), [])->assertForbidden();
    }

    public function test_verklaring_wordt_geblokkeerd_bij_achterstand(): void
    {
        $this->tarief(2530); // student heeft niets betaald -> achterstand
        $student = Student::where('studentnummer', '261011')->first();

        $this->actingAs($this->sz)
            ->get(route('verklaringen', ['student' => $student->id, 'type' => 'studentbewijs']))
            ->assertOk()
            ->assertSee('Verklaring geblokkeerd');

        // Geen uitgifte gelogd bij een geblokkeerde verklaring.
        $this->assertDatabaseMissing('audit_logs', ['onderwerp_id' => $student->id, 'veld' => 'verklaring']);
    }

    public function test_herinschrijven_wordt_geblokkeerd_bij_achterstand(): void
    {
        $this->tarief(2530);
        $student = Student::where('studentnummer', '261011')->first();
        $aantalVoor = $student->inschrijvingen()->count();

        $this->actingAs($this->sz)->post(route('herinschrijven.store', $student), [
            'periode_id' => Periode::where('code', '2026-2027')->value('id'),
            'inschrijfdatum' => '2026-09-01',
        ])->assertRedirect(route('studenten.show', $student));

        $this->assertSame($aantalVoor, $student->inschrijvingen()->count()); // geen nieuwe inschrijving
    }

    public function test_studentdossier_toont_schuldwaarschuwing(): void
    {
        $this->tarief(2530);
        $student = Student::where('studentnummer', '261011')->first();

        $this->actingAs($this->sz)->get(route('studenten.show', $student))
            ->assertOk()
            ->assertSee('Betalingsachterstand');
    }

    public function test_financien_ziet_geen_cijfers_of_studentdossiers(): void
    {
        $this->actingAs($this->fin)->get(route('cijferoverzicht'))->assertForbidden();
        $this->actingAs($this->fin)->get(route('studenten.index'))->assertForbidden();
    }
}
