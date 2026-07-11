<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\OrganisatieSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module Relatiebeheer & Stagebeheer — Fase G (dashboard & rapportage). Bewaakt
 * het dashboard, de rapportage, de CSV-export en de opleidinggebonden scoping.
 */
class RelatieDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $relatiebeheerder; // PABO
    private User $stagecoordinator; // MGV

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class, SynthetischeStudentSeeder::class, OrganisatieSeeder::class]);

        $this->relatiebeheerder = User::where('email', 'l.haddad@iuasr.nl')->firstOrFail(); // PABO
        $this->stagecoordinator = User::where('email', 'j.prins@iuasr.nl')->firstOrFail(); // MGV
    }

    public function test_module_start_route_is_het_dashboard(): void
    {
        $this->assertSame('relatiebeheer.dashboard', Module::where('sleutel', 'relatiebeheer')->first()->startRoute());
    }

    public function test_dashboard_toont_kerncijfers(): void
    {
        $this->actingAs($this->relatiebeheerder)->get(route('relatiebeheer.dashboard'))
            ->assertOk()
            ->assertSee('Relatiebeheer &amp; Stage', false)
            ->assertSee('Organisaties')
            ->assertSee('Lopende stages');
    }

    public function test_rapport_is_opleidinggebonden_gescoped(): void
    {
        // Relatiebeheerder is PABO: ziet de PABO-organisatie, niet de MGV-organisatie.
        $this->actingAs($this->relatiebeheerder)->get(route('relatiebeheer.rapport'))
            ->assertOk()
            ->assertSee('Basisschool De Regenboog')
            ->assertDontSee('Zorggroep Rijnmond');

        // De stagecoördinator (MGV) ziet de MGV-organisatie wél.
        $this->actingAs($this->stagecoordinator)->get(route('relatiebeheer.rapport'))
            ->assertOk()
            ->assertSee('Zorggroep Rijnmond')
            ->assertDontSee('Basisschool De Regenboog');
    }

    public function test_csv_export(): void
    {
        $response = $this->actingAs($this->relatiebeheerder)->get(route('relatiebeheer.rapport.export'));
        $response->assertOk();
        $this->assertStringContainsString('relatienummer', $response->streamedContent());
        $this->assertStringContainsString('Basisschool De Regenboog', $response->streamedContent());
    }

    public function test_studentenzaken_heeft_geen_toegang(): void
    {
        $sz = User::where('rol', Rol::Studentenzaken)->firstOrFail();
        $this->actingAs($sz)->get(route('relatiebeheer.dashboard'))->assertForbidden();
    }
}
