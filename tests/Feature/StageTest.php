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
    private User $stagecoordinator; // ISLTH + MGV
    private User $relatiebeheerder; // PABO
    private Organisatie $paboOrg;   // R260001
    private Organisatie $mgvOrg;    // R260003

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class, SynthetischeStudentSeeder::class, OrganisatieSeeder::class]);

        $this->beheerder = User::where('rol', Rol::Beheerder)->firstOrFail();
        $this->stagecoordinator = User::where('rol', Rol::Stagecoordinator)->firstOrFail();
        $this->relatiebeheerder = User::where('rol', Rol::Relatiebeheerder)->firstOrFail();
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

    public function test_stagecoordinator_kan_pabo_niet_beheren_maar_mgv_wel(): void
    {
        // PABO valt buiten de opleidingen (ISLTH + MGV) van de stagecoördinator.
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
