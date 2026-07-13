<?php

namespace Tests\Feature;

use App\Enums\PublicatieSoort;
use App\Enums\Rol;
use App\Models\Bibliotheek\Exemplaar;
use App\Models\Bibliotheek\Publicatie;
use App\Models\User;
use App\Support\BibliotheekImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Import van de bestaande Excel-bibliotheek. Legt de normalisatieregels vast die
 * nodig zijn omdat de bron met de hand is gegroeid: het vakgebied komt uit de
 * rekletter (de vakgebiedkolom kent 144 spellingvarianten), taalfouten worden
 * gecorrigeerd, niet-talen verhuizen naar de opmerking, het aantal wordt het
 * aantal exemplaren, en het werkblad R heeft verschoven kolommen.
 */
class BibliotheekImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bouwt een klein Excel-bestand met dezelfde structuur als de bron.
     *
     * @param  array<int,array<int,string|int|null>>  $rijen
     */
    private function bestand(array $rijen): string
    {
        $spreadsheet = new Spreadsheet();
        $blad = $spreadsheet->getActiveSheet();
        $blad->setTitle(BibliotheekImport::WERKBLAD);

        // Een banner- en een kopregel, net als in de bron.
        $blad->fromArray(['A', '', '', '', 'Exegese (Quran)'], null, 'A1');
        $blad->fromArray(['Rek: A', 'Aantal', 'Tafsir', 'Taal', 'Naam van het boek', 'Naam van de schrijver', 'Opmerkingen'], null, 'A2');
        $blad->fromArray($rijen, null, 'A3');

        $pad = sys_get_temp_dir().'/biebimport-'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($pad);

        return $pad;
    }

    public function test_een_titel_met_drie_exemplaren_wordt_correct_ingelezen(): void
    {
        $pad = $this->bestand([
            ['F. 143', 3, 'Fiqh', 'Arabisch', 'Al-mawsou3a al-fiqhiya', 'As-sarkhassi', 'Encyclopedie'],
        ]);

        $import = new BibliotheekImport();
        $gelezen = $import->lees($pad);

        $this->assertSame(1, $gelezen['statistiek']['titels']);
        $this->assertSame(3, $gelezen['statistiek']['exemplaren']);

        $import->importeer($gelezen['rijen']);

        $publicatie = Publicatie::where('bron_rekcode', 'F. 143')->firstOrFail();
        $this->assertSame('Al-mawsou3a al-fiqhiya', $publicatie->titel);
        $this->assertSame(PublicatieSoort::Boek, $publicatie->soort);
        $this->assertSame('As-sarkhassi', $publicatie->auteurs->first()->naam);
        $this->assertSame('Arabisch', $publicatie->talen->first()->naam);

        // Vakgebied uit de REKLETTER (F = Fiqh), niet uit de vervuilde kolom.
        $this->assertSame('F', $publicatie->vakgebied->rekletter);

        // Drie exemplaren, genummerd vanuit de rekcode, in kast F.
        $serienummers = $publicatie->exemplaren->pluck('serienummer')->sort()->values()->all();
        $this->assertSame(['F.143-1', 'F.143-2', 'F.143-3'], $serienummers);
        $this->assertSame('F', $publicatie->exemplaren->first()->kast->code);

        unlink($pad);
    }

    public function test_taalfouten_worden_gecorrigeerd_en_niet_talen_verhuizen_naar_de_opmerking(): void
    {
        $pad = $this->bestand([
            ['A - 1', 1, 'Tafsir', 'Arabish', 'Boek met taalfout', 'Auteur A', null],
            ['A - 2', 1, 'Tafsir', 'Nederlans', 'Boek met tweede taalfout', 'Auteur B', null],
            ['G. 1', 1, 'Taal', 'woordenboeken', 'Boek zonder echte taal', 'Auteur C', null],
        ]);

        $import = new BibliotheekImport();
        $gelezen = $import->lees($pad);
        $import->importeer($gelezen['rijen']);

        $this->assertSame('Arabisch', Publicatie::where('bron_rekcode', 'A - 1')->firstOrFail()->talen->first()->naam);
        $this->assertSame('Nederlands', Publicatie::where('bron_rekcode', 'A - 2')->firstOrFail()->talen->first()->naam);

        // 'woordenboeken' is geen taal: het boek krijgt er geen, maar de waarde gaat
        // niet verloren — die staat in de opmerking.
        $zonderTaal = Publicatie::where('bron_rekcode', 'G. 1')->firstOrFail();
        $this->assertCount(0, $zonderTaal->talen);
        $this->assertStringContainsString('Bron-taal: woordenboeken', $zonderTaal->opmerking);
        $this->assertSame(1, $gelezen['statistiek']['zonder_taal']);

        unlink($pad);
    }

    public function test_een_onwaarschijnlijk_aantal_wordt_gecorrigeerd_naar_een_exemplaar(): void
    {
        // In de echte bron staat bij B - 777 het getal 41306: een tikfout.
        $pad = $this->bestand([
            ['B - 777', 41306, 'Quran wetenschappen', 'Turks', 'Kur an in ana konulari', 'Auteur', null],
            ['B - 778', '8 vol', 'Quran wetenschappen', 'Turks', 'Boek met tekst in de aantalkolom', 'Auteur', null],
        ]);

        $import = new BibliotheekImport();
        $gelezen = $import->lees($pad);
        $import->importeer($gelezen['rijen']);

        $this->assertSame(2, $gelezen['statistiek']['aantal_gecorrigeerd']);
        $this->assertSame(2, $gelezen['statistiek']['exemplaren'], 'Beide regels leveren één exemplaar op.');

        $publicatie = Publicatie::where('bron_rekcode', 'B - 777')->firstOrFail();
        $this->assertCount(1, $publicatie->exemplaren);
        $this->assertStringContainsString('41306', $publicatie->opmerking);

        unlink($pad);
    }

    public function test_het_afwijkende_werkblad_r_heeft_verschoven_kolommen(): void
    {
        // Bij rek R staat de titel in de kolom waar elders de taal staat.
        $pad = $this->bestand([
            ['R.1', 1, 'Taal - Nederlands', 'Zakenbrievenboek deel 12', 'Samsom uitgeverij', 'Onderwijs', null],
        ]);

        $import = new BibliotheekImport();
        $gelezen = $import->lees($pad);
        $import->importeer($gelezen['rijen']);

        $publicatie = Publicatie::where('bron_rekcode', 'R.1')->firstOrFail();
        $this->assertSame('Zakenbrievenboek deel 12', $publicatie->titel);
        $this->assertSame('Samsom uitgeverij', $publicatie->auteurs->first()->naam);

        unlink($pad);
    }

    public function test_regels_zonder_titel_worden_overgeslagen_met_reden(): void
    {
        $pad = $this->bestand([
            ['A - 67', 1, 'Tafsir', 'Arabisch', null, 'Auteur zonder boek', null],
            ['A - 68', 1, 'Tafsir', 'Arabisch', 'Wel een titel', 'Auteur', null],
        ]);

        $gelezen = (new BibliotheekImport())->lees($pad);

        $this->assertSame(1, $gelezen['statistiek']['titels']);
        $this->assertCount(1, $gelezen['overgeslagen']);
        $this->assertSame('A - 67', $gelezen['overgeslagen'][0]['rekcode']);
        $this->assertStringContainsString('Geen titel', $gelezen['overgeslagen'][0]['reden']);

        unlink($pad);
    }

    public function test_een_tweede_import_maakt_niets_dubbel(): void
    {
        $pad = $this->bestand([
            ['F. 143', 2, 'Fiqh', 'Arabisch', 'Al-mawsou3a al-fiqhiya', 'As-sarkhassi', null],
        ]);

        $import = new BibliotheekImport();
        $gelezen = $import->lees($pad);

        $eerste = $import->importeer($gelezen['rijen']);
        $tweede = $import->importeer($gelezen['rijen']);

        $this->assertSame(1, $eerste['titels']);
        $this->assertSame(0, $tweede['titels']);
        $this->assertSame(1, $tweede['overgeslagen_bestond_al']);

        $this->assertSame(1, Publicatie::count());
        $this->assertSame(2, Exemplaar::count());

        unlink($pad);
    }

    public function test_een_tijdschriftregel_wordt_geen_boek(): void
    {
        $pad = $this->bestand([
            ['C . 1', 'Tijdschriften', 'Tijdschriften', 'Engels', 'The United Nations Response', 'Zubair Ahmad', null],
        ]);

        $import = new BibliotheekImport();
        $gelezen = $import->lees($pad);
        $import->importeer($gelezen['rijen']);

        $this->assertSame(1, $gelezen['statistiek']['tijdschriften']);
        $this->assertSame(PublicatieSoort::Tijdschrift, Publicatie::where('bron_rekcode', 'C . 1')->firstOrFail()->soort);

        unlink($pad);
    }

    public function test_alleen_de_bibliotheekmedewerker_mag_importeren(): void
    {
        $maak = fn (Rol $rol) => User::create(['naam' => 'T', 'email' => $rol->value.'@iuasr.test', 'rol' => $rol]);

        $this->actingAs($maak(Rol::Bibliotheek))->get(route('bibliotheek.import'))->assertOk();
        $this->actingAs($maak(Rol::Beheerder))->get(route('bibliotheek.import'))->assertOk();
        $this->actingAs($maak(Rol::Bestuur))->get(route('bibliotheek.import'))->assertForbidden();
        $this->actingAs($maak(Rol::Studentenzaken))->get(route('bibliotheek.import'))->assertForbidden();
    }
}
