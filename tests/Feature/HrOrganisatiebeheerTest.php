<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Afdeling;
use App\Models\Functie;
use App\Models\Medewerker;
use App\Models\User;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\HrSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module HR / Personeelszaken — beheer van afdelingen en functies rechtstreeks op
 * de Organisatiestructuur-pagina door de HR-medewerker (voorheen alleen Beheer via
 * Opzoektabellen). Bewaakt de CRUD, de rolscheiding en de verwijder-vergrendeling.
 */
class HrOrganisatiebeheerTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class, HrSeeder::class]);
        $this->hr = User::where('email', 'n.aslan@iuasr.nl')->firstOrFail();
    }

    public function test_hr_ziet_beheerformulieren_op_organisatiepagina(): void
    {
        $this->actingAs($this->hr)->get(route('hr.organisatie'))
            ->assertOk()
            ->assertSee('Afdelingen &amp; teams beheren', false)
            ->assertSee('Functies beheren');
    }

    public function test_bestuur_ziet_alleen_lezen(): void
    {
        $bestuur = User::where('rol', Rol::Bestuur)->firstOrFail();
        $this->actingAs($bestuur)->get(route('hr.organisatie'))
            ->assertOk()
            ->assertDontSee('Functies beheren');
    }

    public function test_hr_voegt_afdeling_toe(): void
    {
        $this->actingAs($this->hr)->post(route('hr.afdeling.store'), [
            'code' => 'FIN', 'naam' => 'Financiën', 'actief' => '1',
        ])->assertRedirect(route('hr.organisatie'));

        $this->assertDatabaseHas('afdelingen', ['code' => 'FIN', 'naam' => 'Financiën', 'actief' => true]);
    }

    public function test_hr_wijzigt_functie(): void
    {
        $functie = Functie::firstOrFail();

        $this->actingAs($this->hr)->put(route('hr.functie.update', $functie), [
            'code' => $functie->code, 'naam' => 'Hoofddocent', 'categorie' => 'docent', 'actief' => '1',
        ])->assertRedirect(route('hr.organisatie'));

        $this->assertSame('Hoofddocent', $functie->fresh()->naam);
    }

    public function test_functie_met_medewerkers_niet_verwijderbaar(): void
    {
        $bezet = Medewerker::whereNotNull('functie_id')->firstOrFail();

        $this->actingAs($this->hr)->delete(route('hr.functie.destroy', $bezet->functie_id))
            ->assertStatus(422);

        $this->assertDatabaseHas('functies', ['id' => $bezet->functie_id]);
    }

    public function test_lege_functie_wel_verwijderbaar(): void
    {
        $functie = Functie::create(['code' => 'TMP', 'naam' => 'Tijdelijk', 'categorie' => 'staf', 'actief' => true]);

        $this->actingAs($this->hr)->delete(route('hr.functie.destroy', $functie))
            ->assertRedirect(route('hr.organisatie'));

        $this->assertDatabaseMissing('functies', ['id' => $functie->id]);
    }

    public function test_afdeling_niet_onder_zichzelf(): void
    {
        $afdeling = Afdeling::firstOrFail();

        $this->actingAs($this->hr)->put(route('hr.afdeling.update', $afdeling), [
            'code' => $afdeling->code, 'naam' => $afdeling->naam,
            'bovenliggende_afdeling_id' => $afdeling->id,
        ])->assertStatus(422);
    }

    public function test_studentenzaken_mag_niet_beheren(): void
    {
        $sz = User::where('rol', Rol::Studentenzaken)->firstOrFail();
        $this->actingAs($sz)->post(route('hr.afdeling.store'), [
            'code' => 'X', 'naam' => 'X',
        ])->assertForbidden();
    }
}
