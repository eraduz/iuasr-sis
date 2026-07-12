<?php

namespace Tests\Feature;

use App\Enums\Afstudeerstap;
use App\Enums\InschrijvingStatus;
use App\Enums\Rol;
use App\Models\Afstudeerproces;
use App\Models\Afstudeerprocesstap;
use App\Models\Inschrijving;
use App\Models\Opleiding;
use App\Models\Periode;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AfstudeerprocesTest extends TestCase
{
    use RefreshDatabase;

    private User $ec;
    private User $sz;
    private User $directie;
    private Student $student;
    private Inschrijving $insch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferentieSeeder::class);
        $this->ec = User::create(['naam' => 'EC', 'email' => 'ec@iuasr.test', 'rol' => Rol::Examencommissie]);
        $this->sz = User::create(['naam' => 'SZ', 'email' => 'sz@iuasr.test', 'rol' => Rol::Studentenzaken]);
        $this->directie = User::create(['naam' => 'Dir', 'email' => 'dir@iuasr.test', 'rol' => Rol::Directie]);

        $this->student = Student::create(['studentnummer' => '260900', 'voornaam' => 'Laatste', 'achternaam' => 'Jaar']);
        $this->insch = Inschrijving::create([
            'student_id' => $this->student->id,
            'opleiding_id' => Opleiding::where('code', 'ISLTH')->value('id'), // nominale_jaren = 4
            'periode_id' => Periode::where('actief', true)->value('id'),
            'leerjaar' => 4,
            'status' => InschrijvingStatus::Actief,
            'inschrijfdatum' => '2026-09-01',
        ]);
    }

    private function startProces(): Afstudeerproces
    {
        $this->actingAs($this->ec)->post(route('afstuderen.proces.start', $this->insch))->assertRedirect();

        return $this->insch->afstudeerproces()->with('stappen')->firstOrFail();
    }

    private function stap(Afstudeerproces $proces, Afstudeerstap $enum): Afstudeerprocesstap
    {
        return $proces->stappen()->where('stap', $enum->value)->firstOrFail();
    }

    public function test_examencommissie_start_proces_met_vijf_stappen(): void
    {
        $proces = $this->startProces();

        $this->assertSame(5, $proces->stappen()->count());
        $this->assertSame(Afstudeerproces::LOPEND, $proces->status);
        $this->assertSame($this->ec->id, $proces->gestart_door_id);
        $this->assertDatabaseHas('audit_logs', ['veld' => 'afstudeerproces', 'actie' => 'aanmaak']);
    }

    public function test_alleen_examencommissie_en_beheer_starten_het_proces(): void
    {
        $this->actingAs($this->sz)->post(route('afstuderen.proces.start', $this->insch))->assertForbidden();
        $this->actingAs($this->directie)->post(route('afstuderen.proces.start', $this->insch))->assertForbidden();
        $this->assertDatabaseCount('afstudeerprocessen', 0);
    }

    public function test_stappen_zijn_strikt_per_rol(): void
    {
        $proces = $this->startProces();

        // Examencommissie mag stap 1 (verzoek), niet stap 4 (diploma klaarmaken = SZ).
        $this->actingAs($this->ec)->post(route('afstuderen.stap.afvinken', $this->stap($proces, Afstudeerstap::DiplomaKlaarmaken)))
            ->assertForbidden();
        // Studentenzaken mag stap 1 niet (dat is de examencommissie).
        $this->actingAs($this->sz)->post(route('afstuderen.stap.afvinken', $this->stap($proces, Afstudeerstap::Verzoek)))
            ->assertForbidden();
    }

    public function test_stappen_worden_sequentieel_afgevinkt(): void
    {
        $proces = $this->startProces();

        // Stap 2 kan niet vóór stap 1 (zelfde rol, maar volgorde).
        $this->actingAs($this->ec)->post(route('afstuderen.stap.afvinken', $this->stap($proces, Afstudeerstap::Vakken)));
        $this->assertFalse((bool) $this->stap($proces, Afstudeerstap::Vakken)->fresh()->gereed);

        // Stap 1 eerst, daarna stap 2 wel.
        $this->actingAs($this->ec)->post(route('afstuderen.stap.afvinken', $this->stap($proces, Afstudeerstap::Verzoek)));
        $this->assertTrue((bool) $this->stap($proces, Afstudeerstap::Verzoek)->fresh()->gereed);
        $this->actingAs($this->ec)->post(route('afstuderen.stap.afvinken', $this->stap($proces, Afstudeerstap::Vakken)));
        $this->assertTrue((bool) $this->stap($proces, Afstudeerstap::Vakken)->fresh()->gereed);
    }

    public function test_laatste_stap_studeert_de_student_af(): void
    {
        $proces = $this->startProces();

        // Examencommissie: stap 1-3.
        foreach ([Afstudeerstap::Verzoek, Afstudeerstap::Vakken, Afstudeerstap::StageScriptie] as $s) {
            $this->actingAs($this->ec)->post(route('afstuderen.stap.afvinken', $this->stap($proces, $s)));
        }
        // Studentenzaken: stap 4-5.
        $this->actingAs($this->sz)->post(route('afstuderen.stap.afvinken', $this->stap($proces, Afstudeerstap::DiplomaKlaarmaken)));
        $this->actingAs($this->sz)->post(route('afstuderen.stap.afvinken', $this->stap($proces, Afstudeerstap::DiplomaUitreiken)));

        // Student is nu afgestudeerd + alumnus; proces afgerond.
        $this->assertSame(InschrijvingStatus::Afgestudeerd, $this->insch->fresh()->status);
        $this->assertNotNull($this->insch->fresh()->afstudeerdatum);
        $this->assertSame(Afstudeerproces::AFGEROND, $proces->fresh()->status);
        $this->assertTrue($this->student->fresh()->load('inschrijvingen')->isAlumnus());
    }

    public function test_kandidatenlijst_toont_laatste_jaar_student(): void
    {
        // Een jaar-1 student hoort NIET in de lijst.
        $ander = Student::create(['studentnummer' => '260901', 'voornaam' => 'Eerste', 'achternaam' => 'Jaar']);
        Inschrijving::create([
            'student_id' => $ander->id,
            'opleiding_id' => Opleiding::where('code', 'ISLTH')->value('id'),
            'periode_id' => Periode::where('actief', true)->value('id'),
            'leerjaar' => 1, 'status' => InschrijvingStatus::Actief, 'inschrijfdatum' => '2026-09-01',
        ]);

        $this->actingAs($this->ec)->get(route('afstuderen.kandidaten'))
            ->assertOk()
            ->assertSee('260900')       // laatste jaar: wel
            ->assertDontSee('260901');  // eerste jaar: niet
    }

    public function test_studentenzaken_ziet_afstudeerproces_op_dashboard(): void
    {
        $proces = $this->startProces();

        // Net gestart: stap 1 is aan de examencommissie -> "wacht op examencommissie".
        $this->actingAs($this->sz)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Afstudeerprocessen')
            ->assertSee('260900')
            ->assertSee('wacht op examencommissie');

        // Examencommissie rondt stap 1-3 af -> nu is het de beurt van Studentenzaken.
        foreach ([Afstudeerstap::Verzoek, Afstudeerstap::Vakken, Afstudeerstap::StageScriptie] as $s) {
            $this->actingAs($this->ec)->post(route('afstuderen.stap.afvinken', $this->stap($proces, $s)));
        }
        $this->actingAs($this->sz)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Actie: Studentenzaken');
    }

    public function test_examencommissie_breekt_proces_af(): void
    {
        $proces = $this->startProces();

        $this->actingAs($this->ec)->post(route('afstuderen.proces.afbreken', $proces))->assertRedirect();
        $this->assertSame(Afstudeerproces::AFGEBROKEN, $proces->fresh()->status);
    }
}
