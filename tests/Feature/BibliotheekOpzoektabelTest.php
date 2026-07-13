<?php

namespace Tests\Feature;

use App\Enums\ExemplaarStatus;
use App\Enums\Rol;
use App\Models\Bibliotheek\Kast;
use App\Models\Bibliotheek\Publicatie;
use App\Models\Bibliotheek\Publicatiesoort;
use App\Models\Bibliotheek\Taal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De opzoektabellen van de bibliotheek: soorten, talen, vakgebieden en kasten.
 *
 * De kern: de bibliotheek voegt zélf een soort toe (cd, dvd, en wat er nog komt),
 * en het systeem gedraagt zich daar meteen naar — zonder codewijziging. De twee
 * vlaggen op een soort zijn geen etiket maar sturen het gedrag aan.
 */
class BibliotheekOpzoektabelTest extends TestCase
{
    use RefreshDatabase;

    private function bibliothecaris(): User
    {
        return User::create([
            'naam' => 'Test bibliotheek',
            'email' => 'bieb@iuasr.test',
            'rol' => Rol::Bibliotheek,
        ]);
    }

    public function test_de_vijf_soorten_bestaan_na_de_migratie(): void
    {
        $codes = Publicatiesoort::orderBy('volgorde')->pluck('code')->all();

        $this->assertSame(['boek', 'tijdschrift', 'digitaal', 'cd', 'dvd'], $codes);

        // De vlaggen bepalen het gedrag: een digitaal document kent geen exemplaren,
        // alleen een tijdschrift kent uitgaven met artikelen.
        $this->assertTrue(Publicatiesoort::metCode('cd')->heeftExemplaren());
        $this->assertTrue(Publicatiesoort::metCode('dvd')->heeftExemplaren());
        $this->assertFalse(Publicatiesoort::metCode('digitaal')->heeftExemplaren());
        $this->assertTrue(Publicatiesoort::metCode('tijdschrift')->heeftUitgaven());
        $this->assertFalse(Publicatiesoort::metCode('boek')->heeftUitgaven());
    }

    public function test_de_bibliotheek_voegt_zelf_een_soort_toe(): void
    {
        $bieb = $this->bibliothecaris();

        $this->actingAs($bieb)->get(route('bibliotheek.opzoektabellen'))->assertOk();

        $this->actingAs($bieb)->post(route('bibliotheek.opzoektabellen.soort.store'), [
            'code' => 'kaart',
            'naam' => 'Landkaart',
            'heeft_exemplaren' => '1',
            'volgorde' => 6,
        ])->assertRedirect();

        $soort = Publicatiesoort::metCode('kaart');
        $this->assertNotNull($soort);
        $this->assertTrue($soort->heeftExemplaren());
        $this->assertFalse($soort->heeftUitgaven());

        // En de nieuwe soort is meteen te kiezen bij een publicatie.
        $this->actingAs($bieb)->get(route('bibliotheek.publicaties.create'))
            ->assertOk()
            ->assertSee('Landkaart');
    }

    public function test_een_cd_kan_worden_geregistreerd_en_uitgeleend(): void
    {
        $bieb = $this->bibliothecaris();

        $this->actingAs($bieb)->post(route('bibliotheek.publicaties.store'), [
            'soort_id' => Publicatiesoort::metCode('cd')->id,
            'titel' => 'Koranrecitatie — Abdul Basit',
            'exemplaren' => ['CD-001'],
        ])->assertRedirect();

        $cd = Publicatie::where('titel', 'Koranrecitatie — Abdul Basit')->firstOrFail();

        // Een cd kent fysieke exemplaren (de vlag staat aan), dus het exemplaar is
        // aangemaakt en uitleenbaar.
        $this->assertTrue($cd->heeftExemplaren());
        $this->assertSame(1, $cd->exemplaren()->count());
        $this->assertTrue($cd->exemplaren->first()->isUitleenbaar());
        $this->assertSame(ExemplaarStatus::Beschikbaar, $cd->exemplaren->first()->status);
    }

