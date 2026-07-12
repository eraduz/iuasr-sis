<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Betalingsafspraak;
use App\Models\CollegegeldTarief;
use App\Models\Periode;
use App\Models\Student;
use App\Models\User;
use App\Support\Collegegeldstatus;
use Carbon\Carbon;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischVakSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Een betalingsafspraak heft de blokkades op verklaringen en herinschrijven op.
 * De SCHULD blijft bestaan: `achterstand` blijft true, alleen `geblokkeerd` niet.
 */
class BetalingsafspraakTest extends TestCase
{
    use RefreshDatabase;

    private User $fin;
    private User $sz;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, SynthetischVakSeeder::class,
            GebruikerSeeder::class, SynthetischeStudentSeeder::class]);
        Carbon::setTestNow('2026-01-25'); // sep, nov en jan zijn vervallen (24e)

        CollegegeldTarief::create([
            'periode_id' => Periode::where('actief', true)->value('id'),
            'opleiding_id' => null, 'bedrag' => 2530, 'aantal_termijnen' => 5,
        ]);

        $this->fin = User::where('rol', Rol::Financien)->firstOrFail();
        $this->sz = User::where('rol', Rol::Studentenzaken)->firstOrFail();
        // 261011 heeft niets betaald -> achterstallig.
        $this->student = Student::where('studentnummer', '261011')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function afspraak(array $overschrijf = []): Betalingsafspraak
    {
        return Betalingsafspraak::create(array_merge([
            'student_id' => $this->student->id,
            'geldig_tot' => '2026-03-01',
            'reden' => 'Gespreide betaling',
            'vastgelegd_door_id' => $this->fin->id,
        ], $overschrijf));
    }

    public function test_zonder_afspraak_is_de_student_geblokkeerd(): void
    {
        $status = Collegegeldstatus::voor($this->student);

        $this->assertTrue($status['achterstand']);
        $this->assertTrue($status['geblokkeerd']);
        $this->assertNull($status['afspraak']);
    }

    /** De kern: de schuld blijft, de blokkade vervalt. */
    public function test_lopende_afspraak_heft_de_blokkade_op_maar_niet_de_schuld(): void
    {
        $this->afspraak();

        $status = Collegegeldstatus::voor($this->student->fresh());
        $this->assertTrue($status['achterstand']);          // schuld blijft
        $this->assertGreaterThan(0, $status['achterstallig']);
        $this->assertFalse($status['geblokkeerd']);         // blokkade weg
        $this->assertNotNull($status['afspraak']);
    }

    public function test_verlopen_afspraak_brengt_de_blokkade_terug(): void
    {
        $this->afspraak(['geldig_tot' => '2026-01-10']); // gisteren verstreken

        $this->assertTrue(Collegegeldstatus::isGeblokkeerd($this->student->fresh()));
    }

    public function test_ingetrokken_afspraak_brengt_de_blokkade_terug(): void
    {
        $afspraak = $this->afspraak();
        $this->assertFalse(Collegegeldstatus::isGeblokkeerd($this->student->fresh()));

        $afspraak->update(['ingetrokken_op' => now(), 'ingetrokken_door_id' => $this->fin->id]);

        $this->assertTrue(Collegegeldstatus::isGeblokkeerd($this->student->fresh()));
    }

    public function test_studentenzaken_kan_weer_een_verklaring_afgeven(): void
    {
        // Zonder afspraak: geblokkeerd.
        $this->actingAs($this->sz)
            ->get(route('verklaringen', ['student' => $this->student->id, 'type' => 'studentbewijs']))
            ->assertOk()->assertSee('Verklaring geblokkeerd');

        $this->afspraak();

        // Met afspraak: de verklaring wordt gebouwd en de uitgifte gelogd.
        $this->actingAs($this->sz)
            ->get(route('verklaringen', ['student' => $this->student->id, 'type' => 'studentbewijs']))
            ->assertOk()->assertDontSee('Verklaring geblokkeerd');

        $this->assertDatabaseHas('audit_logs', ['onderwerp_id' => $this->student->id, 'veld' => 'verklaring']);
    }

    public function test_herinschrijven_is_weer_mogelijk_met_een_afspraak(): void
    {
        $aantalVoor = $this->student->inschrijvingen()->count();
        $body = [
            'opleiding_id' => $this->student->inschrijvingen()->value('opleiding_id'),
            'periode_id' => Periode::where('code', '2026-2027')->value('id'),
            'leerjaar' => 2,
            'inschrijfdatum' => '2026-09-01',
        ];

        // Zonder afspraak wordt de herinschrijving geweigerd.
        $this->actingAs($this->sz)->post(route('herinschrijven.store', $this->student), $body)
            ->assertRedirect(route('studenten.show', $this->student));
        $this->assertSame($aantalVoor, $this->student->inschrijvingen()->count());

        $this->afspraak();

        $this->actingAs($this->sz)->post(route('herinschrijven.store', $this->student), $body)
            ->assertSessionHasNoErrors();
        $this->assertSame($aantalVoor + 1, $this->student->fresh()->inschrijvingen()->count());
    }

    public function test_financien_legt_een_afspraak_vast_en_dit_wordt_gelogd(): void
    {
        $this->actingAs($this->fin)->post(route('financien.afspraak', $this->student), [
            'geldig_tot' => '2026-03-01', 'reden' => 'Betaalt in twee delen',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('betalingsafspraken', [
            'student_id' => $this->student->id, 'reden' => 'Betaalt in twee delen',
            'vastgelegd_door_id' => $this->fin->id, 'ingetrokken_op' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', ['veld' => 'betalingsafspraak', 'actie' => 'aanmaak']);
    }

    public function test_einddatum_moet_in_de_toekomst_liggen(): void
    {
        $this->actingAs($this->fin)->post(route('financien.afspraak', $this->student), [
            'geldig_tot' => '2026-01-10', 'reden' => 'Te laat',
        ])->assertSessionHasErrors('geldig_tot');

        $this->assertDatabaseCount('betalingsafspraken', 0);
    }

    public function test_reden_is_verplicht(): void
    {
        $this->actingAs($this->fin)->post(route('financien.afspraak', $this->student), [
            'geldig_tot' => '2026-03-01',
        ])->assertSessionHasErrors('reden');
    }

    /** Twee lopende afspraken zou de vraag oproepen welke geldt. */
    public function test_een_nieuwe_afspraak_vervangt_de_lopende(): void
    {
        $oud = $this->afspraak();

        $this->actingAs($this->fin)->post(route('financien.afspraak', $this->student), [
            'geldig_tot' => '2026-04-01', 'reden' => 'Nieuwe afspraak',
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($oud->fresh()->ingetrokken_op);
        $this->assertSame(1, Betalingsafspraak::lopend()->count());
        $this->assertSame('Nieuwe afspraak', Betalingsafspraak::lopendVoor($this->student->fresh())->reden);
    }

    public function test_financien_trekt_een_afspraak_in_en_dit_wordt_gelogd(): void
    {
        $afspraak = $this->afspraak();

        $this->actingAs($this->fin)
            ->post(route('financien.afspraak.intrekken', [$this->student, $afspraak]))
            ->assertRedirect();

        $this->assertNotNull($afspraak->fresh()->ingetrokken_op);
        $this->assertSame($this->fin->id, $afspraak->fresh()->ingetrokken_door_id);
        $this->assertTrue(Collegegeldstatus::isGeblokkeerd($this->student->fresh()));
        $this->assertDatabaseHas('audit_logs', ['veld' => 'betalingsafspraak', 'actie' => 'wijziging']);
    }

    /** Studentenzaken mag haar eigen blokkade niet opheffen. */
    public function test_alleen_financien_en_beheer_leggen_een_afspraak_vast(): void
    {
        $body = ['geldig_tot' => '2026-03-01', 'reden' => 'Test'];

        foreach ([Rol::Financien, Rol::Beheerder] as $rol) {
            $this->actingAs(User::where('rol', $rol)->first())
                ->post(route('financien.afspraak', $this->student), $body)->assertRedirect();
        }

        foreach ([Rol::Studentenzaken, Rol::Directie, Rol::Docent, Rol::Examencommissie] as $rol) {
            $this->actingAs(User::where('rol', $rol)->first())
                ->post(route('financien.afspraak', $this->student), $body)->assertForbidden();
        }
    }

    public function test_afspraak_van_een_andere_student_kan_niet_worden_ingetrokken(): void
    {
        $afspraak = $this->afspraak();
        $andere = Student::where('id', '!=', $this->student->id)->firstOrFail();

        $this->actingAs($this->fin)
            ->post(route('financien.afspraak.intrekken', [$andere, $afspraak]))->assertNotFound();

        $this->assertNull($afspraak->fresh()->ingetrokken_op);
    }

    public function test_dashboard_van_financien_toont_lopende_afspraken(): void
    {
        $this->afspraak(['reden' => 'Gespreide betaling in twee delen']);

        $this->actingAs($this->fin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Lopende betalingsafspraken')
            ->assertSee('Gespreide betaling in twee delen');
    }

    public function test_studentdossier_toont_de_afspraak_naast_de_schuld(): void
    {
        $this->afspraak();

        $this->actingAs($this->sz)->get(route('studenten.show', $this->student))
            ->assertOk()
            ->assertSee('Betalingsachterstand')          // de schuld blijft zichtbaar
            ->assertSee('betalingsafspraak tot 01-03-2026')
            ->assertSee('tijdelijk opgeheven');
    }
}
