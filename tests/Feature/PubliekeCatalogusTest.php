<?php

namespace Tests\Feature;

use App\Enums\ExemplaarStatus;
use App\Models\Bibliotheek\Publicatiesoort;
use App\Models\Bibliotheek\Auteur;
use App\Models\Bibliotheek\Publicatie;
use App\Models\Bibliotheek\Taal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De publieke zoekpagina (de PC in de bibliotheek): zonder login, alleen zoeken.
 *
 * De tests bewaken vooral wat er NIET op mag staan: geen leners, geen
 * uitleenhistorie, geen interne opmerkingen, en geen enkele mutatieroute.
 * Daarnaast dat de netwerkbeperking (SIS_TOEGESTANE_IPS) ook hier geldt.
 */
class PubliekeCatalogusTest extends TestCase
{
    use RefreshDatabase;

    private function boek(): Publicatie
    {
        $publicatie = Publicatie::create([
            'soort_id' => Publicatiesoort::metCode('boek')->id,
            'titel' => 'Hak dini Kur an dili',
            'isbn' => '9789753430739',
            'uitgavejaar' => 1935,
            'bron_rekcode' => 'B. 777',
            'opmerking' => 'INTERNE AANTEKENING van de bibliothecaris',
        ]);

        $publicatie->auteurs()->sync(Auteur::idsVoorNamen(['Elmalili Hamdi Yazir']));
        $publicatie->talen()->sync([Taal::where('code', 'tr')->value('id')]);
        $publicatie->exemplaren()->create([
            'serienummer' => 'B.777-1',
            'status' => ExemplaarStatus::Beschikbaar,
        ]);

        return $publicatie;
    }

    public function test_zonder_login_kan_een_boek_worden_gezocht(): void
    {
        $this->boek();

        $this->get(route('catalogus.publiek', ['q' => 'Hak dini']))
            ->assertOk()
            ->assertSee('Bibliotheek IUASR')
            ->assertSee('Hak dini Kur an dili')
            ->assertSee('Elmalili Hamdi Yazir')
            ->assertSee('B. 777')                 // de rekplaats: waar het boek ligt
            ->assertSee('9789753430739');
    }

    public function test_de_pagina_toont_geen_interne_gegevens(): void
    {
        $this->boek();

        $antwoord = $this->get(route('catalogus.publiek'));

        $antwoord->assertOk()
            ->assertDontSee('INTERNE AANTEKENING')   // interne opmerking bij het boek
            ->assertDontSee('Uitlenen')
            ->assertDontSee('Bewerken')
            ->assertDontSee(route('login'), false);  // geen inlogscherm nodig
    }

    public function test_zoeken_werkt_op_titel_auteur_isbn_en_rek(): void
    {
        $this->boek();

        foreach (['Hak dini', 'Elmalili', '9789753430739', 'B. 777'] as $zoekterm) {
            $this->get(route('catalogus.publiek', ['q' => $zoekterm]))
                ->assertOk()
                ->assertSee('Hak dini Kur an dili');
        }

        $this->get(route('catalogus.publiek', ['q' => 'bestaat niet']))
            ->assertOk()
            ->assertSee('Niets gevonden');
    }

    public function test_uitgeleende_boeken_worden_als_uitgeleend_getoond(): void
    {
        $boek = $this->boek();

        $this->get(route('catalogus.publiek'))->assertOk()->assertSee('ja');

        $boek->exemplaren->first()->update(['status' => ExemplaarStatus::Uitgeleend]);

        $this->get(route('catalogus.publiek'))->assertOk()->assertSee('uitgeleend');
        $this->get(route('catalogus.publiek', ['beschikbaar' => '1']))
            ->assertOk()->assertDontSee('Hak dini Kur an dili');
    }

    public function test_de_netwerkbeperking_geldt_ook_voor_de_publieke_pagina(): void
    {
        $this->boek();

        // Zonder filter: bereikbaar (zoals bij lokale ontwikkeling).
        $this->get(route('catalogus.publiek'))->assertOk();

        // Met een filter dat dit IP niet toelaat: geweigerd — ook zonder login.
        config(['sis.toegestane_ips' => ['192.168.99.0/24']]);
        $this->get(route('catalogus.publiek'))->assertForbidden();

        // En met het eigen IP in de lijst: weer bereikbaar.
        config(['sis.toegestane_ips' => ['127.0.0.1']]);
        $this->get(route('catalogus.publiek'))->assertOk();
    }

    public function test_de_netwerkbeperking_geldt_voor_het_hele_systeem(): void
    {
        config(['sis.toegestane_ips' => ['192.168.99.0/24']]);

        // Ook het inlogscherm is dan onbereikbaar: wie niet op het netwerk hoort,
        // ziet niet eens een login.
        $this->get(route('login'))->assertForbidden();
    }
}