    public function test_een_soort_zonder_exemplaren_krijgt_er_ook_geen(): void
    {
        $bieb = $this->bibliothecaris();

        // Een digitaal document: de vlag 'heeft exemplaren' staat uit.
        $this->actingAs($bieb)->post(route('bibliotheek.publicaties.store'), [
            'soort_id' => Publicatiesoort::metCode('digitaal')->id,
            'titel' => 'Onderwijsvisie (PDF)',
            'exemplaren' => ['MOET-GENEGEERD-WORDEN'],
        ])->assertRedirect();

        $digitaal = Publicatie::where('titel', 'Onderwijsvisie (PDF)')->firstOrFail();

        $this->assertFalse($digitaal->heeftExemplaren());
        $this->assertSame(0, $digitaal->exemplaren()->count(), 'De server negeert exemplaren bij een soort die ze niet kent.');
    }

    public function test_een_soort_met_titels_kan_niet_worden_verwijderd(): void
    {
        $bieb = $this->bibliothecaris();
        $boek = Publicatiesoort::metCode('boek');

        Publicatie::create(['soort_id' => $boek->id, 'titel' => 'Een boek']);

        $this->actingAs($bieb)
            ->delete(route('bibliotheek.opzoektabellen.soort.destroy', $boek))
            ->assertSessionHas('fout');

        $this->assertNotNull(Publicatiesoort::metCode('boek'));

        // Wél op inactief zetten: dan verdwijnt hij uit de keuzelijsten, maar de
        // bestaande titels blijven kloppen.
        $this->actingAs($bieb)->put(route('bibliotheek.opzoektabellen.soort.update', $boek), [
            'naam' => 'Boek',
            'heeft_exemplaren' => '1',
        ])->assertRedirect();

        $this->assertFalse($boek->fresh()->actief);
    }

    public function test_een_ongebruikte_soort_kan_wel_worden_verwijderd(): void
    {
        $bieb = $this->bibliothecaris();
        $dvd = Publicatiesoort::metCode('dvd');

        $this->actingAs($bieb)
            ->delete(route('bibliotheek.opzoektabellen.soort.destroy', $dvd))
            ->assertSessionHas('status');

        $this->assertNull(Publicatiesoort::metCode('dvd'));
    }

    public function test_talen_en_kasten_zijn_zelf_uit_te_breiden(): void
    {
        $bieb = $this->bibliothecaris();

        $this->actingAs($bieb)->post(route('bibliotheek.opzoektabellen.taal.store'), [
            'code' => 'fa',
            'naam' => 'Perzisch',
        ])->assertRedirect();

        $this->assertNotNull(Taal::where('code', 'fa')->first());

        $this->actingAs($bieb)->post(route('bibliotheek.opzoektabellen.kast.store'), [
            'code' => 'V',
            'omschrijving' => 'Media (cd/dvd)',
        ])->assertRedirect();

        $this->assertNotNull(Kast::where('code', 'V')->first());
    }

    public function test_een_kast_met_exemplaren_blijft_staan(): void
    {
        $bieb = $this->bibliothecaris();

        $kast = Kast::firstOrCreate(['code' => 'Z'], ['omschrijving' => 'Test', 'actief' => true]);
        $publicatie = Publicatie::create([
            'soort_id' => Publicatiesoort::metCode('boek')->id,
            'titel' => 'Een boek',
        ]);
        $publicatie->exemplaren()->create([
            'serienummer' => 'Z-1',
            'kast_id' => $kast->id,
            'status' => ExemplaarStatus::Beschikbaar,
        ]);

        $this->actingAs($bieb)
            ->delete(route('bibliotheek.opzoektabellen.kast.destroy', $kast))
            ->assertSessionHas('fout');

        $this->assertNotNull(Kast::where('code', 'Z')->first());
    }

    public function test_alleen_de_bibliotheek_beheert_de_opzoektabellen(): void
    {
        $maak = fn (Rol $rol) => User::create(['naam' => 'T', 'email' => $rol->value.'@iuasr.test', 'rol' => $rol]);

        $this->actingAs($maak(Rol::Beheerder))->get(route('bibliotheek.opzoektabellen'))->assertOk();
        $this->actingAs($maak(Rol::Bestuur))->get(route('bibliotheek.opzoektabellen'))->assertForbidden();
        $this->actingAs($maak(Rol::Docent))->get(route('bibliotheek.opzoektabellen'))->assertForbidden();
    }
}
