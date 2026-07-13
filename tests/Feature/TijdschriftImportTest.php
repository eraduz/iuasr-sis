<?php

namespace Tests\Feature;

use App\Models\Bibliotheek\Artikel;
use App\Models\Bibliotheek\Publicatie;
use App\Models\Bibliotheek\Publicatiesoort;
use App\Models\Bibliotheek\Uitgave;
use App\Support\TijdschriftImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Import van de tijdschriftINHOUD (de artikelen per uitgave).
 *
 * Deze tests leggen de regels vast die uit de echte bron zijn afgeleid:
 * de hiërarchie rek → tijdschrift → uitgave → artikel, het feit dat sommige
 * artikelregels géén opsommingsteken hebben, en de valkuil dat een artikelTITEL
 * zelf "REK N: 4" kan bevatten. En bovenal: bij twijfel wordt er niets geraden.
 */
class TijdschriftImportTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<int,string>  $regels */
    private function bestand(array $regels): string
    {
        $spreadsheet = new Spreadsheet();
        $blad = $spreadsheet->getActiveSheet();

        foreach ($regels as $i => $regel) {
            $blad->setCellValue('A'.($i + 1), $regel);
        }

        $pad = sys_get_temp_dir().'/tijdschrift-'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($pad);

        return $pad;
    }

    public function test_de_hierarchie_rek_tijdschrift_uitgave_artikel_wordt_gelezen(): void
    {
        $pad = $this->bestand([
            'REK N: 3 "drie"',
            'ISLAMIC STUDIES   REK N: 3',
            'Quarterly Journal of Islamic Research Institute',
            'Vol. 1, June 1962, N: 2',
            'CONTENTS',
            '· Sunnah and Hadith - Fazlur Rahman ... 1 – 36 السنة والحديث',
            '· Pluralism in the Islamic World - G. E. von Grunebaum ... 37 - 59',
        ]);

        $import = new TijdschriftImport();
        $gelezen = $import->lees($pad);

        $this->assertSame(1, $gelezen['statistiek']['tijdschriften']);
        $this->assertSame(1, $gelezen['statistiek']['uitgaven']);
        $this->assertSame(2, $gelezen['statistiek']['artikelen']);

        $import->importeer($gelezen['rijen']);

        $tijdschrift = Publicatie::where('titel', 'ISLAMIC STUDIES')->firstOrFail();
        $this->assertSame('tijdschrift', $tijdschrift->soort->code);
        $this->assertSame('N. 3', $tijdschrift->bron_rekcode);   // de rek waar het staat

        $uitgave = Uitgave::where('publicatie_id', $tijdschrift->id)->firstOrFail();
        $this->assertSame(1962, $uitgave->jaar);
        $this->assertSame(2, $uitgave->artikelen()->count());

        // Titel, auteur, pagina's en de Arabische vertaling zijn uit elkaar gehaald.
        $artikel = Artikel::where('titel', 'Sunnah and Hadith')->firstOrFail();
        $this->assertSame('Fazlur Rahman', $artikel->auteurs->first()->naam);
        $this->assertSame('1-36', $artikel->paginas);
        $this->assertStringContainsString('السنة والحديث', $artikel->trefwoorden);

        // En de volledige bronregel blijft bewaard: niets gaat verloren.
        $this->assertStringContainsString('Sunnah and Hadith', $artikel->beschrijving);

        unlink($pad);
    }

    public function test_een_artikeltitel_met_rek_erin_breekt_de_structuur_niet(): void
    {
        // Deze regel staat echt in de bron en liet eerder alle volgende artikelen
        // hun uitgave verliezen: de titel bevat zelf "REK N: 4".
        $pad = $this->bestand([
            'ISLAMIC STUDIES   REK N: 3',
            'Vol. 5 June 1966, N: 2',
            'CONTENTS',
            "· 'Mutiny' and The Moslim world  REK N: 4: a British Viewpoint - S. D. Smith. 10-20",
            '· Notes on the Historiography of the Pre-Islamic Odes - Suhail Ibn Salim. 21-30',
        ]);

        $import = new TijdschriftImport();
        $gelezen = $import->lees($pad);

        // Eén tijdschrift, niet twee — en beide artikelen horen bij de uitgave.
        $this->assertSame(1, $gelezen['statistiek']['tijdschriften']);
        $this->assertSame(2, $gelezen['statistiek']['artikelen']);
        $this->assertSame([], $gelezen['overgeslagen']);

        unlink($pad);
    }

    public function test_artikelen_zonder_opsommingsteken_worden_ook_gelezen(): void
    {
        // In de bron ontbreekt bij duizenden regels het opsommingsteken. Binnen een
        // uitgave telt een regel die op een paginanummer eindigt tóch als artikel.
        $pad = $this->bestand([
            'ISLAMIC STUDIES   REK N: 3',
            'Vol. 2, March 1963, N: 1',
            'CONTENTS',
            'The Significance of the Political Murder of Mirza Salman - R. M. Savory. 181-191',
        ]);

        $gelezen = (new TijdschriftImport())->lees($pad);

        $this->assertSame(1, $gelezen['statistiek']['artikelen']);
        $this->assertSame('R. M. Savory', $gelezen['rijen'][0]['auteur']);
        $this->assertSame('181-191', $gelezen['rijen'][0]['paginas']);

        unlink($pad);
    }

    public function test_bij_twijfel_wordt_er_geen_auteur_verzonnen(): void
    {
        $pad = $this->bestand([
            'ISLAMIC STUDIES   REK N: 3',
            'Vol. 3, 1964, N: 1',
            'CONTENTS',
            // Geen herkenbare auteursnaam: dan blijft alles de titel, en komt er
            // GEEN verzonnen auteur in de catalogus.
            '· Annual table of contents 1987. 397',
        ]);

        $gelezen = (new TijdschriftImport())->lees($pad);

        $this->assertSame(1, $gelezen['statistiek']['artikelen']);
        $this->assertNull($gelezen['rijen'][0]['auteur']);
        $this->assertSame(1, $gelezen['statistiek']['zonder_auteur']);

        unlink($pad);
    }

    public function test_een_artikel_zonder_uitgave_wordt_overgeslagen_en_gemeld(): void
    {
        $pad = $this->bestand([
            'Een losse regel zonder tijdschrift',
            '· Een artikel dat nergens bij hoort - Iemand. 1-10',
        ]);

        $gelezen = (new TijdschriftImport())->lees($pad);

        $this->assertSame(0, $gelezen['statistiek']['artikelen']);
        $this->assertNotEmpty($gelezen['overgeslagen']);

        $redenen = array_column($gelezen['overgeslagen'], 'reden');
        $this->assertContains('Artikel zonder bekende uitgave.', $redenen);

        unlink($pad);
    }

    public function test_een_tweede_import_maakt_niets_dubbel(): void
    {
        $pad = $this->bestand([
            'HAMDARD ISLAMICUS   REK N: 4',
            'Vol. X / Number 4 / Winter 1987',
            'Contents',
            '· The Umma. Souran Mardini. 3—23. الأمة',
        ]);

        $import = new TijdschriftImport();
        $gelezen = $import->lees($pad);

        $eerste = $import->importeer($gelezen['rijen']);
        $tweede = $import->importeer($gelezen['rijen']);

        $this->assertSame(1, $eerste['artikelen']);
        $this->assertSame(0, $tweede['artikelen']);
        $this->assertSame(1, $tweede['bestond_al']);

        $this->assertSame(1, Artikel::count());
        $this->assertSame(1, Uitgave::count());
        $this->assertSame(1, Publicatie::where('soort_id', Publicatiesoort::metCode('tijdschrift')->id)->count());

        unlink($pad);
    }

    public function test_de_artikelen_zijn_terug_te_vinden_via_de_zoekfunctie(): void
    {
        $pad = $this->bestand([
            'ISLAMIC STUDIES   REK N: 3',
            'Vol. 1, June 1962, N: 2',
            'CONTENTS',
            '· Sunnah and Hadith - Fazlur Rahman ... 1 – 36 السنة والحديث',
        ]);

        $import = new TijdschriftImport();
        $import->importeer($import->lees($pad)['rijen']);

        // Op artikeltitel, op auteur, op de Arabische vertaling én op de ruwe
        // bronregel — alle vier vinden hetzelfde artikel terug.
        foreach (['Sunnah', 'Fazlur', 'السنة'] as $zoekterm) {
            $this->assertSame(1, Artikel::zoek($zoekterm)->count(), 'Niet gevonden op: '.$zoekterm);
        }

        unlink($pad);
    }
}
