<?php

namespace Tests\Feature;

use App\Models\Bibliotheek\Publicatiesoort;
use App\Models\Bibliotheek\Auteur;
use App\Models\Bibliotheek\Publicatie;
use App\Models\Bibliotheek\Taal;
use App\Models\Bibliotheek\Verrijking;
use App\Support\BibliotheekVerrijker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Verrijking van de catalogus met een externe bibliografische bron.
 *
 * De kernregel van de opdrachtgever is "skip als je onzeker bent". Deze tests
 * leggen precies dat vast: een zekere match vult ISBN en jaar aan en zet de
 * schrijfwijze recht; een twijfelgeval wordt vastgelegd maar wijzigt NIETS.
 */
class BibliotheekVerrijkingTest extends TestCase
{
    use RefreshDatabase;

    private function publicatie(string $titel, ?string $auteur = null, string $taal = 'tr'): Publicatie
    {
        $publicatie = Publicatie::create([
            'soort_id' => Publicatiesoort::metCode('boek')->id,
            'titel' => $titel,
            'bron_rekcode' => 'B - '.random_int(1, 9999),
        ]);

        $publicatie->talen()->sync([Taal::where('code', $taal)->value('id')]);

        if ($auteur !== null) {
            $publicatie->auteurs()->sync(Auteur::idsVoorNamen([$auteur]));
        }

        return $publicatie->fresh(['auteurs', 'talen']);
    }

    /** Antwoord van Open Library nabootsen. */
    private function antwoord(array $docs): void
    {
        Http::fake([
            'openlibrary.org/*' => Http::response(['docs' => $docs], 200),
        ]);
    }

    public function test_een_zekere_match_vult_isbn_en_jaar_aan_en_zet_de_schrijfwijze_recht(): void
    {
        $this->antwoord([[
            'title' => "Kur'an ansiklopedisi",
            'author_name' => ['Süleyman Ateş'],
            'first_publish_year' => 1994,
            'isbn' => ['9789753430739'],
        ]]);

        $publicatie = $this->publicatie('Kuran Ansiklopedisi', 'Suleyman Ates');

        $uitkomst = (new BibliotheekVerrijker())->verrijk($publicatie);

        $this->assertSame(Verrijking::TOEGEPAST, $uitkomst->status);

        $publicatie->refresh();
        $this->assertSame('9789753430739', $publicatie->isbn);
        $this->assertSame(1994, $publicatie->uitgavejaar);
        $this->assertSame("Kur'an ansiklopedisi", $publicatie->titel);

        // De oude titel blijft bewaard, dus de correctie is terug te draaien.
        $this->assertSame('Kuran Ansiklopedisi', $uitkomst->oude_titel);

        // En de wijziging is gelogd (opdracht: oude en nieuwe waarde).
        $this->assertDatabaseHas('audit_logs', ['veld' => 'bibliotheek_verrijking']);
    }

    public function test_een_andere_titel_wordt_nooit_opgedrongen(): void
    {
        // De bron vindt een heel ander boek: dat mag onze titel niet overschrijven.
        $this->antwoord([[
            'title' => 'De ontdekking van de hemel',
            'author_name' => ['Harry Mulisch'],
            'first_publish_year' => 1992,
            'isbn' => ['9789023442615'],
        ]]);

        $publicatie = $this->publicatie('Islam in het licht van de bijbel', 'J.I. van Baaren', 'nl');

        $uitkomst = (new BibliotheekVerrijker())->verrijk($publicatie);

        $this->assertSame(Verrijking::ONZEKER, $uitkomst->status);

        $publicatie->refresh();
        $this->assertSame('Islam in het licht van de bijbel', $publicatie->titel);
        $this->assertNull($publicatie->isbn);
        $this->assertNull($publicatie->uitgavejaar);
    }

