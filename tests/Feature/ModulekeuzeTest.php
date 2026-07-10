<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase A — platformfundament: het modulekeuzescherm na de login en de
 * rolgebonden toegang tot de modules.
 */
class ModulekeuzeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, GebruikerSeeder::class]);
    }

    public function test_de_vijf_modules_zijn_aanwezig(): void
    {
        $this->assertSame(
            ['studentenzaken', 'cursussen', 'stage', 'scriptie', 'hr'],
            Module::geordend()->pluck('sleutel')->all(),
        );
        // Studentenzaken en (sinds Fase B) Cursussen zijn gebouwd; de rest nog niet.
        $this->assertTrue(Module::where('sleutel', 'studentenzaken')->value('actief'));
        $this->assertTrue(Module::where('sleutel', 'cursussen')->value('actief'));
        $this->assertFalse(Module::where('sleutel', 'stage')->value('actief'));
    }

    public function test_dev_login_leidt_naar_het_keuzescherm(): void
    {
        $gebruiker = User::where('rol', Rol::Studentenzaken)->first();

        $this->post(route('dev-login'), ['user_id' => $gebruiker->id])
            ->assertRedirect(route('modules.kiezen'));
    }

    public function test_keuzescherm_toont_studentenzaken_klikbaar(): void
    {
        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->get(route('modules.kiezen'))
            ->assertOk()
            ->assertSee('Kies een module')
            ->assertSee('Studentenzaken')
            ->assertSee(route('dashboard'), false)   // Studentenzaken linkt naar het dashboard
            ->assertSee('Binnenkort');               // de nog niet gebouwde modules
    }

    public function test_financien_ziet_zowel_studentenzaken_als_cursussen(): void
    {
        $gebruiker = User::where('rol', Rol::Financien)->first();

        $this->assertTrue(Module::where('sleutel', 'studentenzaken')->first()->toegankelijkVoor($gebruiker));
        $this->assertTrue(Module::where('sleutel', 'cursussen')->first()->toegankelijkVoor($gebruiker));
        // Cursussen is sinds Fase B gebouwd en dus bruikbaar voor Financiën.
        $this->assertTrue(Module::where('sleutel', 'cursussen')->first()->bruikbaarVoor($gebruiker));
    }

    public function test_onderwijsrol_heeft_geen_toegang_tot_cursussen(): void
    {
        $docent = User::where('rol', Rol::Docent)->first();

        $this->assertFalse(Module::where('sleutel', 'cursussen')->first()->toegankelijkVoor($docent));
        $this->assertTrue(Module::where('sleutel', 'studentenzaken')->first()->toegankelijkVoor($docent));
    }

    public function test_beheerder_heeft_toegang_tot_alle_modules(): void
    {
        $beheer = User::where('rol', Rol::Beheerder)->first();

        foreach (Module::all() as $module) {
            $this->assertTrue($module->toegankelijkVoor($beheer), "Beheerder mist toegang tot {$module->sleutel}");
        }
    }

    public function test_alleen_gebouwde_modules_hebben_een_startroute(): void
    {
        $this->assertSame('dashboard', Module::where('sleutel', 'studentenzaken')->first()->startRoute());
        $this->assertSame('cursussen.dashboard', Module::where('sleutel', 'cursussen')->first()->startRoute());
        $this->assertNull(Module::where('sleutel', 'hr')->first()->startRoute());
    }

    public function test_keuzescherm_vereist_login(): void
    {
        $this->get(route('modules.kiezen'))->assertRedirect(route('login'));
    }
}
