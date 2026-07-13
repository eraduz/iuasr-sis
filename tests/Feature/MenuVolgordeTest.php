<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De zijbalk groepeert op onderwerp en houdt een VASTE volgorde aan. Zonder die
 * sortering hangt de volgorde af van de toevalligheid waarmee de menu's worden
 * samengevoegd (multi-rol) en van de plek waar een groep later is bijgeplakt.
 */
class MenuVolgordeTest extends TestCase
{
    use RefreshDatabase;

    public function test_de_menugroepen_staan_in_een_vaste_volgorde(): void
    {
        $sz = User::create([
            'naam' => 'Test SZ',
            'email' => 'sz@iuasr.test',
            'rol' => Rol::Studentenzaken,
        ]);

        $this->actingAs($sz)->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'Overzicht',
                'Studenten',
                'Onderwijs',
                'Financieel',
                'Documenten',
                'Bibliotheek IUASR',   // gedeelde voorziening: onderaan, niet tussen het werk
            ]);
    }

    public function test_de_bibliotheekmodule_heeft_een_menu_per_onderwerp(): void
    {
        $bieb = User::create([
            'naam' => 'Test bibliotheek',
            'email' => 'bieb@iuasr.test',
            'rol' => Rol::Bibliotheek,
        ]);

        // Niet één lange lijst, maar groepen op onderwerp — en het onderhoud
        // (Beheer: importeren, verrijking) staat onderaan, niet tussen het werk.
        $this->actingAs($bieb)->get(route('bibliotheek.dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'Overzicht',
                'Collectie',
                'Catalogus',
                'Uitlenen',
                'Rapportage',
                'Beheer',
                'Importeren',
            ]);
    }

    public function test_bij_multi_rol_worden_de_groepen_ook_geordend(): void
    {
        $gebruiker = User::create([
            'naam' => 'Test multi',
            'email' => 'multi@iuasr.test',
            'rol' => Rol::Studentenzaken,
        ]);

        // Extra rol: het menu van Financiën komt erbij. De volgorde mag daar niet
        // van in de war raken.
        $gebruiker->roltoewijzingen()->create(['rol' => Rol::Financien->value]);

        $this->actingAs($gebruiker->fresh())->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'Overzicht',
                'Studenten',
                'Financiën',
                'Documenten',
                'Bibliotheek IUASR',
            ]);
    }
}
