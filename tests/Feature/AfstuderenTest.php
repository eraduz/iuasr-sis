<?php

namespace Tests\Feature;

use App\Enums\InschrijvingStatus;
use App\Enums\Rol;
use App\Models\Inschrijving;
use App\Models\Opleiding;
use App\Models\Periode;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AfstuderenTest extends TestCase
{
    use RefreshDatabase;

    private User $sz;
    private User $examen;
    private User $directie;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferentieSeeder::class);
        $this->sz = User::create(['naam' => 'SZ', 'email' => 'sz@iuasr.test', 'rol' => Rol::Studentenzaken]);
        $this->examen = User::create(['naam' => 'EC', 'email' => 'ec@iuasr.test', 'rol' => Rol::Examencommissie]);
        $this->directie = User::create(['naam' => 'Dir', 'email' => 'dir@iuasr.test', 'rol' => Rol::Directie]);

        $this->student = Student::create(['studentnummer' => '260800', 'voornaam' => 'Test', 'achternaam' => 'Afstudeerder']);
    }

    /** ISLTH heeft nominale_jaren=4; leerjaar bepaalt of het het laatste jaar is. */
    private function inschrijving(int $leerjaar, string $code = 'ISLTH', InschrijvingStatus $status = InschrijvingStatus::Actief): Inschrijving
    {
        return Inschrijving::create([
            'student_id' => $this->student->id,
            'opleiding_id' => Opleiding::where('code', $code)->value('id'),
            'periode_id' => Periode::where('actief', true)->value('id'),
            'leerjaar' => $leerjaar,
            'status' => $status,
            'inschrijfdatum' => '2026-09-01',
        ]);
    }

    public function test_afstuderen_in_laatste_leerjaar_zet_status_en_maakt_alumnus(): void
    {
        $insch = $this->inschrijving(4); // laatste jaar ISLTH

        $this->actingAs($this->sz)->get(route('afstuderen.form', $this->student))->assertOk();

        $this->actingAs($this->sz)->post(route('afstuderen.store', $this->student), [
            'inschrijving_id' => $insch->id,
            'afstudeerdatum' => '2026-07-15',
        ])->assertRedirect(route('studenten.show', $this->student));

        $insch->refresh();
        $this->assertSame(InschrijvingStatus::Afgestudeerd, $insch->status);
        $this->assertSame('2026-07-15', $insch->afstudeerdatum->toDateString());
        $this->assertTrue($this->student->fresh()->load('inschrijvingen')->isAlumnus());
        $this->assertDatabaseHas('audit_logs', ['veld' => 'afstuderen', 'actie' => 'wijziging']);
    }

    public function test_afstuderen_niet_in_laatste_leerjaar_is_geblokkeerd(): void
    {
        $insch = $this->inschrijving(3); // niet het laatste jaar

        // Geen kandidaat -> formulier 404.
        $this->actingAs($this->sz)->get(route('afstuderen.form', $this->student))->assertNotFound();

        // Direct posten wordt geweigerd (inschrijving valt buiten de kandidaten).
        $this->actingAs($this->sz)->post(route('afstuderen.store', $this->student), [
            'inschrijving_id' => $insch->id,
            'afstudeerdatum' => '2026-07-15',
        ])->assertNotFound();

        $this->assertSame(InschrijvingStatus::Actief, $insch->fresh()->status);
    }

    public function test_examencommissie_geeft_vervroegd_vrij_daarna_kan_afstuderen(): void
    {
        $insch = $this->inschrijving(3); // een jaar te vroeg

        // Examencommissie geeft vervroegd afstuderen vrij.
        $this->actingAs($this->examen)->post(route('inschrijving.vervroegd-afstuderen', $insch), [
            'vervroegd_afstuderen' => '1', 'reden' => 'Vrijstellingen + 180 EC behaald',
        ])->assertRedirect();
        $this->assertTrue((bool) $insch->fresh()->vervroegd_afstuderen);
        $this->assertDatabaseHas('audit_logs', ['veld' => 'vervroegd_afstuderen', 'actie' => 'wijziging']);

        // Nu kan Studentenzaken de student afstuderen, ook in jaar 3.
        $this->actingAs($this->sz)->post(route('afstuderen.store', $this->student), [
            'inschrijving_id' => $insch->id, 'afstudeerdatum' => '2026-07-15',
        ])->assertRedirect(route('studenten.show', $this->student));
        $this->assertSame(InschrijvingStatus::Afgestudeerd, $insch->fresh()->status);
    }

    public function test_alleen_examencommissie_en_beheer_geven_vervroegd_vrij(): void
    {
        $insch = $this->inschrijving(3);

        // Studentenzaken en Directie mogen dit niet.
        $this->actingAs($this->sz)->post(route('inschrijving.vervroegd-afstuderen', $insch),
            ['vervroegd_afstuderen' => '1'])->assertForbidden();
        $this->actingAs($this->directie)->post(route('inschrijving.vervroegd-afstuderen', $insch),
            ['vervroegd_afstuderen' => '1'])->assertForbidden();

        $this->assertFalse((bool) $insch->fresh()->vervroegd_afstuderen);
    }

    public function test_afgestudeerde_inschrijving_is_vergrendeld(): void
    {
        $insch = $this->inschrijving(4, status: InschrijvingStatus::Afgestudeerd);

        // Korting, betaalregeling en aanwezigheidsregeling worden geweigerd (geen wijziging).
        $this->actingAs($this->sz)->post(route('inschrijving.korting', $insch),
            ['korting_percentage' => '50', 'korting_reden' => 'test'])->assertRedirect();
        $this->actingAs($this->sz)->post(route('inschrijving.betaalregeling', $insch),
            ['betaalregeling' => 'volledig'])->assertRedirect();
        $this->actingAs($this->sz)->post(route('inschrijving.aanwezigheidsregeling', $insch),
            ['aanwezigheidsregeling_50' => '1'])->assertRedirect();

        $insch->refresh();
        $this->assertSame(0.0, (float) $insch->korting_percentage);
        // De wijziging naar 'volledig' is door de guard geblokkeerd.
        $this->assertNotSame(\App\Enums\Betaalregeling::Volledig, $insch->betaalregeling);
        $this->assertFalse((bool) $insch->aanwezigheidsregeling_50);

        // Uitschrijven is niet meer mogelijk.
        $this->actingAs($this->sz)->get(route('uitschrijven.form', $this->student))->assertNotFound();
    }

    public function test_dossier_toont_alumnus_en_vergrendeling(): void
    {
        $this->inschrijving(4, status: InschrijvingStatus::Afgestudeerd);

        $this->actingAs($this->sz)->get(route('studenten.show', $this->student))
            ->assertOk()
            ->assertSee('Alumnus')
            ->assertSee('Afgerond (afgestudeerd)');
    }

    public function test_examencommissie_ziet_de_vervroegd_afstuderen_kaart(): void
    {
        $this->inschrijving(3); // niet het laatste jaar -> override is relevant

        $this->actingAs($this->examen)->get(route('studenten.show', $this->student))
            ->assertOk()
            ->assertSee('Vervroegd afstuderen');
    }

    public function test_herinschrijven_zelfde_afgestudeerde_opleiding_geweigerd_andere_mag(): void
    {
        $this->inschrijving(4, status: InschrijvingStatus::Afgestudeerd); // ISLTH afgerond

        // Zelfde opleiding (ISLTH) opnieuw -> geweigerd.
        $this->actingAs($this->sz)->post(route('herinschrijven.store', $this->student), [
            'opleiding_id' => Opleiding::where('code', 'ISLTH')->value('id'),
            'periode_id' => Periode::where('code', '2026-2027')->value('id'),
            'leerjaar' => 1, 'inschrijfdatum' => '2026-09-01',
        ])->assertSessionHas('fout');
        $this->assertSame(1, $this->student->inschrijvingen()->count());

        // Andere opleiding (MGV) -> toegestaan, zelfde studentnummer.
        $this->actingAs($this->sz)->post(route('herinschrijven.store', $this->student), [
            'opleiding_id' => Opleiding::where('code', 'MGV')->value('id'),
            'periode_id' => Periode::where('code', '2026-2027')->value('id'),
            'leerjaar' => 1, 'inschrijfdatum' => '2026-09-01',
        ])->assertRedirect();

        $this->student->refresh();
        $this->assertSame('260800', $this->student->studentnummer);
        $this->assertSame(2, $this->student->inschrijvingen()->count());
        // De afgestudeerde ISLTH-inschrijving blijft bestaan (historie).
        $this->assertTrue($this->student->inschrijvingen()
            ->where('status', InschrijvingStatus::Afgestudeerd->value)->exists());
    }
}
