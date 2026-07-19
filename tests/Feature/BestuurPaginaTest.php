<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Database\Seeders\SynthetischVakSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Globale bestuurspagina en de snelkoppelingen op de modulekiezer: het
 * Schoolbestuur krijgt een instellingsbreed overzicht, de Beheerder bereikt de
 * systeemtaken (back-up, gebruikers, audit-log) rechtstreeks vanaf de hoofdpagina.
 */
class BestuurPaginaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, GebruikerSeeder::class]);
    }

    private function user(Rol $rol): User
    {
        return User::where('rol', $rol)->firstOrFail();
    }

    public function test_bestuur_kan_de_bestuurspagina_openen(): void
    {
        $this->actingAs($this->user(Rol::Bestuur))->get(route('bestuur'))
            ->assertOk()->assertSee('globaal overzicht', false)->assertSee('Studiesucces');
    }

    public function test_beheerder_kan_de_bestuurspagina_openen(): void
    {
        $this->actingAs($this->user(Rol::Beheerder))->get(route('bestuur'))->assertOk();
    }

    public function test_andere_rollen_mogen_niet_bij_de_bestuurspagina(): void
    {
        foreach ([Rol::Studentenzaken, Rol::Financien, Rol::Docent, Rol::Directie, Rol::Cursusadministratie] as $rol) {
            $this->actingAs($this->user($rol))->get(route('bestuur'))->assertForbidden();
        }
    }

    public function test_bestuurspagina_rendert_met_data(): void
    {
        // Met synthetische studenten/vakken/personeel mogen de aggregaties van álle
        // modules niet breken; de HR-seeder vult de personeelscijfers.
        $this->seed([SynthetischVakSeeder::class, SynthetischeStudentSeeder::class,
            \Database\Seeders\DocentSeeder::class, \Database\Seeders\HrSeeder::class]);

        $this->actingAs($this->user(Rol::Bestuur))->get(route('bestuur'))
            ->assertOk()->assertSee('Studenten per opleiding')->assertSee('Cursussen');
    }

    public function test_bestuurspagina_toont_alle_rubrieken(): void
    {
        // De vijf hoofdlijnen bundelen alle modules op één pagina.
        $this->actingAs($this->user(Rol::Bestuur))->get(route('bestuur'))
            ->assertOk()
            ->assertSee('Studenten &amp; onderwijs', false)
            ->assertSee('Financiën')
            ->assertSee('Cursussen')
            ->assertSee('Relatiebeheer &amp; stage', false)
            ->assertSee('HR / Personeelszaken');
    }

    public function test_financien_toont_collegegeld_en_cursusgeld_apart(): void
    {
        // De financiële rubriek moet ondubbelzinnig beide geldstromen tonen:
        // collegegeld (opleidingen) én cursusgelden (cursussen), plus het totaal.
        $this->actingAs($this->user(Rol::Bestuur))->get(route('bestuur'))
            ->assertOk()
            ->assertSee('Collegegeld — opleidingen', false)
            ->assertSee('Cursusgelden — cursussen', false)
            ->assertSee('Totaal voldaan');
    }

    public function test_modulekiezer_toont_systeembeheer_voor_beheerder(): void
    {
        $this->actingAs($this->user(Rol::Beheerder))->get(route('modules.kiezen'))
            ->assertOk()->assertSee('Systeembeheer')->assertSee('Back-up')->assertSee('Audit-log');
    }

    public function test_modulekiezer_toont_bestuurstegel_voor_bestuur(): void
    {
        $this->actingAs($this->user(Rol::Bestuur))->get(route('modules.kiezen'))
            ->assertOk()->assertSee('Globaal overzicht')->assertDontSee('Systeembeheer');
    }

    public function test_modulekiezer_toont_geen_systeembeheer_voor_studentenzaken(): void
    {
        $this->actingAs($this->user(Rol::Studentenzaken))->get(route('modules.kiezen'))
            ->assertOk()->assertDontSee('Systeembeheer');
    }

    public function test_bestuur_menu_bevat_directe_rapportagelinks(): void
    {
        // De rapportages van andere modules staan als directe links in het
        // Bestuur-menu, zodat het Bestuur er niet voor in een andere module hoeft.
        $this->actingAs($this->user(Rol::Bestuur))->get(route('bestuur'))
            ->assertOk()
            ->assertSee('Cursusrapportage')
            ->assertSee('HR verzuim &amp; verlof', false);
    }

    public function test_bestuur_behoudt_eigen_menu_op_modulerapport(): void
    {
        // Kern van de fix: op een modulerapport (hier het HR-rapport) houdt het
        // Bestuur zijn eigen menu en krijgt het NIET het HR-module-menu met
        // beheerlinks te zien die 403 zouden geven.
        $this->seed([\Database\Seeders\DocentSeeder::class, \Database\Seeders\HrSeeder::class]);

        // Toets op de menulinks zelf, niet op de tekst 'HR / Personeelszaken':
        // dat is sinds 2026-07-19 ook de modulenaam in de header, en die hoort er
        // juist wél te staan als u een HR-rapport bekijkt.
        $this->actingAs($this->user(Rol::Bestuur))->get(route('hr.rapport'))
            ->assertOk()
            ->assertSee('Bestuursoverzicht')
            ->assertDontSee(route('medewerkers'), false)
            ->assertDontSee(route('hr.signaleringen'), false);
    }

    public function test_hr_medewerker_ziet_wel_het_modulemenu(): void
    {
        // Regressiecheck: voor de HR-medewerker (die de beheerlinks wél mag) blijft
        // het HR-module-menu gewoon verschijnen. Sinds 2026-07-19 staat dat menu in
        // onderwerpsgroepen in plaats van één groep 'HR / Personeelszaken'.
        $this->seed([\Database\Seeders\DocentSeeder::class, \Database\Seeders\HrSeeder::class]);

        $hr = User::where('email', 'n.aslan@iuasr.nl')->firstOrFail();
        $this->actingAs($hr)->get(route('hr.rapport'))
            ->assertOk()
            ->assertSee('Personeel')
            ->assertSee(route('medewerkers'), false);
    }
}
