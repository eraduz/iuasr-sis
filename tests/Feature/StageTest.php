<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Opleiding;
use App\Models\Organisatie;
use App\Models\Stage;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\OrganisatieSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module Relatiebeheer & Stagebeheer — Fase D (stageplaatsen & stages). Bewaakt
 * de plaatsing, de bezetting, de rolscheiding (magStagebeheer, opleidinggebonden)
 * en de gelogde beoordeling.
 */
class StageTest extends TestCase
{
    use RefreshDatabase;

    private User $beheerder;
    private User $stagecoordinator; // MGV
    private User $relatiebeheerder; // PABO
    private Organisatie $paboOrg;   // R260001
    private Organisatie $mgvOrg;    // R260003

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class, SynthetischeStudentSeeder::class, OrganisatieSeeder::class]);

        $this->beheerder = User::where('rol', Rol::Beheerder)->firstOrFail();
        $this->stagecoordinator = User::where('email', 'j.prins@iuasr.nl')->firstOrFail(); // MGV
        $this->relatiebeheerder = User::where('email', 'l.haddad@iuasr.nl')->firstOrFail(); // PABO
        $this->paboOrg = Organisatie::where('relatienummer', 'R260001')->firstOrFail();
        $this->mgvOrg = Organisatie::where('relatienummer', 'R260003')->firstOrFail();
    }

    public function test_stages_overzicht_toont_de_demo_stage(): void
    {
        $this->actingAs($this->beheerder)->get(route('stages'))->assertOk()->assertSee('S260001');
    }

    public function test_relatiekaart_toont_stageplaatsen_en_stages(): void
    {
        $this->actingAs($this->beheerder)->get(route('relaties.show', $this->paboOrg))
            ->assertOk()
            ->assertSee('Stageplaatsen')
            ->assertSee('Stages')
            ->assertSee('S260001');
    }

    public function test_stageplaats_toevoegen(): void
    {
        $paboId = Opleiding::where('code', 'PABO')->value('id');

        $this->actingAs($this->beheerder)->post(route('stageplaatsen.store', $this->paboOrg), [
            'opleiding_id' => $paboId,
            'aantal_plaatsen' => 2,
            'max_studenten' => 2,
            'actief' => '1',
        ])->assertRedirect(route('relaties.show', $this->paboOrg));

        $this->assertDatabaseHas('stageplaatsen', [
            'organisatie_id' => $this->paboOrg->id,
            'opleiding_id' => $paboId,
            'aantal_plaatsen' => 2,
        ]);
    }

    public function test_student_plaatsen_genereert_stagenummer(): void
    {
        $paboId = Opleiding::where('code', 'PABO')->value('id');
        $student = Student::where('studentnummer', '261005')->firstOrFail();

        $this->actingAs($this->beheerder)->post(route('stages.store', $this->paboOrg), [
            'student_id' => $student->id,
            'opleiding_id' => $paboId,
            'status' => 'aangevraagd',
        ])->assertRedirect(route('relaties.show', $this->paboOrg));

        $stage = Stage::where('student_id', $student->id)->where('organisatie_id', $this->paboOrg->id)->firstOrFail();
        $this->assertStringStartsWith('S', $stage->stagenummer);
    }

    public function test_student_plaatsen_via_het_zoekveld(): void
    {
        // De keuzelijst is vervangen door een zoekveld: het formulier stuurt
        // "261005 — Naam" en de server pelt het studentnummer eraf.
        $paboId = Opleiding::where('code', 'PABO')->value('id');
        $student = Student::where('studentnummer', '261005')->firstOrFail();

        $this->actingAs($this->beheerder)->post(route('stages.store', $this->paboOrg), [
            'student_zoek' => '261005 — '.$student->volledigeNaam(),
            'opleiding_id' => $paboId,
            'status' => 'aangevraagd',
        ])->assertRedirect(route('relaties.show', $this->paboOrg));

        $this->assertDatabaseHas('stages', [
            'student_id' => $student->id,
            'organisatie_id' => $this->paboOrg->id,
        ]);
    }

    public function test_zoekveld_accepteert_een_kaal_studentnummer(): void
    {
        // Wie het nummer uit het hoofd kent, hoeft niets uit de lijst te kiezen.
        $paboId = Opleiding::where('code', 'PABO')->value('id');
        $student = Student::where('studentnummer', '261005')->firstOrFail();

        $this->actingAs($this->beheerder)->post(route('stages.store', $this->paboOrg), [
            'student_zoek' => '261005',
            'opleiding_id' => $paboId,
            'status' => 'aangevraagd',
        ])->assertRedirect(route('relaties.show', $this->paboOrg));

        $this->assertDatabaseHas('stages', ['student_id' => $student->id]);
    }

    public function test_zoekveld_weigert_onbekende_of_ontoegankelijke_student(): void
    {
        // Onzin-invoer geeft een nette validatiefout, geen stille mislukking.
        $paboId = Opleiding::where('code', 'PABO')->value('id');

        $this->actingAs($this->beheerder)->post(route('stages.store', $this->paboOrg), [
            'student_zoek' => 'bestaat niet',
            'opleiding_id' => $paboId,
            'status' => 'aangevraagd',
        ])->assertSessionHasErrors('student_id');

        $this->assertDatabaseCount('stages', 1); // alleen de demo-stage uit de seed
    }

    public function test_zoekveld_toont_geen_studenten_van_een_andere_opleiding(): void
    {
        // Scoping blijft server-side: de MGV-coördinator kan via het zoekveld geen
        // PABO-student plaatsen bij zijn eigen organisatie.
        $mgvId = Opleiding::where('code', 'MGV')->value('id');
        $pabostudent = Student::where('studentnummer', '261005')->firstOrFail();

        $this->actingAs($this->stagecoordinator)->post(route('stages.store', $this->mgvOrg), [
            'student_zoek' => $pabostudent->studentnummer.' — '.$pabostudent->volledigeNaam(),
            'opleiding_id' => $mgvId,
            'status' => 'aangevraagd',
        ])->assertSessionHasErrors('student_id');
    }

    public function test_plaatsingsformulier_toont_het_zoekveld_met_datalist(): void
    {
        $this->actingAs($this->beheerder)->get(route('stages.create', $this->paboOrg))
            ->assertOk()
            ->assertSee('name="student_zoek"', false)
            ->assertSee('<datalist id="stage-studenten">', false)
            ->assertDontSee('<select name="student_id"', false);
    }

    public function test_stagecoordinator_kan_pabo_niet_beheren_maar_mgv_wel(): void
    {
        // PABO valt buiten de opleiding (MGV) van de stagecoördinator.
        $this->actingAs($this->stagecoordinator)->get(route('stageplaatsen.create', $this->paboOrg))->assertForbidden();
        $this->actingAs($this->stagecoordinator)->get(route('stages.create', $this->paboOrg))->assertForbidden();

        // MGV mag hij wel.
        $this->actingAs($this->stagecoordinator)->get(route('stageplaatsen.create', $this->mgvOrg))->assertOk();
    }

    public function test_relatiebeheerder_ziet_stages_maar_kan_niet_plaatsen(): void
    {
        $this->actingAs($this->relatiebeheerder)->get(route('relaties.show', $this->paboOrg))->assertOk();
        // Plaatsen vereist magStagebeheer (stagecoördinator/beheer) — relatiebeheerder niet.
        $this->actingAs($this->relatiebeheerder)->get(route('stages.create', $this->paboOrg))->assertForbidden();
    }

    public function test_beoordeling_wordt_vastgelegd_en_gelogd(): void
    {
        $stage = Stage::where('stagenummer', 'S260001')->firstOrFail();
        $paboId = Opleiding::where('code', 'PABO')->value('id');

        $this->actingAs($this->beheerder)->put(route('stages.update', $stage), [
            'student_id' => $stage->student_id,
            'opleiding_id' => $paboId,
            'status' => 'afgerond',
            'beoordeling' => 'voldoende',
            'beoordeling_toelichting' => 'Sterke stage.',
        ])->assertRedirect(route('relaties.show', $this->paboOrg));

        $this->assertSame('voldoende', $stage->fresh()->beoordeling);
        $this->assertDatabaseHas('audit_logs', ['veld' => 'stage_beoordeling']);
    }
}
