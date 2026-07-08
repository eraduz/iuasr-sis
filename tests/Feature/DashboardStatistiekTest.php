<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use App\Support\Statistiek;
use Database\Seeders\CollegegeldSeeder;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
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
            ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class,
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
            $this->actingAs($user)->get(route('dashboard'))->assertOk();
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

    public function test_financien_dashboard_toont_bedragen_en_betaalgraad(): void
    {
        $this->actingAs(User::where('rol', Rol::Financien)->first())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Betaalgraad')
            ->assertSee('Achterstanden');
    }
}
