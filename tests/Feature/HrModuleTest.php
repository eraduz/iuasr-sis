<?php

namespace Tests\Feature;

use App\Enums\MedewerkerSoort;
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

    public function test_uit_dienst_vereist_een_uitdienstdatum(): void
    {
        $m = Medewerker::where('personeelsnummer', 'P260005')->firstOrFail();

        $this->actingAs($this->hr)->put(route('medewerkers.update', $m), [
            'voornaam' => $m->voornaam, 'achternaam' => $m->achternaam, 'status' => 'uit_dienst',
        ])->assertSessionHasErrors('uit_dienst_datum');
    }

    public function test_offboarding_sluit_vast_contract_en_zet_niet_actief(): void
    {
        // Verse medewerker met een VAST contract zonder einddatum.
        $this->actingAs($this->hr)->post(route('medewerkers.store'), [
            'voornaam' => 'Vast', 'achternaam' => 'Contract', 'status' => 'actief', 'actief' => '1',
        ])->assertRedirect();
        $m = Medewerker::where('achternaam', 'Contract')->firstOrFail();

        $this->actingAs($this->hr)->post(route('dienstverbanden.store', $m), [
            'contracttype' => 'vast', 'startdatum' => '2020-01-01', 'uren_per_week' => 40,
        ])->assertRedirect();

        // Uit dienst zetten — met datum en reden; 'actief' aangevinkt moet toch false worden.
        $this->actingAs($this->hr)->put(route('medewerkers.update', $m), [
            'voornaam' => 'Vast', 'achternaam' => 'Contract', 'status' => 'uit_dienst',
            'uit_dienst_datum' => '2026-08-31', 'uit_dienst_reden' => 'eigen verzoek', 'actief' => '1',
        ])->assertRedirect(route('medewerkers.show', $m));

        $m->refresh();
        $this->assertSame('2026-08-31', $m->uit_dienst_datum?->toDateString());
        $this->assertSame('eigen verzoek', $m->uit_dienst_reden);
        $this->assertFalse((bool) $m->actief);

        // Het lopende (vaste) contract is afgesloten op de uit-dienstdatum.
        $dv = $m->dienstverbanden()->where('startdatum', '2020-01-01')->firstOrFail();
        $this->assertSame('2026-08-31', $dv->einddatum?->toDateString());
    }

    public function test_terug_in_dienst_wist_de_uitdienstgegevens(): void
    {
        $m = Medewerker::where('personeelsnummer', 'P260005')->firstOrFail();
        $m->update(['status' => 'uit_dienst', 'uit_dienst_datum' => '2026-08-31', 'uit_dienst_reden' => 'x', 'actief' => false]);

        $this->actingAs($this->hr)->put(route('medewerkers.update', $m), [
            'voornaam' => $m->voornaam, 'achternaam' => $m->achternaam, 'status' => 'actief', 'actief' => '1',
        ])->assertRedirect();

        $m->refresh();
        $this->assertNull($m->uit_dienst_datum);
        $this->assertNull($m->uit_dienst_reden);
        $this->assertTrue((bool) $m->actief);
    }

    public function test_nieuwe_medewerker_is_standaard_personeel(): void
    {
        $this->actingAs($this->hr)->post(route('medewerkers.store'), [
            'voornaam' => 'Gewoon', 'achternaam' => 'Personeelslid', 'soort' => 'personeel', 'status' => 'actief', 'actief' => '1',
        ])->assertRedirect();

        $this->assertSame(MedewerkerSoort::Personeel, Medewerker::where('achternaam', 'Personeelslid')->firstOrFail()->soort);
    }

    public function test_vrijwilliger_telt_niet_mee_in_fte(): void
    {
        $this->actingAs($this->hr)->post(route('medewerkers.store'), [
            'voornaam' => 'Vrij', 'achternaam' => 'Williger', 'soort' => 'vrijwilliger', 'status' => 'actief', 'actief' => '1',
        ])->assertRedirect();
        $m = Medewerker::where('achternaam', 'Williger')->firstOrFail();

        // Zelfs mét een volledig dienstverband telt de FTE van een vrijwilliger niet mee.
        $this->actingAs($this->hr)->post(route('dienstverbanden.store', $m), [
            'contracttype' => 'vast', 'startdatum' => '2026-01-01', 'uren_per_week' => 40,
        ])->assertRedirect();

        $m->refresh()->load('dienstverbanden');
        $this->assertTrue($m->isVrijwilliger());
        $this->assertNull($m->fte());
    }

    public function test_zzp_telt_niet_mee_in_fte(): void
    {
        $this->actingAs($this->hr)->post(route('medewerkers.store'), [
            'voornaam' => 'Zelf', 'achternaam' => 'Standig', 'soort' => 'zzp', 'status' => 'actief', 'actief' => '1',
        ])->assertRedirect();
        $m = Medewerker::where('achternaam', 'Standig')->firstOrFail();

        $this->actingAs($this->hr)->post(route('dienstverbanden.store', $m), [
            'contracttype' => 'tijdelijk', 'startdatum' => '2026-01-01', 'uren_per_week' => 40,
        ])->assertRedirect();

        $m->refresh()->load('dienstverbanden');
        $this->assertTrue($m->isZzp());
        $this->assertFalse($m->teltVoorFte());
        $this->assertNull($m->fte());
    }

    public function test_lijst_filtert_op_soort(): void
    {
        $this->actingAs($this->hr)->post(route('medewerkers.store'), [
            'voornaam' => 'Vrij', 'achternaam' => 'Williger', 'soort' => 'vrijwilliger', 'status' => 'actief', 'actief' => '1',
        ])->assertRedirect();

        $this->actingAs($this->hr)->get(route('medewerkers', ['soort' => 'vrijwilliger']))
            ->assertOk()
            ->assertSee('Williger')
            ->assertDontSee('Willemsen'); // seeded personeelslid
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

    public function test_zzp_overeenkomst_uploaden(): void
    {
        Storage::fake('local');
        // Verse ZZP'er; de ZZP-/opdrachtovereenkomst als document opslaan.
        $this->actingAs($this->hr)->post(route('medewerkers.store'), [
            'voornaam' => 'Zelf', 'achternaam' => 'Standig', 'soort' => 'zzp', 'status' => 'actief', 'actief' => '1',
        ])->assertRedirect();
        $zzp = Medewerker::where('achternaam', 'Standig')->firstOrFail();

        $this->actingAs($this->hr)->post(route('hrdocumenten.store', $zzp), [
            'categorie' => 'zzp_overeenkomst',
            'bestand' => UploadedFile::fake()->create('opdrachtovereenkomst.pdf', 40, 'application/pdf'),
        ])->assertRedirect(route('medewerkers.show', $zzp));

        $this->assertDatabaseHas('hr_documenten', ['medewerker_id' => $zzp->id, 'categorie' => 'zzp_overeenkomst']);
    }
}
