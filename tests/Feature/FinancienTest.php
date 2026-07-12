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
use Illuminate\Http\UploadedFile;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischVakSeeder;
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
        $this->seed([ReferentieSeeder::class, SynthetischVakSeeder::class, GebruikerSeeder::class, SynthetischeStudentSeeder::class]);
        $this->sz = User::where('rol', Rol::Studentenzaken)->first();
        $this->fin = User::where('rol', Rol::Financien)->first();
        // Vast peilmoment binnen het studiejaar (jan 2026 = 5e maand) voor
        // deterministische pro rata-berekeningen. Ná de 24e, zodat de
        // januari-termijn (vervaldatum de 24e) is vervallen.
        Carbon::setTestNow('2026-01-25');
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

        // Verschuldigd is het VOLLEDIGE jaarbedrag; de achterstand betreft alleen
        // de termijnen die al vervallen zijn. Op 25 januari zijn dat september,
        // november en januari: 3 x 506,00.
        $status = Collegegeldstatus::voor($student->fresh());
        $this->assertEqualsWithDelta(2530.00, $status['verschuldigd'], 0.01);
        $this->assertEqualsWithDelta(1518.00, $status['achterstallig'], 0.01);

        // Deelbetaling op de vervallen termijnen -> nog steeds achterstand.
        $this->actingAs($this->fin)->post(route('financien.betaling', $student), [
            'inschrijving_id' => $insch->id, 'bedrag' => '500', 'datum' => '2026-01-15',
        ])->assertRedirect(route('financien.student', $student));

        $status = Collegegeldstatus::voor($student->fresh());
        $this->assertTrue($status['achterstand']);
        $this->assertEqualsWithDelta(1018.00, $status['achterstallig'], 0.01);

        // De vervallen termijnen volledig voldoen -> geen achterstand meer,
        // ook al staan de termijnen van maart en mei nog open.
        $this->actingAs($this->fin)->post(route('financien.betaling', $student), [
            'inschrijving_id' => $insch->id, 'bedrag' => '1018.00', 'datum' => '2026-01-20',
        ]);

        $status = Collegegeldstatus::voor($student->fresh());
        $this->assertFalse($status['achterstand']);
        $this->assertSame(0.0, $status['achterstallig']);
        $this->assertEqualsWithDelta(1012.00, $status['openstaand'], 0.01); // maart + mei
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

    public function test_hele_jaarbedrag_vooruit_betalen_geeft_geen_achterstand_en_geen_tegoed(): void
    {
        $this->tarief(4000);
        $student = Student::where('studentnummer', '261011')->first(); // actief
        $insch = $student->inschrijvingen()->first();

        // Volledig jaarbedrag in september voldaan: alle termijnen zijn betaald.
        $student->betalingen()->create([
            'inschrijving_id' => $insch->id, 'bedrag' => 4000, 'datum' => '2025-09-15',
        ]);

        $status = Collegegeldstatus::voor($student->fresh());
        $this->assertFalse($status['achterstand']);
        $this->assertSame(0.0, $status['openstaand']);
        // Vooruit betalen van het jaarbedrag is geen tegoed: het is gewoon voldaan.
        $this->assertSame(0.0, $status['vooruitbetaald']);
        $this->assertSame(0.0, $status['terugbetaling']);
    }

    public function test_actieve_student_die_teveel_betaalt_krijgt_een_tegoed_geen_terugbetaling(): void
    {
        $this->tarief(4000);
        $student = Student::where('studentnummer', '261011')->first(); // actief
        $insch = $student->inschrijvingen()->first();

        // Meer dan het jaarbedrag betaald terwijl de student nog is ingeschreven.
        $student->betalingen()->create([
            'inschrijving_id' => $insch->id, 'bedrag' => 4500, 'datum' => '2025-09-15',
        ]);

        $status = Collegegeldstatus::voor($student->fresh());
        $this->assertSame(0.0, $status['terugbetaling']); // geen terugbetaling: nog ingeschreven
        $this->assertEqualsWithDelta(500.0, $status['vooruitbetaald'], 0.01); // wel een tegoed
        $this->assertFalse($status['achterstand']);
    }

    /**
     * BELEIDSWIJZIGING (2026-07-10): collegegeld wordt PER OPLEIDING geheven.
     * Een tweede opleiding kost dus extra; Studentenzaken legt daar doorgaans
     * een korting op vast. Zie DubbeleInschrijvingCollegegeldTest.
     */
    public function test_collegegeld_wordt_per_opleiding_geheven_bij_dubbele_inschrijving(): void
    {
        $this->tarief(4000);
        $student = Student::where('studentnummer', '261011')->first(); // actief ISLTH
        $insch = $student->inschrijvingen()->first();

        $enkel = Collegegeldstatus::voor($student->fresh())['verschuldigd'];
        $this->assertGreaterThan(0, $enkel);

        // Tweede opleiding in HETZELFDE studiejaar (dubbele inschrijving).
        Inschrijving::create([
            'student_id' => $student->id,
            'opleiding_id' => Opleiding::where('code', 'PABO')->value('id'),
            'periode_id' => $insch->periode_id,
            'leerjaar' => 1, 'status' => 'actief', 'inschrijfdatum' => $insch->inschrijfdatum,
        ]);

        // Elke opleiding heeft een eigen rekening: het bedrag telt op.
        $dubbel = Collegegeldstatus::voor($student->fresh())['verschuldigd'];
        $this->assertEqualsWithDelta($enkel * 2, $dubbel, 0.01);

        // Met 50% korting op de tweede opleiding: anderhalf keer het tarief.
        $student->inschrijvingen()->latest('id')->first()
            ->update(['korting_percentage' => 50, 'korting_reden' => 'Tweede opleiding']);

        $metKorting = Collegegeldstatus::voor($student->fresh())['verschuldigd'];
        $this->assertEqualsWithDelta($enkel * 1.5, $metKorting, 0.01);
    }

    public function test_overzicht_toont_vooruitbetaalde_student(): void
    {
        $this->tarief(4000);
        $student = Student::where('studentnummer', '261011')->first(); // actief
        $insch = $student->inschrijvingen()->first();
        $student->betalingen()->create([
            'inschrijving_id' => $insch->id, 'bedrag' => 4500, 'datum' => '2025-09-15',
        ]);

        $this->actingAs($this->fin)->get(route('financien'))
            ->assertOk()
            ->assertSee('Tegoed / vooruitbetaald')
            ->assertSee($student->volledigeNaam());
    }

    public function test_studentenzaken_registreert_geen_betaling(): void
    {
        $this->actingAs($this->sz)->get(route('financien'))->assertForbidden();
        $student = Student::first();
        $this->actingAs($this->sz)->post(route('financien.betaling', $student), [])->assertForbidden();
    }

    public function test_bulk_import_controleert_voor_definitief_opslaan(): void
    {
        $csv = "studentnummer;bedrag;datum;betaalwijze;opmerking\r\n"
            ."261013;1000,00;15-09-2025;overboeking;jaarbetaling\r\n"
            ."261014;500,00;15-09-2025;termijn;\r\n"
            ."999999;100,00;15-09-2025;;\r\n"; // onbekend studentnummer -> overslaan

        $file = UploadedFile::fake()->createWithContent('betalingen.csv', $csv);

        // Stap 1: controle — toont het overzicht, slaat nog NIETS op.
        $this->actingAs($this->fin)->post(route('financien.import.controle'), ['bestand' => $file])
            ->assertOk()
            ->assertSee('261013')
            ->assertSessionHas('import_preview');
        $this->assertSame(0, Betaling::count());

        // Stap 2: bevestigen — slaat de gecontroleerde regels definitief op.
        $this->actingAs($this->fin)->post(route('financien.import'))
            ->assertRedirect(route('financien'))
            ->assertSessionHas('import_resultaat');

        $this->assertSame(2, Betaling::count());
        $this->assertEqualsWithDelta(1000.00, (float) Student::where('studentnummer', '261013')->first()->betalingen()->sum('bedrag'), 0.01);
        $this->assertCount(1, session('import_resultaat')['fouten']);
    }

    /** Bestanden van vóór de termijnmodule (zonder termijnkolom) blijven werken. */
    public function test_bulk_import_accepteert_csv_zonder_termijnkolom(): void
    {
        $csv = "studentnummer;bedrag;datum;betaalwijze;opmerking\r\n"
            ."261013;1000,00;15-09-2025;overboeking;jaarbetaling\r\n";

        $this->actingAs($this->fin)->post(route('financien.import.controle'), [
            'bestand' => UploadedFile::fake()->createWithContent('oud.csv', $csv),
        ])->assertOk();
        $this->actingAs($this->fin)->post(route('financien.import'))->assertRedirect();

        $this->assertDatabaseHas('betalingen', ['bedrag' => 1000.00, 'termijn' => null]);
    }

    public function test_bulk_import_leest_de_termijnkolom(): void
    {
        $this->tarief(4000);
        $csv = "studentnummer;bedrag;termijn;datum;betaalwijze;opmerking\r\n"
            ."261013;800,00;2;03-11-2025;overboeking;termijn november\r\n"
            ."261013;800,00;9;03-11-2025;overboeking;bestaat niet\r\n";

        $this->actingAs($this->fin)->post(route('financien.import.controle'), [
            'bestand' => UploadedFile::fake()->createWithContent('nieuw.csv', $csv),
        ])->assertOk();
        $this->actingAs($this->fin)->post(route('financien.import'))->assertRedirect();

        $this->assertDatabaseHas('betalingen', ['bedrag' => 800.00, 'termijn' => 2]);
        $this->assertSame(1, Betaling::count()); // regel met termijn 9 is overgeslagen
        $this->assertCount(1, session('import_resultaat')['fouten']);
    }

    public function test_bevestigen_zonder_controle_slaat_niets_op(): void
    {
        $this->actingAs($this->fin)->post(route('financien.import'))
            ->assertRedirect(route('financien'))
            ->assertSessionHasErrors('bestand');
        $this->assertSame(0, Betaling::count());
    }

    public function test_import_weigert_niet_csv_bestand(): void
    {
        $file = UploadedFile::fake()->create('betalingen.xlsx', 10);
        $this->actingAs($this->fin)->post(route('financien.import.controle'), ['bestand' => $file])
            ->assertSessionHasErrors('bestand');
        $this->assertSame(0, Betaling::count());
    }

    public function test_studentenzaken_mag_niet_importeren(): void
    {
        $this->actingAs($this->sz)->get(route('financien.import.sjabloon'))->assertForbidden();
        $this->actingAs($this->sz)->post(route('financien.import.controle'), [])->assertForbidden();
        $this->actingAs($this->sz)->post(route('financien.import'), [])->assertForbidden();
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
