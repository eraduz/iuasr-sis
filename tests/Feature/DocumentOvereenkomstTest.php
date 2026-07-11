<?php

namespace Tests\Feature;

use App\Enums\OvereenkomstStatus;
use App\Enums\Rol;
use App\Models\Organisatie;
use App\Models\Overeenkomst;
use App\Models\RelatieDocument;
use App\Models\User;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\OrganisatieSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Module Relatiebeheer & Stagebeheer — Fase F (documenten & overeenkomsten).
 * Bewaakt de upload/versiebeheer, de ondertekening en de scoping.
 */
class DocumentOvereenkomstTest extends TestCase
{
    use RefreshDatabase;

    private User $relatiebeheerder; // PABO
    private Organisatie $paboOrg;   // R260001
    private Organisatie $mgvOrg;    // R260003

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed([ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class, OrganisatieSeeder::class]);

        $this->relatiebeheerder = User::where('email', 'l.haddad@iuasr.nl')->firstOrFail(); // PABO
        $this->paboOrg = Organisatie::where('relatienummer', 'R260001')->firstOrFail();
        $this->mgvOrg = Organisatie::where('relatienummer', 'R260003')->firstOrFail();
    }

    public function test_relatiekaart_toont_documenten_en_overeenkomsten(): void
    {
        $this->actingAs($this->relatiebeheerder)->get(route('relaties.show', $this->paboOrg))
            ->assertOk()
            ->assertSee('Overeenkomsten')
            ->assertSee('Documenten')
            ->assertSee('Samenwerkingsovereenkomst 2026');
    }

    public function test_document_uploaden_en_nieuwe_versie(): void
    {
        $this->actingAs($this->relatiebeheerder)->post(route('relatiedocumenten.store', $this->paboOrg), [
            'categorie' => 'convenant',
            'bestand' => UploadedFile::fake()->create('convenant.pdf', 40, 'application/pdf'),
        ])->assertRedirect(route('relaties.show', $this->paboOrg));

        $document = RelatieDocument::where('organisatie_id', $this->paboOrg->id)->firstOrFail();
        $this->assertSame(1, $document->versie);
        Storage::disk('local')->assertExists($document->pad);

        // Nieuwe versie.
        $this->actingAs($this->relatiebeheerder)->post(route('relatiedocumenten.versie', $document), [
            'bestand' => UploadedFile::fake()->create('convenant-v2.pdf', 45, 'application/pdf'),
        ])->assertRedirect();

        $v2 = RelatieDocument::where('vorige_versie_id', $document->id)->firstOrFail();
        $this->assertSame(2, $v2->versie);
        $this->assertFalse($document->fresh()->isHuidigeVersie());
        $this->assertTrue($v2->isHuidigeVersie());
    }

    public function test_overeenkomst_aanmaken_zonder_pdf(): void
    {
        $this->actingAs($this->relatiebeheerder)->post(route('overeenkomsten.store', $this->paboOrg), [
            'type' => 'convenant',
            'status' => 'concept',
        ])->assertRedirect(route('relaties.show', $this->paboOrg));

        $this->assertDatabaseHas('overeenkomsten', [
            'organisatie_id' => $this->paboOrg->id,
            'type' => 'convenant',
            'status' => 'concept',
        ]);
    }

    public function test_overeenkomst_met_pdf_wordt_gewaarmerkt(): void
    {
        $this->actingAs($this->relatiebeheerder)->post(route('overeenkomsten.store', $this->paboOrg), [
            'type' => 'stagecontract',
            'status' => 'concept',
            'bestand' => UploadedFile::fake()->create('contract.pdf', 60, 'application/pdf'),
        ])->assertRedirect(route('relaties.show', $this->paboOrg));

        $overeenkomst = Overeenkomst::where('organisatie_id', $this->paboOrg->id)->where('type', 'stagecontract')->firstOrFail();
        $this->assertNotNull($overeenkomst->ondertekend_document_id);
        $this->assertSame(OvereenkomstStatus::Getekend, $overeenkomst->status);
        $this->assertDatabaseHas('ondertekende_documenten', ['id' => $overeenkomst->ondertekend_document_id]);
    }

    public function test_scoping_geen_upload_bij_vreemde_organisatie(): void
    {
        $this->actingAs($this->relatiebeheerder)->post(route('relatiedocumenten.store', $this->mgvOrg), [
            'categorie' => 'overig',
            'bestand' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])->assertForbidden();
    }

    public function test_verlopende_overeenkomst_op_de_planning(): void
    {
        // Een overeenkomst die binnenkort verloopt verschijnt op de planning.
        Overeenkomst::create([
            'organisatie_id' => $this->paboOrg->id,
            'type' => 'convenant',
            'status' => 'getekend',
            'verloopdatum' => now()->addDays(20)->toDateString(),
        ]);

        $this->actingAs($this->relatiebeheerder)->get(route('agenda'))
            ->assertOk()
            ->assertSee('Contracten die verlopen')
            ->assertSee($this->paboOrg->naam);
    }
}
