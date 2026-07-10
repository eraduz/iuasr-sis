<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Opleiding;
use App\Models\User;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Een ingelogde directeur ziet duidelijk WELKE opleiding hij beheert — in de
 * topbalk (naast de rol) én op het dashboard (kop + subtitel). Voorheen stond er
 * alleen "Directie", zonder de opleiding.
 */
class DirectieOpleidingZichtbaarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, GebruikerSeeder::class]);
    }

    public function test_directeur_ziet_eigen_opleiding_op_het_dashboard(): void
    {
        // Mariëlle Groen is directeur van de PABO.
        $pabo = User::where('email', 'm.groen@iuasr.nl')->firstOrFail();

        $this->actingAs($pabo)->get('/')
            ->assertOk()
            ->assertSee('PABO')                       // code in kop + topbalk
            ->assertSee('Leraar Basisonderwijs');     // volledige opleidingnaam in de subtitel
    }

    public function test_directeur_met_twee_opleidingen_ziet_beide_codes(): void
    {
        // Bram de Wit is directeur van ISLTH + PMGV.
        $bram = User::where('email', 'b.dewit@iuasr.nl')->firstOrFail();

        $this->actingAs($bram)->get('/')
            ->assertOk()->assertSee('ISLTH')->assertSee('PMGV');
    }

    public function test_directeur_ziet_opleiding_op_het_modulekeuzescherm(): void
    {
        // Yasin Demir is directeur van de Master GV (MGV).
        $yasin = User::where('email', 'y.demir@iuasr.nl')->firstOrFail();

        $this->actingAs($yasin)->get(route('modules.kiezen'))
            ->assertOk()->assertSee('Directie')->assertSee('MGV');
    }

    public function test_directeur_zonder_toewijzing_krijgt_een_melding(): void
    {
        $dir = User::create(['naam' => 'Nieuwe Directeur', 'email' => 'nieuw.dir@iuasr.test', 'rol' => Rol::Directie]);

        $this->actingAs($dir)->get('/')
            ->assertOk()->assertSee('Nog geen opleiding toegewezen');
    }
}
