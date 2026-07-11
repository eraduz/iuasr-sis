<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Contactmoment;
use App\Models\User;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\OrganisatieSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module Relatiebeheer & Stagebeheer — Fase H (slimme functies): globaal zoeken,
 * iCal-export van de agenda en contactmoment → opvolgtaak.
 */
class RelatieSlimmeFunctiesTest extends TestCase
{
    use RefreshDatabase;

    private User $relatiebeheerder; // PABO

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class, SynthetischeStudentSeeder::class, OrganisatieSeeder::class]);

        $this->relatiebeheerder = User::where('rol', Rol::Relatiebeheerder)->firstOrFail();
    }

    public function test_globaal_zoeken_vindt_en_scoopt(): void
    {
        $this->actingAs($this->relatiebeheerder)->get(route('relatiebeheer.zoeken', ['q' => 'Regenboog']))
            ->assertOk()
            ->assertSee('Basisschool De Regenboog');

        // Opleidinggebonden: een MGV-organisatie hoort de PABO-relatiebeheerder niet te vinden.
        $this->actingAs($this->relatiebeheerder)->get(route('relatiebeheer.zoeken', ['q' => 'Zorggroep']))
            ->assertOk()
            ->assertDontSee('Zorggroep Rijnmond');
    }

    public function test_ical_export_van_de_agenda(): void
    {
        $response = $this->actingAs($this->relatiebeheerder)->get(route('relatiebeheer.agenda.ics'));

        $response->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->assertStringContainsString('BEGIN:VCALENDAR', $response->getContent());
        $this->assertStringContainsString('Stagebezoek', $response->getContent());
    }

    public function test_contactmoment_met_vervolgdatum_wordt_een_taak(): void
    {
        $contactmoment = Contactmoment::where('onderwerp', 'Kennismakingsgesprek nieuwe stageperiode')->firstOrFail();

        $this->actingAs($this->relatiebeheerder)->post(route('contactmomenten.taak', $contactmoment))
            ->assertRedirect(route('relaties.show', $contactmoment->organisatie));

        $this->assertDatabaseHas('relatie_taken', [
            'organisatie_id' => $contactmoment->organisatie_id,
            'titel' => 'Opvolging: Kennismakingsgesprek nieuwe stageperiode',
        ]);
    }

    public function test_contactmoment_zonder_vervolgdatum_geeft_geen_taak(): void
    {
        $contactmoment = Contactmoment::where('onderwerp', 'Telefonisch: beschikbaarheid werkplekbegeleiders')->firstOrFail();

        $this->actingAs($this->relatiebeheerder)->post(route('contactmomenten.taak', $contactmoment))
            ->assertStatus(422);
    }

    public function test_directie_kan_geen_taak_van_contactmoment_maken(): void
    {
        $contactmoment = Contactmoment::where('onderwerp', 'Kennismakingsgesprek nieuwe stageperiode')->firstOrFail();
        $paboDirectie = User::where('email', 'm.groen@iuasr.nl')->firstOrFail();

        // Directie zit niet in de beheergroep van de module → geen toegang tot deze actie.
        $this->actingAs($paboDirectie)->post(route('contactmomenten.taak', $contactmoment))->assertForbidden();
    }
}
