<?php

namespace Tests\Feature;

use App\Enums\ExemplaarStatus;
use App\Enums\PublicatieSoort;
use App\Enums\Rol;
use App\Models\Bibliotheek\Auteur;
use App\Models\Bibliotheek\Kast;
use App\Models\Bibliotheek\Publicatie;
use App\Models\Bibliotheek\Taal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Bibliotheek IUASR" — de catalogus als alleen-lezen raadpleegscherm voor iedere
 * ingelogde medewerker, uit welke module hij ook komt.
 *
 * De kern van deze tests: iedereen mag ZOEKEN, niemand mag hier MUTEREN. De
 * beheerfuncties (uitlenen, innemen, catalogus wijzigen) blijven achter de rol
 * Bibliotheek.
 */
class CatalogusTest extends TestCase
{
    use RefreshDatabase;

    private function gebruiker(Rol $rol): User
    {
        return User::create([
            'naam' => 'Test '.$rol->value,
            'email' => $rol->value.'@iuasr.test',
            'rol' => $rol,
        ]);
    }

    private function boek(): Publicatie
    {
        $publicatie = Publicatie::create([
            'soort' => PublicatieSoort::Boek,
            'titel' => 'Hak dini Kur an dili',
            'isbn' => '9789753430739',
            'uitgavejaar' => 1935,
            // De rekplaats zoals de bibliotheek die altijd al noteerde.
            'bron_rekcode' => 'B. 777',
        ]);

        $publicatie->auteurs()->sync(Auteur::idsVoorNamen(['Elmalili Hamdi Yazir']));
        $publicatie->talen()->sync([Taal::where('code', 'tr')->value('id')]);

        // De kasten A t/m U worden door de migratie aangemaakt (de rekletters uit
        // de oude Excel-bibliotheek); hier alleen ophalen.
        $kast = Kast::firstOrCreate(['code' => 'B'], ['omschrijving' => 'Quran wetenschappen', 'actief' => true]);
        $publicatie->exemplaren()->create([
            'serienummer' => 'B.777-1',
            'kast_id' => $kast->id,
            'status' => ExemplaarStatus::Beschikbaar,
        ]);

        return $publicatie;
    }

    public function test_iedere_medewerker_kan_een_boek_zoeken(): void
    {
        $boek = $this->boek();

        $docent = null;

        foreach ([Rol::Docent, Rol::Studentenzaken, Rol::Hrmedewerker, Rol::Financien, Rol::Balie, Rol::Examencommissie] as $rol) {
            $gebruiker = $this->gebruiker($rol);
            $docent ??= $gebruiker;

            $this->actingAs($gebruiker)
                ->get(route('catalogus', ['q' => 'Kur an']))
                ->assertOk()
                ->assertSee('Bibliotheek IUASR')
                ->assertSee('Hak dini Kur an dili')
                ->assertSee('9789753430739')   // het ISBN staat erbij
                ->assertSee('B');              // en de kast waar het staat
        }

        // De boekenkaart is ook voor iedereen te openen.
        $this->actingAs($docent)
            ->get(route('catalogus.show', $boek))
            ->assertOk()
            ->assertSee('B.777-1');            // serienummer, zodat men het kan opvragen
    }

    public function test_de_catalogus_toont_geen_enkele_beheerknop(): void
    {
        $this->boek();

        $antwoord = $this->actingAs($this->gebruiker(Rol::Docent))->get(route('catalogus'));

        $antwoord->assertOk()
            ->assertDontSee(route('bibliotheek.uitlenen'), false)
            ->assertDontSee(route('bibliotheek.publicaties.create'), false)
            ->assertDontSee('Uitlenen')
            ->assertDontSee('Bewerken');
    }

    public function test_de_beheerfuncties_blijven_achter_de_rol_bibliotheek(): void
    {
        $docent = $this->gebruiker(Rol::Docent);

        // Zoeken mag...
        $this->actingAs($docent)->get(route('catalogus'))->assertOk();

        // ...maar de module en haar mutatieroutes niet.
        $this->actingAs($docent)->get(route('bibliotheek.dashboard'))->assertForbidden();
        $this->actingAs($docent)->get(route('bibliotheek.publicaties'))->assertForbidden();
        $this->actingAs($docent)->get(route('bibliotheek.uitlenen'))->assertForbidden();
        $this->actingAs($docent)->get(route('bibliotheek.import'))->assertForbidden();
    }

    public function test_de_rekplaats_staat_in_de_lijst_en_op_de_boekenkaart(): void
    {
        $boek = $this->boek();
        $docent = $this->gebruiker(Rol::Docent);

        // De rekcode ("B. 777") vertelt waar het boek fysiek ligt — dat is voor een
        // docent de belangrijkste informatie om het boek te kunnen vinden.
        $this->actingAs($docent)->get(route('catalogus'))
            ->assertOk()
            ->assertSee('Rek')
            ->assertSee('B. 777');

        $this->actingAs($docent)->get(route('catalogus.show', $boek))
            ->assertOk()
            ->assertSee('Rek / plaats')
            ->assertSee('B. 777');
    }

    public function test_zoeken_werkt_op_titel_auteur_isbn_en_rekplaats(): void
    {
        $this->boek();
        $docent = $this->gebruiker(Rol::Docent);

        foreach (['Hak dini', 'Elmalili', '9789753430739', 'B. 777'] as $zoekterm) {
            $this->actingAs($docent)->get(route('catalogus', ['q' => $zoekterm]))
                ->assertOk()
                ->assertSee('Hak dini Kur an dili');
        }

        $this->actingAs($docent)->get(route('catalogus', ['q' => 'bestaat niet']))
            ->assertOk()
            ->assertSee('Niets gevonden');
    }

    public function test_het_filter_alleen_beschikbaar_verbergt_uitgeleende_titels(): void
    {
        $boek = $this->boek();
        $docent = $this->gebruiker(Rol::Docent);

        $this->actingAs($docent)->get(route('catalogus', ['beschikbaar' => '1']))
            ->assertOk()->assertSee('Hak dini Kur an dili');

        // Enige exemplaar uitgeleend → valt uit het filter.
        $boek->exemplaren->first()->update(['status' => ExemplaarStatus::Uitgeleend]);

        $this->actingAs($docent)->get(route('catalogus', ['beschikbaar' => '1']))
            ->assertOk()->assertDontSee('Hak dini Kur an dili');
    }

    public function test_de_catalogus_vereist_wel_een_login(): void
    {
        $this->get(route('catalogus'))->assertRedirect(route('login'));
    }
}
