<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Opleiding;
use App\Models\Organisatie;
use App\Models\Stage;
use App\Models\Stageperiode;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\OrganisatieSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\StageperiodeSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stageperioden per opleiding (Islamitische Theologie: 3 stages; Master IGV: 2).
 * Bewaakt de seed, het beheer via Opzoektabellen, en het kiezen/invoeren van een
 * stageperiode + uren bij het plaatsen van een student — inclusief de regel dat
 * de keuze verplicht is zodra een opleiding stageperioden heeft.
 */
class StageperiodeTest extends TestCase
{
    use RefreshDatabase;

    private User $beheerder;
    private Organisatie $islthOrg;  // R260005 Moskee An-Nasr (ISLTH)
    private Student $islthStudent;
    private int $islthId;
    private Stageperiode $stage1;      // ISLTH · Stage 1 · 280 u
    private Stageperiode $mgvSnuffel;  // MGV · Snuffelstage · 40 u

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class, SynthetischeStudentSeeder::class, StageperiodeSeeder::class, OrganisatieSeeder::class]);

        $this->beheerder = User::where('rol', Rol::Beheerder)->firstOrFail();
        $this->islthOrg = Organisatie::where('relatienummer', 'R260005')->firstOrFail();
        $this->islthId = Opleiding::where('code', 'ISLTH')->value('id');
        $this->islthStudent = Student::whereHas('inschrijvingen', fn ($q) => $q->where('status', 'actief')->where('opleiding_id', $this->islthId))->firstOrFail();
        $this->stage1 = Stageperiode::where('code', 'STAGE-1')->firstOrFail();
        $this->mgvSnuffel = Stageperiode::where('code', 'STAGE-SNUF')->firstOrFail();
    }

    public function test_stageperioden_zijn_geseed_voor_islth_en_mgv(): void
    {
        $this->assertSame(3, Stageperiode::where('opleiding_id', $this->islthId)->count());
        $this->assertSame(2, Stageperiode::whereHas('opleiding', fn ($q) => $q->where('code', 'MGV'))->count());

        // De vastgestelde urennormen.
        $this->assertSame(140, Stageperiode::where('code', 'STAGE-VERK')->value('verplichte_uren'));
        $this->assertSame(280, $this->stage1->verplichte_uren);
        $this->assertSame(560, Stageperiode::where('code', 'STAGE-2')->value('verplichte_uren'));
        $this->assertSame(40, $this->mgvSnuffel->verplichte_uren);
        $this->assertSame(480, Stageperiode::where('code', 'STAGE-GROOT')->value('verplichte_uren'));
    }

    public function test_beheer_via_opzoektabellen_toont_en_maakt_stageperioden(): void
    {
        $this->actingAs($this->beheerder)->get(route('opzoektabellen.tabel', 'stageperioden'))
            ->assertOk()
            ->assertSee('Verkennende stage')
            ->assertSee('Grote stage');

        // Beheer kan een nieuwe periode toevoegen (bijv. later voor PABO).
        $paboId = Opleiding::where('code', 'PABO')->value('id');
        $this->actingAs($this->beheerder)->post(route('opzoektabellen.store', 'stageperioden'), [
            'opleiding_id' => $paboId,
            'naam' => 'LIO-stage',
            'verplichte_uren' => 600,
            'volgorde' => 1,
            'actief' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('stageperioden', ['opleiding_id' => $paboId, 'naam' => 'LIO-stage', 'verplichte_uren' => 600]);
    }

    public function test_stageformulier_toont_de_stageperioden_van_de_opleiding(): void
    {
        $this->actingAs($this->beheerder)->get(route('stages.create', $this->islthOrg))
            ->assertOk()
            ->assertSee('id="stageperiode-select"', false)
            ->assertSee('Verkennende stage')
            ->assertSee('Grote Stage 2');
    }

    public function test_plaatsing_met_stageperiode_en_uren_wordt_opgeslagen(): void
    {
        $this->actingAs($this->beheerder)->post(route('stages.store', $this->islthOrg), [
            'student_id' => $this->islthStudent->id,
            'opleiding_id' => $this->islthId,
            'stageperiode_id' => $this->stage1->id,
            'uren' => 300,
            'status' => 'lopend',
        ])->assertRedirect(route('relaties.show', $this->islthOrg));

        $this->assertDatabaseHas('stages', [
            'student_id' => $this->islthStudent->id,
            'opleiding_id' => $this->islthId,
            'stageperiode_id' => $this->stage1->id,
            'uren' => 300,
        ]);
    }

    public function test_uren_vallen_terug_op_de_norm_als_ze_leeg_blijven(): void
    {
        $this->actingAs($this->beheerder)->post(route('stages.store', $this->islthOrg), [
            'student_id' => $this->islthStudent->id,
            'opleiding_id' => $this->islthId,
            'stageperiode_id' => $this->stage1->id,
            'status' => 'aangevraagd',
        ])->assertRedirect();

        // Geen uren opgegeven -> de urennorm (280) van de stageperiode.
        $this->assertDatabaseHas('stages', [
            'student_id' => $this->islthStudent->id,
            'stageperiode_id' => $this->stage1->id,
            'uren' => 280,
        ]);
    }

    public function test_stageperiode_is_verplicht_als_de_opleiding_perioden_heeft(): void
    {
        $this->actingAs($this->beheerder)->post(route('stages.store', $this->islthOrg), [
            'student_id' => $this->islthStudent->id,
            'opleiding_id' => $this->islthId,
            'status' => 'aangevraagd',
        ])->assertSessionHasErrors('stageperiode_id');

        $this->assertDatabaseMissing('stages', ['student_id' => $this->islthStudent->id, 'opleiding_id' => $this->islthId]);
    }

    public function test_stageperiode_van_een_andere_opleiding_wordt_geweigerd(): void
    {
        // Een MGV-stageperiode koppelen aan een ISLTH-plaatsing mag niet.
        $this->actingAs($this->beheerder)->post(route('stages.store', $this->islthOrg), [
            'student_id' => $this->islthStudent->id,
            'opleiding_id' => $this->islthId,
            'stageperiode_id' => $this->mgvSnuffel->id,
            'status' => 'aangevraagd',
        ])->assertSessionHasErrors('stageperiode_id');
    }

    /** Zet het leerjaar van de actieve ISLTH-inschrijving van de teststudent. */
    private function zetLeerjaar(int $leerjaar): void
    {
        $this->islthStudent->inschrijvingen()
            ->where('opleiding_id', $this->islthId)->where('status', 'actief')
            ->firstOrFail()->update(['leerjaar' => $leerjaar]);
    }

    public function test_afwijkend_leerjaar_geeft_waarschuwing_maar_slaat_wel_op(): void
    {
        // Stage 1 hoort bij jaar 3; de student staat in jaar 1.
        $this->zetLeerjaar(1);

        $this->actingAs($this->beheerder)->post(route('stages.store', $this->islthOrg), [
            'student_id' => $this->islthStudent->id,
            'opleiding_id' => $this->islthId,
            'stageperiode_id' => $this->stage1->id,
            'status' => 'aangevraagd',
        ])->assertRedirect()->assertSessionHas('waarschuwing');

        // Ondanks de waarschuwing is de plaatsing opgeslagen (niet-blokkerend).
        $this->assertDatabaseHas('stages', [
            'student_id' => $this->islthStudent->id,
            'stageperiode_id' => $this->stage1->id,
        ]);
    }

    public function test_passend_leerjaar_geeft_geen_waarschuwing(): void
    {
        // Stage 1 hoort bij jaar 3; zet de student in jaar 3.
        $this->zetLeerjaar(3);

        $this->actingAs($this->beheerder)->post(route('stages.store', $this->islthOrg), [
            'student_id' => $this->islthStudent->id,
            'opleiding_id' => $this->islthId,
            'stageperiode_id' => $this->stage1->id,
            'status' => 'aangevraagd',
        ])->assertRedirect()->assertSessionMissing('waarschuwing');
    }

    public function test_stageperiode_zonder_leerjaar_geeft_nooit_een_waarschuwing(): void
    {
        $this->zetLeerjaar(1);
        $vrij = Stageperiode::create([
            'opleiding_id' => $this->islthId, 'naam' => 'Vrije stage',
            'verplichte_uren' => 100, 'leerjaar' => null, 'actief' => true,
        ]);

        $this->actingAs($this->beheerder)->post(route('stages.store', $this->islthOrg), [
            'student_id' => $this->islthStudent->id,
            'opleiding_id' => $this->islthId,
            'stageperiode_id' => $vrij->id,
            'status' => 'aangevraagd',
        ])->assertRedirect()->assertSessionMissing('waarschuwing');
    }

    public function test_studentveld_bevat_leerjaar_data_voor_de_filter(): void
    {
        $this->actingAs($this->beheerder)->get(route('stages.create', $this->islthOrg))
            ->assertOk()
            ->assertSee('data-leerjaren', false)
            ->assertSee('data-leerjaar=', false);
    }
}
