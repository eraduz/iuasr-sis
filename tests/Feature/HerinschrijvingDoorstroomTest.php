<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Inschrijving;
use App\Models\Opleiding;
use App\Models\Periode;
use App\Models\Student;
use App\Models\User;
use App\Models\Vak;
use App\Models\Vaktoewijzing;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Database\Seeders\ToetsonderdeelSeeder;
use Database\Seeders\VakDocentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Doorstroomtoets bij herinschrijven (opdrachtgever 2026-07-22): naar een hóger
 * leerjaar mag alleen wie het vorige jaar heeft gehaald (EC >= drempel) én van wie
 * de EC nog geldig zijn (pauze <= 5 jaar). Studiewissel en jaar overdoen vallen
 * buiten de toets; de Beheerder mag een 'niet geslaagd'-blokkade vrijgeven.
 */
class HerinschrijvingDoorstroomTest extends TestCase
{
    use RefreshDatabase;

    private User $beheerder;
    private User $sz;
    private Student $student;
    private Inschrijving $huidige;
    private Opleiding $islth;
    private Periode $doelPeriode;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            ReferentieSeeder::class, CurriculumSeeder::class, ToetsonderdeelSeeder::class,
            DocentSeeder::class, VakDocentSeeder::class, GebruikerSeeder::class, SynthetischeStudentSeeder::class,
        ]);

        $this->beheerder = User::where('rol', Rol::Beheerder)->firstOrFail();
        $this->sz = User::where('rol', Rol::Studentenzaken)->firstOrFail();
        $this->islth = Opleiding::where('code', 'ISLTH')->firstOrFail();

        $this->student = Student::whereHas('inschrijvingen', fn ($q) => $q->where('status', 'actief')->where('opleiding_id', $this->islth->id))->firstOrFail();
        $this->huidige = $this->student->inschrijvingen()->where('status', 'actief')->where('opleiding_id', $this->islth->id)->firstOrFail();
        // Vorig leerjaar = 2, ingeschreven ~1 jaar geleden (binnen de geldigheidsduur).
        $this->huidige->update(['leerjaar' => 2, 'inschrijfdatum' => now()->subYear()->toDateString()]);

        // Een studiejaar waarin de student nog niet is ingeschreven.
        $this->doelPeriode = Periode::whereNotIn('id', $this->student->inschrijvingen->pluck('periode_id'))->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'opleiding_id' => $this->islth->id,
            'periode_id' => $this->doelPeriode->id,
            'leerjaar' => 3,
            'inschrijfdatum' => now()->toDateString(),
        ], $overrides);
    }

    private function herinschrijf(User $als, array $payload)
    {
        return $this->actingAs($als)->post(route('herinschrijven.store', $this->student), $payload);
    }

    public function test_jaar_overdoen_valt_buiten_de_doorstroomtoets(): void
    {
        // Zelfde leerjaar (2) is geen doorstroom -> geen toets, altijd toegestaan.
        $this->islth->update(['ec_overgang_drempel' => 40]);

        $this->herinschrijf($this->sz, $this->payload(['leerjaar' => 2]))
            ->assertRedirect(route('studenten.show', $this->student))
            ->assertSessionMissing('fout');

        $this->assertDatabaseHas('inschrijvingen', [
            'student_id' => $this->student->id, 'periode_id' => $this->doelPeriode->id, 'leerjaar' => 2,
        ]);
    }

    public function test_onbekende_drempel_staat_doorstroom_toe_met_waarschuwing(): void
    {
        $this->islth->update(['ec_overgang_drempel' => null]);

        $this->herinschrijf($this->sz, $this->payload())
            ->assertRedirect(route('studenten.show', $this->student))
            ->assertSessionMissing('fout')
            ->assertSessionHas('waarschuwing');

        $this->assertDatabaseHas('inschrijvingen', ['student_id' => $this->student->id, 'leerjaar' => 3]);
    }

    public function test_niet_geslaagd_blokkeert_doorstroom(): void
    {
        $this->islth->update(['ec_overgang_drempel' => 40]); // geen EC behaald -> negatief

        $this->herinschrijf($this->sz, $this->payload())
            ->assertRedirect()
            ->assertSessionHas('fout');

        $this->assertDatabaseMissing('inschrijvingen', ['student_id' => $this->student->id, 'leerjaar' => 3]);
    }

    public function test_beheerder_kan_de_doorstroomblokkade_vrijgeven(): void
    {
        $this->islth->update(['ec_overgang_drempel' => 40]);

        $this->herinschrijf($this->beheerder, $this->payload(['override' => '1', 'override_reden' => 'Besluit examencommissie 2026-07-22']))
            ->assertRedirect(route('studenten.show', $this->student))
            ->assertSessionMissing('fout');

        $this->assertDatabaseHas('inschrijvingen', ['student_id' => $this->student->id, 'leerjaar' => 3]);
        $this->assertDatabaseHas('audit_logs', ['veld' => 'herinschrijving_override']);
    }

    public function test_studentenzaken_kan_niet_vrijgeven(): void
    {
        // De override is voorbehouden aan de Beheerder; SZ blijft geblokkeerd.
        $this->islth->update(['ec_overgang_drempel' => 40]);

        $this->herinschrijf($this->sz, $this->payload(['override' => '1', 'override_reden' => 'poging']))
            ->assertSessionHas('fout');

        $this->assertDatabaseMissing('inschrijvingen', ['student_id' => $this->student->id, 'leerjaar' => 3]);
    }

    public function test_ec_verlopen_na_lange_pauze_blokkeert_doorstroom(): void
    {
        // Vorige inschrijving 7 jaar geleden -> EC vervallen -> doorstroom kan niet.
        $this->huidige->update(['inschrijfdatum' => now()->subYears(7)->toDateString()]);

        $this->herinschrijf($this->sz, $this->payload())
            ->assertSessionHas('fout');
        $this->assertDatabaseMissing('inschrijvingen', ['student_id' => $this->student->id, 'leerjaar' => 3]);

        // Opnieuw beginnen op leerjaar 1 mag wel (geen doorstroom).
        $this->herinschrijf($this->sz, $this->payload(['leerjaar' => 1]))
            ->assertRedirect(route('studenten.show', $this->student))
            ->assertSessionMissing('fout');
        $this->assertDatabaseHas('inschrijvingen', ['student_id' => $this->student->id, 'leerjaar' => 1]);
    }

    public function test_geslaagd_vorig_jaar_staat_doorstroom_toe(): void
    {
        $this->islth->update(['ec_overgang_drempel' => 1]);
        // EC behaald via een vrijstelling op een leerjaar-2 vak (telt als volledige EC).
        $vak = Vak::where('opleiding_id', $this->islth->id)->where('leerjaar', 2)->firstOrFail();
        Vaktoewijzing::updateOrCreate(
            ['inschrijving_id' => $this->huidige->id, 'vak_id' => $vak->id],
            ['vrijgesteld' => true]
        );

        $this->herinschrijf($this->sz, $this->payload())
            ->assertRedirect(route('studenten.show', $this->student))
            ->assertSessionMissing('fout')
            ->assertSessionMissing('waarschuwing');

        $this->assertDatabaseHas('inschrijvingen', ['student_id' => $this->student->id, 'leerjaar' => 3]);
    }
}
