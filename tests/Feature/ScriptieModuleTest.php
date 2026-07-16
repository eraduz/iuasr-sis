<?php

namespace Tests\Feature;

use App\Enums\InschrijvingStatus;
use App\Enums\Rol;
use App\Enums\Scriptiestap;
use App\Models\Inschrijving;
use App\Models\Opleiding;
use App\Models\Periode;
use App\Models\Scriptie;
use App\Models\Student;
use App\Models\User;
use App\Support\Scriptietraject;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScriptieModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $coordinator;
    private User $examencommissie;
    private User $financien;
    private User $bestuur;
    private Student $student;
    private Inschrijving $insch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferentieSeeder::class);

        $isltId = Opleiding::where('code', 'ISLTH')->value('id');

        $this->coordinator = User::create(['naam' => 'Coördinator', 'email' => 'coord@iuasr.test', 'rol' => Rol::Scriptiecoordinator]);
        $this->coordinator->opleidingen()->attach($isltId);

        $this->examencommissie = User::create(['naam' => 'EC', 'email' => 'ec@iuasr.test', 'rol' => Rol::Examencommissie]);
        $this->financien = User::create(['naam' => 'Fin', 'email' => 'fin@iuasr.test', 'rol' => Rol::Financien]);
        $this->bestuur = User::create(['naam' => 'Bestuur', 'email' => 'best@iuasr.test', 'rol' => Rol::Bestuur]);

        $this->student = Student::create(['studentnummer' => '260901', 'voornaam' => 'Scriptie', 'achternaam' => 'Ganger']);
        $this->insch = Inschrijving::create([
            'student_id' => $this->student->id,
            'opleiding_id' => $isltId,
            'periode_id' => Periode::where('actief', true)->value('id'),
            'leerjaar' => 4,
            'status' => InschrijvingStatus::Actief,
            'inschrijfdatum' => '2026-09-01',
        ]);
    }

    private function startTraject(): Scriptie
    {
        return Scriptietraject::start($this->insch->fresh(), $this->coordinator);
    }

    public function test_module_verschijnt_op_het_keuzescherm(): void
    {
        $this->actingAs($this->coordinator)->get(route('modules.kiezen'))->assertOk()->assertSee('Scriptie Coördinatie');
    }

    public function test_coordinator_ziet_dashboard_kandidaten_en_trajecten(): void
    {
        $this->actingAs($this->coordinator)->get(route('scriptie.dashboard'))->assertOk();
        $this->actingAs($this->coordinator)->get(route('scriptie.kandidaten'))->assertOk();
        $this->actingAs($this->coordinator)->get(route('scriptie.trajecten'))->assertOk();
    }

    public function test_start_traject_seedt_elf_stappen_en_checklistpunten(): void
    {
        $this->actingAs($this->coordinator)->post(route('scriptie.start', $this->insch))->assertRedirect();

        $scriptie = $this->insch->scriptie()->firstOrFail();
        $this->assertSame(11, $scriptie->stapstanden()->count());
        // 8 (onderwerp) + 14 (inlevering) + 8 (beoordeling) + 7 (afronding) = 37
        $this->assertSame(37, $scriptie->checklistpunten()->count());
        $this->assertDatabaseHas('audit_logs', ['veld' => 'scriptietraject', 'actie' => 'aanmaak']);
    }

    public function test_trajectpagina_rendert(): void
    {
        $scriptie = $this->startTraject();
        $this->actingAs($this->coordinator)->get(route('scriptie.show', $scriptie))->assertOk()
            ->assertSee($scriptie->scriptienummer);
    }

    public function test_financien_heeft_geen_toegang(): void
    {
        $scriptie = $this->startTraject();
        $this->actingAs($this->financien)->get(route('scriptie.dashboard'))->assertForbidden();
        $this->actingAs($this->financien)->get(route('scriptie.show', $scriptie))->assertForbidden();
        $this->actingAs($this->financien)->post(route('scriptie.start', $this->insch))->assertForbidden();
        $this->assertFalse($this->financien->magScriptieInzien());
    }

    public function test_stapformulier_slaat_velden_op(): void
    {
        $scriptie = $this->startTraject();
        $this->actingAs($this->coordinator)
            ->put(route('scriptie.stap.update', ['scriptie' => $scriptie, 'stap' => Scriptiestap::Voorstel->value]), [
                'titel_voorlopig' => 'Waqf in de moderne tijd',
                'taal' => 'Nederlands',
            ])->assertRedirect();

        $this->assertDatabaseHas('scripties', ['id' => $scriptie->id, 'titel_voorlopig' => 'Waqf in de moderne tijd', 'taal' => 'Nederlands']);
    }

    public function test_stappen_worden_sequentieel_afgevinkt(): void
    {
        $scriptie = $this->startTraject();

        // Stap 2 (voorstel) kan niet vóór stap 1 (toelating) worden afgevinkt.
        $this->actingAs($this->coordinator)
            ->post(route('scriptie.stap.afvinken', ['scriptie' => $scriptie, 'stap' => Scriptiestap::Voorstel->value]))
            ->assertRedirect();
        $this->assertFalse($scriptie->stapstanden()->where('stap', 'voorstel')->value('gereed'));

        // Stap 1 kan wel: de coördinator is verantwoordelijk voor de toelating.
        $this->actingAs($this->coordinator)
            ->post(route('scriptie.stap.afvinken', ['scriptie' => $scriptie, 'stap' => Scriptiestap::Toelating->value]))
            ->assertRedirect();
        $this->assertTrue((bool) $scriptie->stapstanden()->where('stap', 'toelating')->value('gereed'));
    }

    public function test_academische_stap_alleen_door_examencommissie_afvinkbaar(): void
    {
        $scriptie = $this->startTraject();
        // Zet de voorafgaande stappen op gereed zodat de volgorde klopt.
        $scriptie->stapstanden()->whereIn('stap', ['toelating', 'voorstel'])->update(['gereed' => true]);

        // De coördinator mag de onderwerpbeoordeling (examencommissie) NIET afvinken.
        $this->actingAs($this->coordinator)
            ->post(route('scriptie.stap.afvinken', ['scriptie' => $scriptie, 'stap' => Scriptiestap::Onderwerpbeoordeling->value]))
            ->assertForbidden();

        // De examencommissie wel.
        $this->actingAs($this->examencommissie)
            ->post(route('scriptie.stap.afvinken', ['scriptie' => $scriptie, 'stap' => Scriptiestap::Onderwerpbeoordeling->value]))
            ->assertRedirect();
        $this->assertTrue((bool) $scriptie->stapstanden()->where('stap', 'onderwerpbeoordeling')->value('gereed'));
    }

    public function test_checklist_wordt_opgeslagen(): void
    {
        $scriptie = $this->startTraject();
        $punt = $scriptie->checklistpunten()->where('stap', 'onderwerpbeoordeling')->firstOrFail();

        $this->actingAs($this->examencommissie)
            ->put(route('scriptie.stap.checklist', ['scriptie' => $scriptie, 'stap' => Scriptiestap::Onderwerpbeoordeling->value]), [
                'waarde' => [$punt->id => 'ja'],
                'toelichting' => [$punt->id => 'Duidelijk afgebakend.'],
            ])->assertRedirect();

        $this->assertTrue((bool) $punt->fresh()->waarde);
        $this->assertSame('Duidelijk afgebakend.', $punt->fresh()->toelichting);
    }

    public function test_bestuur_leest_mee_maar_muteert_niet(): void
    {
        $scriptie = $this->startTraject();
        $this->actingAs($this->bestuur)->get(route('scriptie.show', $scriptie))->assertOk();
        // Bestuur zit niet in de mutatie-middlewaregroep.
        $this->actingAs($this->bestuur)
            ->post(route('scriptie.stap.afvinken', ['scriptie' => $scriptie, 'stap' => Scriptiestap::Toelating->value]))
            ->assertForbidden();
    }

    public function test_afronden_sluit_het_traject(): void
    {
        $scriptie = $this->startTraject();
        // Alle stappen behalve de afronding op gereed zetten.
        $scriptie->stapstanden()->where('stap', '!=', Scriptiestap::Afronding->value)->update(['gereed' => true]);

        $this->actingAs($this->coordinator)
            ->post(route('scriptie.stap.afvinken', ['scriptie' => $scriptie, 'stap' => Scriptiestap::Afronding->value]))
            ->assertRedirect();

        $this->assertSame(Scriptie::AFGEROND, $scriptie->fresh()->status);
        $this->assertNotNull($scriptie->fresh()->afgerond_op);
    }
}
