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