    public function test_een_kloppende_titel_met_een_andere_auteur_wordt_overgeslagen(): void
    {
        // Zelfde titel, maar van een andere auteur: dat is niet zeker genoeg.
        $this->antwoord([[
            'title' => 'Medicine of the prophet',
            'author_name' => ['Iemand Anders'],
            'first_publish_year' => 1998,
            'isbn' => ['9780946621224'],
        ]]);

        $publicatie = $this->publicatie('Medicine of the prophet', 'As-Suyuti', 'en');

        $uitkomst = (new BibliotheekVerrijker())->verrijk($publicatie);

        $this->assertSame(Verrijking::ONZEKER, $uitkomst->status);
        $this->assertStringContainsString('auteur', $uitkomst->toelichting);
        $this->assertNull($publicatie->fresh()->isbn);
    }

    public function test_zonder_eigen_auteur_volstaat_een_zekere_titelmatch(): void
    {
        $this->antwoord([[
            'title' => 'Islam mijn geloof',
            'author_name' => ['Onbekend'],
            'first_publish_year' => 2001,
            'isbn' => ['9789054600091'],
        ]]);

        $publicatie = $this->publicatie('Islam mijn Geloof', null, 'nl');

        $uitkomst = (new BibliotheekVerrijker())->verrijk($publicatie);

        $this->assertSame(Verrijking::TOEGEPAST, $uitkomst->status);
        $this->assertSame('9789054600091', $publicatie->fresh()->isbn);
    }

    public function test_een_bestaand_isbn_wordt_niet_overschreven(): void
    {
        $this->antwoord([[
            'title' => 'Aziz Kuran',
            'author_name' => ['Auteur'],
            'first_publish_year' => 2003,
            'isbn' => ['9789754733051'],
        ]]);

        $publicatie = $this->publicatie('Aziz Kuran', 'Auteur');
        $publicatie->update(['isbn' => '1234567890123']);

        (new BibliotheekVerrijker())->verrijk($publicatie);

        $this->assertSame('1234567890123', $publicatie->fresh()->isbn, 'Wat wij al hadden blijft staan.');
    }

    public function test_geen_treffer_wordt_vastgelegd_zodat_er_niet_opnieuw_wordt_bevraagd(): void
    {
        $this->antwoord([]);

        $publicatie = $this->publicatie('Een titel die nergens bestaat', 'Niemand');
        $verrijker = new BibliotheekVerrijker();

        $eerste = $verrijker->verrijk($publicatie);
        $tweede = $verrijker->verrijk($publicatie);

        $this->assertSame(Verrijking::GEEN_TREFFER, $eerste->status);
        $this->assertNull($tweede, 'Een al bevraagde titel wordt niet opnieuw bevraagd.');
        $this->assertSame(1, Verrijking::count());
    }

    public function test_een_fout_bij_de_bron_laat_de_publicatie_ongemoeid(): void
    {
        Http::fake(['openlibrary.org/*' => Http::response('', 503)]);

        $publicatie = $this->publicatie('Een titel', 'Een auteur');

        $uitkomst = (new BibliotheekVerrijker())->verrijk($publicatie);

        $this->assertSame(Verrijking::FOUT, $uitkomst->status);
        $this->assertSame('Een titel', $publicatie->fresh()->titel);
        $this->assertNull($publicatie->fresh()->isbn);
    }

    public function test_het_commando_bevraagt_alleen_nederlands_engels_en_turks(): void
    {
        $this->antwoord([[
            'title' => 'Een boek',
            'author_name' => ['Auteur'],
            'first_publish_year' => 2000,
            'isbn' => ['9789999999999'],
        ]]);

        $arabisch = $this->publicatie('كتاب عربي', 'مؤلف', 'ar');
        $turks = $this->publicatie('Een boek', 'Auteur', 'tr');

        $this->artisan('bibliotheek:verrijken', ['--limiet' => 50])->assertSuccessful();

        // De Arabische titel is niet bevraagd; de Turkse wel.
        $this->assertSame(0, Verrijking::where('publicatie_id', $arabisch->id)->count());
        $this->assertSame(1, Verrijking::where('publicatie_id', $turks->id)->count());
    }
}
