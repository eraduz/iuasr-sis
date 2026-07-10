<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Betaling;
use App\Models\CollegegeldTarief;
use App\Models\Inschrijving;
use App\Models\Periode;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischVakSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De Financiële Administratie moet een foute boeking kunnen herstellen. Zowel
 * wijzigen als verwijderen wordt met de oude waarden in de audit-log vastgelegd.
 */
class BetalingCorrectieTest extends TestCase
{
    use RefreshDatabase;

    private User $fin;
    private Student $student;
    private Inschrijving $inschrijving;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, SynthetischVakSeeder::class,
            GebruikerSeeder::class, SynthetischeStudentSeeder::class]);
        Carbon::setTestNow('2025-12-10');

        CollegegeldTarief::create([
            'periode_id' => Periode::where('actief', true)->value('id'),
            'opleiding_id' => null, 'bedrag' => 4000, 'aantal_termijnen' => 5,
        ]);

        $this->fin = User::where('rol', Rol::Financien)->firstOrFail();
        $this->inschrijving = Inschrijving::where('status', 'actief')->with('student')->firstOrFail();
        $this->student = $this->inschrijving->student;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function betaling(array $overschrijf = []): Betaling
    {
        return Betaling::create(array_merge([
            'student_id' => $this->student->id, 'inschrijving_id' => $this->inschrijving->id,
            'termijn' => 1, 'bedrag' => 800, 'datum' => '2025-09-05', 'betaalwijze' => 'overboeking',
        ], $overschrijf));
    }

    public function test_financien_wijzigt_een_betaling_en_dit_wordt_gelogd(): void
    {
        $betaling = $this->betaling();

        $this->actingAs($this->fin)->put(route('financien.betaling.bijwerken', [$this->student, $betaling]), [
            'inschrijving_id' => (string) $this->inschrijving->id, 'termijn' => '2',
            'bedrag' => '850.00', 'datum' => '2025-11-03', 'betaalwijze' => 'incasso',
        ])->assertSessionHasNoErrors()->assertRedirect(route('financien.student', $this->student));

        $betaling->refresh();
        $this->assertSame(2, $betaling->termijn);
        $this->assertSame('850.00', $betaling->bedrag);
        $this->assertSame('incasso', $betaling->betaalwijze);

        $this->assertDatabaseHas('audit_logs', ['veld' => 'betaling', 'actie' => 'wijziging']);
    }

    /** De audit-log bewaart de oude waarden, anders is de correctie niet te volgen. */
    public function test_de_oude_waarden_staan_in_de_audit_log(): void
    {
        $betaling = $this->betaling(['bedrag' => 800]);

        $this->actingAs($this->fin)->put(route('financien.betaling.bijwerken', [$this->student, $betaling]), [
            'inschrijving_id' => (string) $this->inschrijving->id, 'termijn' => '1',
            'bedrag' => '900.00', 'datum' => '2025-09-05',
        ])->assertSessionHasNoErrors();

        $log = \App\Models\AuditLog::where('veld', 'betaling')->where('actie', 'wijziging')->latest('id')->firstOrFail();

        $this->assertSame($betaling->id, $log->context['betaling_id']);
        $this->assertSame('800.00', $log->context['oud']['bedrag']);
        $this->assertSame('900.00', $log->context['nieuw']['bedrag']);
    }

    public function test_financien_verwijdert_een_betaling_en_dit_wordt_gelogd(): void
    {
        $betaling = $this->betaling();

        $this->actingAs($this->fin)
            ->delete(route('financien.betaling.verwijderen', [$this->student, $betaling]))
            ->assertRedirect(route('financien.student', $this->student));

        $this->assertDatabaseMissing('betalingen', ['id' => $betaling->id]);

        // Het bedrag komt via JSON terug; vergelijk op waarde, niet op type.
        $log = \App\Models\AuditLog::where('veld', 'betaling')->where('actie', 'verwijdering')->latest('id')->firstOrFail();
        $this->assertEquals(800.0, $log->context['bedrag']);
        $this->assertSame($betaling->id, $log->context['betaling_id']);
    }

    public function test_een_betaling_van_een_andere_student_kan_niet_worden_gewijzigd(): void
    {
        $andere = Student::where('id', '!=', $this->student->id)->firstOrFail();
        $betaling = $this->betaling();

        $this->actingAs($this->fin)->put(route('financien.betaling.bijwerken', [$andere, $betaling]), [
            'inschrijving_id' => (string) $this->inschrijving->id,
            'bedrag' => '1.00', 'datum' => '2025-09-05',
        ])->assertNotFound();

        $this->assertSame('800.00', $betaling->fresh()->bedrag);
    }

    public function test_wijzigen_naar_een_niet_bestaande_termijn_wordt_geweigerd(): void
    {
        $betaling = $this->betaling();
        // Eén factuur: er bestaat alleen termijn 1.
        $this->inschrijving->update(['betaalregeling' => 'volledig']);

        $this->actingAs($this->fin)->put(route('financien.betaling.bijwerken', [$this->student, $betaling]), [
            'inschrijving_id' => (string) $this->inschrijving->id, 'termijn' => '4',
            'bedrag' => '800.00', 'datum' => '2025-09-05',
        ])->assertSessionHasErrors('termijn');

        $this->assertSame(1, $betaling->fresh()->termijn);
    }

    public function test_alleen_financien_en_beheer_mogen_corrigeren(): void
    {
        $betaling = $this->betaling();
        $body = ['inschrijving_id' => (string) $this->inschrijving->id, 'bedrag' => '5.00', 'datum' => '2025-09-05'];

        $this->actingAs(User::where('rol', Rol::Beheerder)->first())
            ->put(route('financien.betaling.bijwerken', [$this->student, $betaling]), $body)->assertRedirect();

        foreach ([Rol::Studentenzaken, Rol::Directie, Rol::Docent, Rol::Examencommissie] as $rol) {
            $gebruiker = User::where('rol', $rol)->first();
            $this->actingAs($gebruiker)
                ->put(route('financien.betaling.bijwerken', [$this->student, $betaling]), $body)->assertForbidden();
            $this->actingAs($gebruiker)
                ->delete(route('financien.betaling.verwijderen', [$this->student, $betaling]))->assertForbidden();
        }

        $this->assertDatabaseHas('betalingen', ['id' => $betaling->id]);
    }
}
