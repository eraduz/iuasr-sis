<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use App\Support\Statistiek;
use Database\Seeders\CollegegeldSeeder;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischVakSeeder;
use Database\Seeders\ResultatenSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Database\Seeders\VaktoewijzingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStatistiekTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            ReferentieSeeder::class, SynthetischVakSeeder::class, DocentSeeder::class, GebruikerSeeder::class,
            SynthetischeStudentSeeder::class, VaktoewijzingSeeder::class,
            ResultatenSeeder::class, CollegegeldSeeder::class,
        ]);
    }

    public function test_statistiek_levert_bruikbare_aggregaties(): void
    {
        $kern = Statistiek::kern();
        $this->assertArrayHasKey('studenten', $kern);
        $this->assertGreaterThan(0, $kern['studenten']);

        $this->assertIsList(Statistiek::perOpleiding());
        $this->assertIsList(Statistiek::cijferverdeling());

        $slaag = Statistiek::slaagpercentage();
        $this->assertGreaterThanOrEqual(0, $slaag['percentage']);
        $this->assertLessThanOrEqual(100, $slaag['percentage']);

        $fin = Statistiek::financieel();
        $this->assertArrayHasKey('verschuldigd', $fin);
        $this->assertArrayHasKey('betaalgraad', $fin);
    }

    public function test_alle_rol_dashboards_renderen(): void
    {
        foreach (Rol::cases() as $rol) {
            $user = User::where('rol', $rol)->first();
            if (! $user) {
                continue;
            }
            $antwoord = $this->actingAs($user)->get(route('dashboard'));

            if ($rol === Rol::Bestuur) {
                // Het Schoolbestuur wordt doorgestuurd naar het samengevoegde overzicht.
                $antwoord->assertRedirect(route('bestuur'));
            } elseif ($rol->magModule('studentenzaken')) {
                $antwoord->assertOk();
            } elseif ($rol->magModule('cursussen')) {
                // Cursusadministratie wordt naar de Cursussen-module gestuurd.
                $antwoord->assertRedirect(route('cursussen.dashboard'));
            } elseif ($rol->magModule('relatiebeheer')) {
                // Relatiebeheerder/Stagecoördinator naar het Relatiebeheer-dashboard.
                $antwoord->assertRedirect(route('relatiebeheer.dashboard'));
            } elseif ($rol->magModule('hr')) {
                $antwoord->assertRedirect(route('hr.dashboard'));
            } elseif ($rol->magModule('balie')) {
                $antwoord->assertRedirect(route('balie.dashboard'));
            } elseif ($rol->magModule('bibliotheek')) {
                $antwoord->assertRedirect(route('bibliotheek.dashboard'));
            } else {
                // Scriptiecoördinator naar het scriptieoverzicht.
                $antwoord->assertRedirect(route('scriptie.dashboard'));
            }
        }
    }

    public function test_studentenzaken_dashboard_bevat_geen_cijferstatistiek(): void
    {
        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Studenten per opleiding')
            ->assertDontSee('Slaagpercentage')
            ->assertDontSee('Cijferverdeling');
    }

    public function test_examencommissie_dashboard_toont_slaag_en_cijferverdeling(): void
    {
        $this->actingAs(User::where('rol', Rol::Examencommissie)->first())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Slaagpercentage')
            ->assertSee('Cijferverdeling');
    }

    public function test_vrijstellingslijst_zichtbaar_voor_onderwijsrollen(): void
    {
        // De seeder legt één demo-vrijstelling vast (student 261001). Het Bestuur
        // heeft geen studentenzaken-dashboard meer (samengevoegd overzicht), dus
        // de vrijstellingslijst wordt hier voor de onderwijsrollen getoetst.
        foreach ([Rol::Studentenzaken, Rol::Docent, Rol::Examencommissie, Rol::Directie] as $rol) {
            $this->actingAs(User::where('rol', $rol)->first())
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Studenten met vrijstelling');
        }
    }

    public function test_vrijstellingslijst_niet_voor_beheer_en_financien(): void
    {
        $this->actingAs(User::where('rol', Rol::Beheerder)->first())
            ->get(route('dashboard'))->assertOk()->assertDontSee('Studenten met vrijstelling');
        $this->actingAs(User::where('rol', Rol::Financien)->first())
            ->get(route('dashboard'))->assertOk()->assertDontSee('Studenten met vrijstelling');
    }

    public function test_financien_dashboard_toont_bedragen_en_betaalgraad(): void
    {
        $this->actingAs(User::where('rol', Rol::Financien)->first())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Betaalgraad')
            ->assertSee('Achterstanden');
    }
}
