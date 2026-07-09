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

class LifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $sz;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferentieSeeder::class);
        $this->sz = User::create(['naam' => 'SZ', 'email' => 'sz@iuasr.test', 'rol' => Rol::Studentenzaken]);

        $this->student = Student::create(['studentnummer' => '260500', 'voornaam' => 'Test', 'achternaam' => 'Persoon']);
        Inschrijving::create([
            'student_id' => $this->student->id,
            'opleiding_id' => Opleiding::where('code', 'ISLTH')->value('id'),
            'periode_id' => Periode::where('actief', true)->value('id'),
            'leerjaar' => 1,
            'status' => InschrijvingStatus::Actief,
            'inschrijfdatum' => '2026-09-01',
        ]);
    }

    public function test_uitschrijven_berekent_einde_van_de_maand(): void
    {
        $this->actingAs($this->sz)->post(route('uitschrijven.store', $this->student), [
            'reden' => 'Op eigen verzoek',
            'peildatum' => '2026-07-15',
        ])->assertRedirect(route('studenten.show', $this->student));

        $insch = $this->student->inschrijvingen()->first();
        $this->assertSame(InschrijvingStatus::Uitgeschreven, $insch->status);
        $this->assertSame('2026-07-31', $insch->uitschrijfdatum->toDateString());
        $this->assertDatabaseHas('audit_logs', ['veld' => 'uitschrijving', 'actie' => 'wijziging']);
    }

    public function test_schorsen_is_een_klik_en_omkeerbaar(): void
    {
        $this->actingAs($this->sz)->post(route('studenten.schors', $this->student));
        $this->assertSame(InschrijvingStatus::Geschorst, $this->student->inschrijvingen()->first()->status);

        // Nogmaals = schorsing opheffen.
        $this->actingAs($this->sz)->post(route('studenten.schors', $this->student));
        $this->assertSame(InschrijvingStatus::Actief, $this->student->inschrijvingen()->first()->status);
    }

    public function test_herinschrijven_behoudt_studentnummer_en_maakt_nieuwe_inschrijving(): void
    {
        $nieuwe = Periode::where('code', '2026-2027')->first();
        $islth = Opleiding::where('code', 'ISLTH')->value('id');

        $this->actingAs($this->sz)->post(route('herinschrijven.store', $this->student), [
            'opleiding_id' => $islth,
            'periode_id' => $nieuwe->id,
            'leerjaar' => 2,
            'inschrijfdatum' => '2027-09-01',
        ])->assertRedirect(route('studenten.show', $this->student));

        $this->assertSame(2, $this->student->inschrijvingen()->count());
        $this->assertSame('260500', $this->student->fresh()->studentnummer); // ongewijzigd
    }

    public function test_herinschrijven_kan_van_opleiding_wisselen(): void
    {
        $nieuwe = Periode::where('code', '2026-2027')->first();
        $pabo = Opleiding::where('code', 'PABO')->first(); // andere opleiding dan ISLTH

        $this->actingAs($this->sz)->post(route('herinschrijven.store', $this->student), [
            'opleiding_id' => $pabo->id,
            'periode_id' => $nieuwe->id,
            'leerjaar' => 1,
            'inschrijfdatum' => '2026-09-01',
        ])->assertRedirect(route('studenten.show', $this->student));

        $nieuw = $this->student->inschrijvingen()->orderByDesc('id')->first();
        $this->assertSame($pabo->id, $nieuw->opleiding_id); // opleiding gewijzigd (studiewissel)
        $this->assertSame(1, $nieuw->leerjaar);
        $this->assertDatabaseHas('audit_logs', ['veld' => 'herinschrijving', 'actie' => 'aanmaak']);
    }

    public function test_muteren_werkt_bij_en_logt(): void
    {
        $this->actingAs($this->sz)->put(route('studenten.update', $this->student), [
            'voornaam' => 'Test',
            'achternaam' => 'Gewijzigd',
            'email' => 't.gewijzigd@student.iuasr.nl',
        ])->assertRedirect(route('studenten.show', $this->student));

        $this->assertSame('Gewijzigd', $this->student->fresh()->achternaam);
        $this->assertDatabaseHas('audit_logs', ['veld' => 'persoonsgegevens', 'actie' => 'wijziging']);
    }

    public function test_verklaring_uitgifte_wordt_gelogd(): void
    {
        $this->actingAs($this->sz)
            ->get(route('verklaringen', ['student' => $this->student->id, 'type' => 'studentbewijs']))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', ['veld' => 'verklaring', 'actie' => 'uitgifte']);
    }
}
