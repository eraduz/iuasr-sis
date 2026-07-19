<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\HrDocument;
use App\Models\Medewerker;
use App\Models\User;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\HrSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Module HR / Personeelszaken — Fase F (self-service "Mijn HR" & iCal-agenda).
 * Bewaakt dat elke gekoppelde medewerker het eigen dossier ziet (ook zonder
 * HR-rol), de eigen agenda als iCal kan downloaden en uitsluitend de eigen
 * documenten kan ophalen.
 */
class HrMijnTest extends TestCase
{
    use RefreshDatabase;

    private User $self;          // Sophie Willemsen — medewerker zonder HR-rol
    private Medewerker $sophie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class, HrSeeder::class]);

        $this->sophie = Medewerker::where('personeelsnummer', 'P260003')->firstOrFail();
        $this->self = User::create(['naam' => 'Sophie Willemsen', 'email' => 's.willemsen@iuasr.nl', 'rol' => Rol::Docent]);
        $this->sophie->update(['user_id' => $this->self->id]);
    }

    public function test_zelfservice_toont_eigen_dossier(): void
    {
        $this->actingAs($this->self)->get(route('hr.mijn'))
            ->assertOk()
            ->assertSee('Mijn HR')
            ->assertSee('P260003')
            ->assertSee('Verlofsaldo');
    }

    public function test_zelfservice_zonder_hr_rol_geen_hr_beheer(): void
    {
        // Een gewone medewerker (Docent) heeft geen toegang tot de HR-beheerlijst.
        $this->actingAs($this->self)->get(route('medewerkers'))->assertForbidden();
    }

    public function test_keuzescherm_toont_hr_aan_medewerker_zonder_hr_rol(): void
    {
        // Een docent met personeelsdossier moet de HR-module op het keuzescherm
        // zien om bij de zelfservice te komen, en opent daar 'Mijn HR' — niet het
        // HR-dashboard, waarvoor hij geen rechten heeft.
        $hr = \App\Models\Module::where('sleutel', 'hr')->firstOrFail();

        $this->assertTrue($hr->toegankelijkVoor($this->self));
        $this->assertTrue($hr->isZelfserviceVoor($this->self));
        $this->assertSame('hr.mijn', $hr->startRoute($this->self));

        $this->actingAs($this->self)->get(route('modules.kiezen'))
            ->assertOk()
            ->assertSee(route('hr.mijn'), false)
            // Exacte href: route('hr.dashboard') is '/hr' en dus een prefix van
            // '/hr/mijn' — zonder de aanhalingstekens matcht de toets altijd.
            ->assertDontSee('href="'.route('hr.dashboard').'"', false);
    }

    public function test_hr_module_blijft_dicht_zonder_personeelsdossier(): void
    {
        $sz = User::where('rol', Rol::Studentenzaken)->firstOrFail(); // geen medewerker
        $hr = \App\Models\Module::where('sleutel', 'hr')->firstOrFail();

        $this->assertFalse($hr->toegankelijkVoor($sz));
    }

    public function test_zelfservice_toont_geen_hr_beheerlinks_in_het_menu(): void
    {
        // De docent ziet in "Mijn HR" uitsluitend zijn zelfservice-links. Het
        // volledige HR-menu (medewerkers, signaleringen, organisatie) hoort daar
        // niet: die schermen zijn niet van hem en geven een 403.
        $response = $this->actingAs($this->self)->get(route('hr.mijn'))->assertOk();

        $response->assertSee(route('verlof.mijn'), false);
        $response->assertDontSee(route('medewerkers'), false);
        $response->assertDontSee(route('hr.signaleringen'), false);
        $response->assertDontSee(route('hr.organisatie'), false);
        $response->assertDontSee('href="'.route('hr.dashboard').'"', false);
    }

    public function test_hr_medewerker_houdt_het_volledige_hr_menu(): void
    {
        $hrUser = User::where('rol', Rol::Hrmedewerker)->firstOrFail();

        $this->actingAs($hrUser)->get(route('hr.dashboard'))
            ->assertOk()
            ->assertSee(route('medewerkers'), false)
            ->assertSee(route('hr.signaleringen'), false)
            ->assertSee(route('hr.mijn'), false);
    }

    public function test_hr_menu_staat_in_onderwerpsgroepen_op_volgorde(): void
    {
        // Het HR-menu is opgesplitst per onderwerp (zoals de bibliotheekmodule) en
        // staat in een vaste volgorde. Zonder deze toets zakt het terug naar één
        // lange lijst in de volgorde waarin items toevallig zijn bijgeplakt.
        $html = $this->actingAs(User::where('rol', Rol::Hrmedewerker)->firstOrFail())
            ->get(route('hr.dashboard'))->assertOk()->getContent();

        $groepen = ['Overzicht', 'Personeel', 'Verzuim &amp; verlof', 'Ontwikkeling', 'Rapportage', 'Zelfservice'];
        $vorige = -1;
        foreach ($groepen as $groep) {
            $plek = strpos($html, '__title">'.$groep.'<');
            $this->assertNotFalse($plek, "Menugroep '{$groep}' ontbreekt in het HR-menu");
            $this->assertGreaterThan($vorige, $plek, "Menugroep '{$groep}' staat op de verkeerde plek");
            $vorige = $plek;
        }
    }

    public function test_gebruiker_zonder_dossier_geen_zelfservice(): void
    {
        $sz = User::where('rol', Rol::Studentenzaken)->firstOrFail(); // geen medewerker
        $this->actingAs($sz)->get(route('hr.mijn'))->assertForbidden();
    }

    public function test_agenda_ics_download(): void
    {
        // Sophie heeft een gepland gesprek én een goedgekeurde verlofaanvraag (seed).
        $response = $this->actingAs($this->self)->get(route('hr.mijn.agenda'));

        $response->assertOk();
        $this->assertStringContainsString('text/calendar', $response->headers->get('content-type'));
        $ics = $response->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('Verlof', $ics);
    }

    public function test_eigen_document_wel_andermans_niet(): void
    {
        Storage::fake('local');

        $eigen = HrDocument::create([
            'medewerker_id' => $this->sophie->id, 'categorie' => 'contract',
            'bestandsnaam' => 'contract.pdf', 'pad' => 'hr-documenten/eigen.pdf', 'mime' => 'application/pdf', 'grootte' => 10,
        ]);
        Storage::disk('local')->put('hr-documenten/eigen.pdf', 'x');

        $ander = Medewerker::where('personeelsnummer', 'P260005')->firstOrFail();
        $andermans = HrDocument::create([
            'medewerker_id' => $ander->id, 'categorie' => 'contract',
            'bestandsnaam' => 'geheim.pdf', 'pad' => 'hr-documenten/ander.pdf', 'mime' => 'application/pdf', 'grootte' => 10,
        ]);

        $this->actingAs($this->self)->get(route('hr.mijn.document', $eigen))->assertOk();
        $this->actingAs($this->self)->get(route('hr.mijn.document', $andermans))->assertForbidden();
    }
}
