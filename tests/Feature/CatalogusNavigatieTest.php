<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Bibliotheek\Publicatie;
use App\Models\Bibliotheek\Publicatiesoort;
use App\Models\User;
use App\Support\Paginakeuze;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Navigeren door een grote catalogus: het A–Z-filter en de keuze voor het aantal
 * per pagina. Met 11.000 titels is bladeren per pagina onbegonnen werk; deze
 * hulpmiddelen maken de lijst behapbaar.
 */
class CatalogusNavigatieTest extends TestCase
{
    use RefreshDatabase;

    private function titel(string $titel): Publicatie
    {
        return Publicatie::create([
            'soort_id' => Publicatiesoort::metCode('boek')->id,
            'titel' => $titel,
        ]);
    }

    public function test_het_a_z_filter_toont_alleen_titels_met_die_beginletter(): void
    {
        $this->titel('Tafsir Ibn Kathir');
        $this->titel('Aqidah Tahawiyya');
        $this->titel('Tarikh al-Islam');

        $docent = User::create(['naam' => 'D', 'email' => 'd@iuasr.test', 'rol' => Rol::Docent]);

        $this->actingAs($docent)->get(route('catalogus', ['letter' => 'T']))
            ->assertOk()
            ->assertSee('Tafsir Ibn Kathir')
            ->assertSee('Tarikh al-Islam')
            ->assertDontSee('Aqidah Tahawiyya');
    }

    public function test_de_hekje_knop_vangt_titels_die_niet_met_een_letter_beginnen(): void
    {
        $this->titel('العربية بين يديك');   // Arabisch schrift
        $this->titel('1001 nacht');         // begint met een cijfer
        $this->titel('Fiqh');               // gewone letter

        // Op code getest, want de tabelweergave hangt van de zoekopdracht af.
        $this->assertSame(2, Publicatie::beginletter('#')->count());
        $this->assertSame(1, Publicatie::beginletter('F')->count());
    }

    public function test_de_keuze_voor_aantal_per_pagina_wordt_gerespecteerd(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->titel(sprintf('Boek %03d', $i));
        }

        $bieb = User::create(['naam' => 'B', 'email' => 'b@iuasr.test', 'rol' => Rol::Bibliotheek]);

        // Standaard 25 per pagina.
        $this->actingAs($bieb)->get(route('bibliotheek.publicaties'))
            ->assertViewHas('publicaties', fn ($p) => $p->perPage() === 25);

        // Gekozen 100 per pagina: alle 60 op één pagina.
        $this->actingAs($bieb)->get(route('bibliotheek.publicaties', ['per' => 100]))
            ->assertViewHas('publicaties', fn ($p) => $p->perPage() === 100 && $p->count() === 60);
    }

    public function test_een_ongeldige_paginakeuze_valt_terug_op_de_standaard(): void
    {
        $request = \Illuminate\Http\Request::create('/', 'GET', ['per' => '999']);
        $this->assertSame(Paginakeuze::STANDAARD, Paginakeuze::aantal($request));

        $request = \Illuminate\Http\Request::create('/', 'GET', ['per' => '100']);
        $this->assertSame(100, Paginakeuze::aantal($request));
    }
}
