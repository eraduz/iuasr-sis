<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Medewerker;
use App\Models\User;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\HrSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Module HR / Personeelszaken — Fase A (medewerkersregistratie). Bewaakt de
 * moduletoegang, de CRUD en de FTE-berekening. HR-medewerker en Manager zijn
 * samengevoegd tot één rol die alle medewerkers ziet.
 */
class HrModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;           // HR-medewerker (Nadia Aslan)
    private User $leidingg;     // HR-medewerker/leidinggevende (Ruben Smit)

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class, HrSeeder::class]);

        $this->hr = User::where('email', 'n.aslan@iuasr.nl')->firstOrFail();
        $this->leidingg = User::where('email', 'r.smit@iuasr.nl')->firstOrFail();
    }

    public function test_hr_ziet_de_medewerkerslijst(): void
    {
        $this->actingAs($this->hr)->get(route('medewerkers'))->assertOk();
    }

    public function test_studentenzaken_heeft_geen_toegang(): void
    {
        $sz = User::where('rol', Rol::Studentenzaken)->firstOrFail();
        $this->actingAs($sz)->get(route('medewerkers'))->assertForbidden();
    }

    public function test_module_verschijnt_op_het_keuzescherm(): void
    {
        $this->actingAs($this->hr)->get(route('modules.kiezen'))->assertOk()->assertSee('HR / Personeelszaken');
    }

    public function test_medewerker_aanmaken_genereert_personeelsnummer(): void
    {
        $this->actingAs($this->hr)->post(route('medewerkers.store'), [
            'voornaam' => 'Test',
            'achternaam' => 'Nieuw',
            'status' => 'actief',
            'actief' => '1',
        ])->assertRedirect();

        $medewerker = Medewerker::where('achternaam', 'Nieuw')->firstOrFail();
        $this->assertStringStartsWith('P', $medewerker->personeelsnummer);
    }

    public function test_dienstverband_berekent_fte(): void
    {
        $medewerker = Medewerker::where('personeelsnummer', 'P260005')->firstOrFail();

        $this->actingAs($this->hr)->post(route('dienstverbanden.store', $medewerker), [
            'contracttype' => 'vast',
            'startdatum' => '2026-01-01',
            'uren_per_week' => 20,
        ])->assertRedirect(route('medewerkers.show', $medewerker));

        $dienstverband = $medewerker->dienstverbanden()->where('startdatum', '2026-01-01')->firstOrFail();
        $this->assertSame(0.5, $dienstverband->fte()); // 20 ÷ 40
    }

    public function test_gecombineerde_hr_rol_ziet_alle_medewerkers(): void
    {
        // Sinds het samenvoegen van HR-medewerker en Manager is er geen team-scoping
        // meer: de gecombineerde rol ziet iedereen, ook buiten het eigen team.
        $zichtbaar = Medewerker::query()->zichtbaarVoor($this->leidingg)->pluck('achternaam');

        $this->assertTrue($zichtbaar->contains('Willemsen'));
        $this->assertTrue($zichtbaar->contains('Smit'));
        $this->assertTrue($zichtbaar->contains('Bakker'));
    }

    public function test_gecombineerde_hr_rol_kan_medewerker_aanmaken(): void
    {
        $this->actingAs($this->leidingg)->get(route('medewerkers.create'))->assertOk();
    }

    public function test_document_uploaden(): void
    {
        Storage::fake('local');
        $medewerker = Medewerker::where('personeelsnummer', 'P260003')->firstOrFail();

        $this->actingAs($this->hr)->post(route('hrdocumenten.store', $medewerker), [
            'categorie' => 'contract',
            'bestand' => UploadedFile::fake()->create('contract.pdf', 30, 'application/pdf'),
        ])->assertRedirect(route('medewerkers.show', $medewerker));

        $this->assertDatabaseHas('hr_documenten', ['medewerker_id' => $medewerker->id, 'categorie' => 'contract']);
    }
}
