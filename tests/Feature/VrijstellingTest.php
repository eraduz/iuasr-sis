<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Student;
use App\Models\User;
use App\Models\Vak;
use App\Models\Vaktoewijzing;
use App\Support\Overgangsbeoordeling;
use App\Support\Transcript;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Database\Seeders\VaktoewijzingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VrijstellingTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;
    private Vaktoewijzing $toewijzing;
    private Vak $vak;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, GebruikerSeeder::class, SynthetischeStudentSeeder::class, VaktoewijzingSeeder::class]);
        $this->student = Student::where('studentnummer', '261001')->first();
        // Neem een toewijzing die (nog) niet vrijgesteld is.
        $this->toewijzing = $this->student->inschrijvingen()->first()
            ->vaktoewijzingen()->where('vrijgesteld', false)->with('vak')->firstOrFail();
        $this->vak = $this->toewijzing->vak;
    }

    private function legVast(User $als): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($als)->post(route('studenten.vrijstellingen.store', $this->student), [
            'vaktoewijzing_id' => $this->toewijzing->id,
            'grondslag' => 'eerder_behaald',
            'besluit' => 'EC-2026-099',
            'besluit_datum' => '2026-09-10',
            'toelichting' => 'Test',
        ]);
    }

    public function test_studentenzaken_legt_vrijstelling_vast(): void
    {
        $this->legVast(User::where('rol', Rol::Studentenzaken)->first())->assertRedirect();

        $this->toewijzing->refresh();
        $this->assertTrue($this->toewijzing->vrijgesteld);
        $this->assertSame((int) $this->vak->ec, $this->toewijzing->vrijstelling_ec);
        $this->assertSame('EC-2026-099', $this->toewijzing->vrijstelling_besluit);
        $this->assertDatabaseHas('audit_logs', ['veld' => 'vrijstelling', 'actie' => 'wijziging']);
    }

    public function test_besluit_referentie_is_verplicht(): void
    {
        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->post(route('studenten.vrijstellingen.store', $this->student), [
                'vaktoewijzing_id' => $this->toewijzing->id,
                'grondslag' => 'vooropleiding',
                'besluit_datum' => '2026-09-10',
            ])->assertSessionHasErrors('besluit');
    }

    public function test_vrijstelling_telt_als_behaald_op_cijferlijst(): void
    {
        $this->legVast(User::where('rol', Rol::Studentenzaken)->first());

        $transcript = Transcript::voor($this->student->fresh());
        $regel = collect($transcript['studiejaren'])->flatMap(fn ($sj) => $sj['regels'])
            ->firstWhere('vak.id', $this->vak->id);

        $this->assertSame('vr', $regel['eind']['status']);
        $this->assertSame((int) $this->vak->ec, $regel['ec']);
        $this->assertGreaterThanOrEqual(
            (int) $this->vak->ec,
            Overgangsbeoordeling::behaaldeEc($this->student->inschrijvingen()->first())
        );
    }

    public function test_intrekken_verwijdert_vrijstelling(): void
    {
        $this->legVast(User::where('rol', Rol::Studentenzaken)->first());
        $this->toewijzing->refresh();
        $this->assertTrue($this->toewijzing->vrijgesteld);

        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->delete(route('studenten.vrijstellingen.destroy', [$this->student, $this->toewijzing]))
            ->assertRedirect();

        $this->assertFalse($this->toewijzing->fresh()->vrijgesteld);
        $this->assertNull($this->toewijzing->fresh()->vrijstelling_besluit);
    }

    public function test_rolscheiding_alleen_sz_en_beheerder(): void
    {
        // Examencommissie/Directie/Docent mogen vrijstelling NIET registreren (geen inschrijvingbeheer).
        foreach ([Rol::Examencommissie, Rol::Directie, Rol::Docent] as $rol) {
            $this->legVast(User::where('rol', $rol)->first())->assertForbidden();
        }
        // Studentenzaken en Beheerder wel.
        $this->legVast(User::where('rol', Rol::Beheerder)->first())->assertRedirect();
    }
}
