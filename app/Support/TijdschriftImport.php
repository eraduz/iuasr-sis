<?php

namespace App\Support;

use App\Models\Bibliotheek\Artikel;
use App\Models\Bibliotheek\Auteur;
use App\Models\Bibliotheek\Publicatie;
use App\Models\Bibliotheek\Publicatiesoort;
use App\Models\Bibliotheek\Uitgave;

/**
 * Import van de TIJDSCHRIFTINHOUD: de artikelen per uitgave.
 *
 * Twee bronnen, met elk hun eigen opbouw:
 *
 *  1. "Tijdschriftinhoud-Engels.xlsx" — 15.994 regels in één kolom. De opbouw is
 *     een hiërarchie die zich netjes laat lezen:
 *
 *        REK N: 3 "drie"                     <- rek (de kast)
 *        ISLAMIC STUDIES   REK N: 3          <- TIJDSCHRIFT (naam + rek)
 *        Quarterly Journal of ...            <- ondertitel (informatief)
 *        Vol. 1, June 1962, N: 2             <- UITGAVE (aflevering)
 *        CONTENTS                            <- kopje
 *        · Sunnah and Hadith - Fazlur Rahman ... 1 – 36   <- ARTIKEL
 *
 *  2. "A المجلات العربية.docx" — Arabische tijdschriften. Zelfde idee, maar dit
 *     bestand MENGT tijdschriftuitgaven met boekhoofdstukken (الفصل الثالث, ...).
 *     Die hoofdstukken zijn geen artikelen en worden NIET ingelezen.
 *
 * DE KERNREGEL (zoals bij de verrijking): bij twijfel niets aannemen. Een artikel
 * wordt alleen vastgelegd als er een tijdschrift ÉN een uitgave bekend zijn. Alle
 * overige regels worden gerapporteerd met hun regelnummer, zodat niets stilletjes
 * verdwijnt en de bibliotheek ze desgewenst met de hand kan afhandelen.
 *
 * NIETS GAAT VERLOREN: de volledige bronregel wordt bewaard in de beschrijving van
 * het artikel. Ook als de scheiding tussen titel en auteur niet zeker is, blijft de
 * oorspronkelijke tekst dus doorzoekbaar.
 */
class TijdschriftImport
{
    /** Regels die alleen een kopje zijn en dus overgeslagen mogen worden. */
    private const KOPJES = [
        'contents', 'content', 'book reviews', 'book-reviews', 'book review', 'book. review',
        'review article', 'obituary', 'obituaries', 'miscellaneous', 'index', 'errata',
    ];

    /**
     * Leest het bestand en geeft de artikelen terug met hun tijdschrift en uitgave,
     * zonder iets op te slaan.
     *
     * @return array{rijen: array<int,array<string,mixed>>, overgeslagen: array<int,array<string,string>>, statistiek: array<string,int>}
     */
    public function lees(string $pad): array
    {
        // De twee bronnen wijzen hun tijdschrift op een ANDERE manier aan:
        //  - xlsx (Engels)  : de naam staat op de kopregel, samen met "REK N: x".
        //  - docx (Arabisch): er is geen REK-kop; de regel vlak BOVEN de
        //                     deel-/jaargangregel is de naam van het tijdschrift.
        // Dat verschil expliciet maken is eerlijker dan één regel die het in beide
        // bestanden half goed doet.
        $viaKop = mb_strtolower(pathinfo($pad, PATHINFO_EXTENSION)) === 'docx';

        $rijen = [];
        $overgeslagen = [];
        $statistiek = ['gelezen' => 0, 'tijdschriften' => 0, 'uitgaven' => 0, 'artikelen' => 0, 'zonder_auteur' => 0];

        $tijdschriften = [];
        $uitgaven = [];

        // Waar we op dit moment in het bestand staan.
        $rek = null;
        $tijdschrift = null;
        $uitgave = null;
        // De laatste regel die een kop KAN zijn (geen artikel, geen uitgave, geen
        // kopje). In het Arabische bestand is dat de naam van het tijdschrift.
        $laatsteKop = null;

        foreach ($this->regels($pad) as $regelnummer => $tekst) {
            $statistiek['gelezen']++;
            $tekst = $this->schoon($tekst);

            if ($tekst === '') {
                continue;
            }

            // 1. Een rekaanduiding: "REK N: 3" — bepaalt de kast van wat volgt.
            if ($nieuwRek = $this->leesRek($tekst)) {
                $rek = $nieuwRek;

                // Een regel die ALLEEN de rek noemt, is verder geen tijdschrift.
                if ($this->isAlleenRek($tekst)) {
                    continue;
                }
            }

            // 2. Een tijdschriftkop: "ISLAMIC STUDIES   REK N: 3".
            if ($naam = $this->leesTijdschrift($tekst)) {
                $tijdschrift = $naam;
                $uitgave = null;    // een nieuw tijdschrift begint zonder uitgave
                $laatsteKop = null; // en zonder losse kop

                if (! isset($tijdschriften[$naam])) {
                    $tijdschriften[$naam] = $rek;
                    $statistiek['tijdschriften']++;
                }

                continue;
            }

            // 3. Een uitgave: "Vol. 1, June 1962, N: 2" of "المجلد الأول- 2008".
            if ($nummer = $this->leesUitgave($tekst)) {
                // In het Arabische bestand staat er geen "REK"-kop boven een
                // tijdschrift. Daar is de kop vlák boven de deel-/jaargangregel de
                // naam van het tijdschrift — dat is de enige aanwijzing die de bron
                // geeft, en ze is consequent. Is er geen kop, dan weten we het niet
                // en slaan we over.
                if ($viaKop && $laatsteKop !== null) {
                    // Elke uitgave krijgt de kop die er direct boven staat: in dit
                    // bestand volgen meerdere tijdschriften elkaar op zonder REK-kop.
                    $tijdschrift = $laatsteKop;
                    $laatsteKop = null;

                    if (! isset($tijdschriften[$tijdschrift])) {
                        $tijdschriften[$tijdschrift] = $rek;
                        $statistiek['tijdschriften']++;
                    }
                }

                if ($tijdschrift === null) {
                    $overgeslagen[] = ['regel' => $regelnummer, 'reden' => 'Uitgave zonder bekend tijdschrift.', 'tekst' => $this->kort($tekst)];

                    continue;
                }

                $uitgave = $nummer;
                $sleutel = $tijdschrift.'|'.$nummer;

                if (! isset($uitgaven[$sleutel])) {
                    $uitgaven[$sleutel] = true;
                    $statistiek['uitgaven']++;
                }

                continue;
            }

            // 4. Kopjes binnen een uitgave (CONTENTS, Book Reviews, ...) overslaan.
            if (in_array(mb_strtolower(rtrim($tekst, '.:')), self::KOPJES, true)) {
                continue;
            }

            // 5. Een artikel. Alleen als tijdschrift ÉN uitgave bekend zijn — anders
            //    weten we niet waar het bij hoort en raden we niet.
            //    Behalve het opsommingsteken telt ook: een regel BINNEN een uitgave
            //    die eindigt op een paginanummer. Zo'n regel is in deze bron een
            //    artikel waarvan het opsommingsteken ontbreekt (dat komt vaak voor).
            if ($this->isArtikel($tekst) || ($uitgave !== null && $this->isArtikelZonderTeken($tekst))) {
                if ($tijdschrift === null || $uitgave === null) {
                    $overgeslagen[] = ['regel' => $regelnummer, 'reden' => 'Artikel zonder bekende uitgave.', 'tekst' => $this->kort($tekst)];

                    continue;
                }

                $artikel = $this->leesArtikel($tekst);

                if ($artikel['titel'] === '') {
                    $overgeslagen[] = ['regel' => $regelnummer, 'reden' => 'Geen bruikbare titel.', 'tekst' => $this->kort($tekst)];

                    continue;
                }

                $rijen[] = [
                    'regel' => $regelnummer,
                    'tijdschrift' => $tijdschrift,
                    'rek' => $tijdschriften[$tijdschrift] ?? $rek,
                    'uitgave' => $uitgave,
                    'jaar' => $this->leesJaar($uitgave),
                ] + $artikel;

                $statistiek['artikelen']++;
                $statistiek['zonder_auteur'] += $artikel['auteur'] === null ? 1 : 0;

                continue;
            }

            // 6. Alles wat hier komt, past niet in de structuur: ondertitels,
            //    boekhoofdstukken uit het Arabische bestand, losse aantekeningen.
            //    Melden, niet gokken. Wél onthouden als mogelijke tijdschriftkop
            //    voor het Arabische bestand (zie stap 3).
            if (mb_strlen($tekst) >= 8 && mb_strlen($tekst) <= 120) {
                $laatsteKop = $tekst;
            }

            $overgeslagen[] = ['regel' => $regelnummer, 'reden' => 'Past niet in de structuur (geen tijdschrift, uitgave of artikel).', 'tekst' => $this->kort($tekst)];
        }

        return ['rijen' => $rijen, 'overgeslagen' => $overgeslagen, 'statistiek' => $statistiek];
    }

    /**
     * Slaat de gelezen artikelen op: het tijdschrift als publicatie, de uitgave
     * eronder, en de artikelen daaronder.
     *
     * IDEMPOTENT: een tijdschrift/uitgave/artikel dat er al is, wordt niet nog een
     * keer aangemaakt. Het commando kan dus veilig opnieuw draaien.
     *
     * @param  array<int,array<string,mixed>>  $rijen
     * @return array<string,int>
     */
    public function importeer(array $rijen): array
    {
        $resultaat = ['tijdschriften' => 0, 'uitgaven' => 0, 'artikelen' => 0, 'bestond_al' => 0];

        $soortId = Publicatiesoort::metCode('tijdschrift')?->id;
        abort_if($soortId === null, 500, 'De soort "tijdschrift" ontbreekt in de opzoektabel.');

        $tijdschriftCache = [];
        $uitgaveCache = [];

        foreach ($rijen as $rij) {
            // Het tijdschrift (publicatie van soort tijdschrift).
            $naam = $rij['tijdschrift'];

            if (! isset($tijdschriftCache[$naam])) {
                $publicatie = Publicatie::where('soort_id', $soortId)->where('titel', $naam)->first();

                if ($publicatie === null) {
                    $publicatie = Publicatie::create([
                        'soort_id' => $soortId,
                        'titel' => mb_substr($naam, 0, 255),
                        'bron_rekcode' => $rij['rek'],
                    ]);
                    $resultaat['tijdschriften']++;
                }

                $tijdschriftCache[$naam] = $publicatie;
            }

            $publicatie = $tijdschriftCache[$naam];

            // De uitgave (aflevering).
            $sleutel = $publicatie->id.'|'.$rij['uitgave'];

            if (! isset($uitgaveCache[$sleutel])) {
                $uitgave = Uitgave::where('publicatie_id', $publicatie->id)
                    ->where('uitgavenummer', $rij['uitgave'])->first();

                if ($uitgave === null) {
                    $uitgave = $publicatie->uitgaven()->create([
                        'uitgavenummer' => mb_substr($rij['uitgave'], 0, 40),
                        'jaar' => $rij['jaar'],
                    ]);
                    $resultaat['uitgaven']++;
                }

                $uitgaveCache[$sleutel] = $uitgave;
            }

            $uitgave = $uitgaveCache[$sleutel];

            // Het artikel. Bestaat het al met dezelfde titel in deze uitgave, dan
            // slaan we het over — zo blijft een tweede import schoon.
            $bestaat = Artikel::where('uitgave_id', $uitgave->id)
                ->where('titel', mb_substr($rij['titel'], 0, 255))
                ->exists();

            if ($bestaat) {
                $resultaat['bestond_al']++;

                continue;
            }

            $artikel = $uitgave->artikelen()->create([
                'titel' => mb_substr($rij['titel'], 0, 255),
                'paginas' => $rij['paginas'],
                'trefwoorden' => $rij['arabisch'] !== null ? mb_substr($rij['arabisch'], 0, 255) : null,
                // De volledige bronregel blijft bewaard: niets gaat verloren, en het
                // artikel is ook vindbaar op woorden die wij niet hebben herkend.
                'beschrijving' => $rij['ruw'],
            ]);

            if ($rij['auteur'] !== null) {
                $artikel->auteurs()->sync(Auteur::idsVoorNamen([$rij['auteur']]));
            }

            $resultaat['artikelen']++;
        }

        return $resultaat;
    }

    /* ====================================================================
     | Het lezen van de bron: xlsx (één kolom) of docx (alinea's)
     |=================================================================== */

    /** @return \Generator<int,string> regelnummer => tekst */
    private function regels(string $pad): \Generator
    {
        $extensie = mb_strtolower(pathinfo($pad, PATHINFO_EXTENSION));

        return match ($extensie) {
            'xlsx' => $this->regelsUitXlsx($pad),
            'docx' => $this->regelsUitDocx($pad),
            default => throw new \RuntimeException('Onbekend bestandstype: .'.$extensie.' (verwacht .xlsx of .docx).'),
        };
    }

    /** Kolom A van het eerste werkblad, rij voor rij (streamend: 16.000 regels). */
    private function regelsUitXlsx(string $pad): \Generator
    {
        $zip = new \ZipArchive();

        if ($zip->open($pad) !== true) {
            throw new \RuntimeException('Het bestand kon niet worden geopend.');
        }

        try {
            $gedeeld = $this->gedeeldeTeksten($zip);

            $reader = new \XMLReader();
            $reader->XML($zip->getFromName('xl/worksheets/sheet1.xml'), 'UTF-8');

            while ($reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->name !== 'row') {
                    continue;
                }

                $regelnummer = (int) $reader->getAttribute('r');
                $rij = new \SimpleXMLElement($reader->readOuterXml());

                foreach ($rij->c as $cel) {
                    if (preg_replace('/\d+/', '', (string) $cel['r']) !== 'A') {
                        continue; // alleen kolom A
                    }

                    $type = (string) $cel['t'];
                    $waarde = $type === 'inlineStr'
                        ? (string) $cel->is->t
                        : ($type === 's' ? ($gedeeld[(int) $cel->v] ?? '') : (string) $cel->v);

                    if (trim($waarde) !== '') {
                        yield $regelnummer => $waarde;
                    }
                }
            }

            $reader->close();
        } finally {
            $zip->close();
        }
    }

    /** De alinea's van een Word-bestand, op volgorde. */
    private function regelsUitDocx(string $pad): \Generator
    {
        $zip = new \ZipArchive();

        if ($zip->open($pad) !== true) {
            throw new \RuntimeException('Het bestand kon niet worden geopend.');
        }

        try {
            $xml = $zip->getFromName('word/document.xml');

            if ($xml === false) {
                throw new \RuntimeException('Dit is geen leesbaar Word-bestand.');
            }

            $document = new \SimpleXMLElement($xml);
            $document->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            $regelnummer = 0;

            foreach ($document->xpath('//w:p') ?: [] as $alinea) {
                $regelnummer++;
                $alinea->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

                $tekst = implode('', array_map('strval', $alinea->xpath('.//w:t') ?: []));

                if (trim($tekst) !== '') {
                    yield $regelnummer => $tekst;
                }
            }
        } finally {
            $zip->close();
        }
    }

    /** @return array<int,string> */
    private function gedeeldeTeksten(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $teksten = [];
        $reader = new \XMLReader();
        $reader->XML($xml, 'UTF-8');

        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'si') {
                $si = new \SimpleXMLElement($reader->readOuterXml());
                $teksten[] = implode('', array_map('strval', $si->xpath('.//*[local-name()="t"]') ?: []));
            }
        }

        $reader->close();

        return $teksten;
    }

    /* ====================================================================
     | Het herkennen van de structuur
     |=================================================================== */

    private function schoon(string $tekst): string
    {
        // Harde spaties en dubbele witruimte wegwerken; opsommingstekens houden.
        $tekst = str_replace(["\u{00A0}", "\t"], ' ', $tekst);

        return trim(preg_replace('/\s+/u', ' ', $tekst));
    }

    /** "REK N: 3" of "REK, N: 2" of het Arabische "C 47" → de rekcode. */
    private function leesRek(string $tekst): ?string
    {
        if (preg_match('/REK[.,:]?\s*N[:.]?\s*(\d+)/iu', $tekst, $t)) {
            return 'N. '.$t[1];
        }

        // Arabische bron: een losse rekcode als "C 47".
        if (preg_match('/(?<![A-Za-z])([A-Z])\s*(\d{1,3})(?![\d])/u', $tekst, $t) && mb_strlen($tekst) < 60) {
            return $t[1].'. '.$t[2];
        }

        return null;
    }

    /** Is dit een regel die ALLEEN de rek aankondigt? ("REK N: 2 “twee” الرف الثاني") */
    private function isAlleenRek(string $tekst): bool
    {
        return (bool) preg_match('/^REK\b/iu', $tekst);
    }

    /**
     * Een tijdschriftkop: de naam staat vóór "REK N: x" op dezelfde regel.
     * Bijvoorbeeld "ISLAMIC STUDIES   REK N: 3" → "ISLAMIC STUDIES".
     */
    private function leesTijdschrift(string $tekst): ?string
    {
        // LET OP: sommige ARTIKELTITELS bevatten zelf "REK N: 4" (bijvoorbeeld
        // "· 'Mutiny' and The Moslim world REK N: 4: a British Viewpoint - ...").
        // Zonder deze controle wordt zo'n artikel als een nieuw tijdschrift gelezen
        // en raken alle volgende artikelen hun uitgave kwijt. Een tijdschriftkop
        // begint nooit met een opsommingsteken.
        if ($this->isArtikel($tekst)) {
            return null;
        }

        if (! preg_match('/^(.+?)\s+REK[.,:]?\s*N[:.]?\s*\d+/iu', $tekst, $t)) {
            return null;
        }

        $naam = trim($t[1], " .,-–—:");

        return $naam !== '' ? $naam : null;
    }

    /**
     * Een uitgave (aflevering). Engels: "Vol. 1, June 1962, N: 2" of
     * "Vol. X / Number 4 / Winter 1987". Arabisch: een regel met المجلد (deel).
     */
    private function leesUitgave(string $tekst): ?string
    {
        if (preg_match('/^(?:vol\.?|volume)\b/iu', $tekst) || preg_match('/^N\s*:\s*\d/u', $tekst)) {
            return $this->kort($tekst, 40);
        }

        if (mb_strpos($tekst, 'المجلد') !== false) {
            return $this->kort($tekst, 40);
        }

        return null;
    }

    /** Het jaartal uit een uitgaveregel (1962, 1987, ...). */
    private function leesJaar(string $tekst): ?int
    {
        if (preg_match('/\b(1[89]\d{2}|20[0-4]\d)\b/u', $tekst, $t)) {
            return (int) $t[1];
        }

        return null;
    }

    /** Een artikelregel begint met een opsommingsteken of een nummer. */
    private function isArtikel(string $tekst): bool
    {
        return (bool) preg_match('/^[·●•\*\-–—]\s*\S/u', $tekst)
            || (bool) preg_match('/^\d{1,2}\s*[.)]\s+\S/u', $tekst);
    }

    /**
     * Een artikelregel ZONDER opsommingsteken, binnen een uitgave: lang genoeg en
     * eindigend op een paginanummer of -bereik. Zo blijven de duizenden regels die
     * in de bron zonder teken staan tóch behouden.
     *
     * Bewust NIET meegenomen: de korte inhoudsopgave-regels vooraan een jaargang
     * ("a. According to subjects. 1", "c. Plates 100"). Die beginnen met één letter
     * plus punt en zijn kort; het zijn verwijzingen, geen artikelen.
     */
    private function isArtikelZonderTeken(string $tekst): bool
    {
        if (mb_strlen($tekst) < 25) {
            return false;
        }

        if (preg_match('/^[a-z]\s*[.)]\s/u', $tekst)) {
            return false; // "a. According to subjects. 1"
        }

        return (bool) preg_match('/\d{1,4}\s*[-–—]\s*\d{1,4}\s*\.?$|\d{1,4}\s*\.?$/u', $tekst);
    }

    /**
     * Eén artikelregel uit elkaar halen. Wat zeker is, wordt apart vastgelegd;
     * wat onzeker is, blijft in de ruwe tekst staan in plaats van dat er iets
     * verzonnen wordt.
     *
     *   "· Sunnah and Hadith - Fazlur Rahman ... 1 – 36 السنة والحديث"
     *      titel   : Sunnah and Hadith
     *      auteur  : Fazlur Rahman
     *      pagina's: 1-36
     *      arabisch: السنة والحديث
     *
     * @return array{titel:string,auteur:?string,paginas:?string,arabisch:?string,ruw:string}
     */
    private function leesArtikel(string $tekst): array
    {
        $ruw = $tekst;

        // Het opsommingsteken of volgnummer eraf.
        $rest = preg_replace('/^([·●•\*\-–—]|\d{1,2}\s*[.)])\s*/u', '', $tekst);

        // De Arabische staart (indien aanwezig) apart houden: dat is de vertaalde
        // titel, geen deel van de Engelse titel.
        $arabisch = null;
        if (preg_match('/([\p{Arabic}][\p{Arabic}\s\p{P}]*)$/u', $rest, $t)) {
            $arabisch = trim($t[1]);
            $rest = trim(mb_substr($rest, 0, mb_strpos($rest, $t[1])));
        }

        // De pagina's: het laatste getal(-bereik) in de regel.
        $paginas = null;
        if (preg_match('/(\d{1,4}\s*[-–—]\s*\d{1,4}|\d{1,4})\s*\.?\s*$/u', $rest, $t)) {
            $paginas = preg_replace('/\s*[-–—]\s*/u', '-', trim($t[1]));
            $rest = trim(mb_substr($rest, 0, mb_strrpos($rest, $t[1])));
        }

        // Wat overblijft is "titel - auteur" (of alleen een titel). De scheiding is
        // in de bron niet consequent; daarom alleen splitsen als het resultaat
        // OVERTUIGEND een naam is. Anders blijft alles de titel — nooit gokken.
        $rest = trim($rest, " .,:;-–—…");
        $titel = $rest;
        $auteur = null;

        foreach ([' - ', ' – ', ' — ', '... ', '.. ', '. '] as $scheiding) {
            $plek = mb_strrpos($rest, $scheiding);

            if ($plek === false || $plek < 10) {
                continue;
            }

            $kandidaat = trim(mb_substr($rest, $plek + mb_strlen($scheiding)), " .,:;-–—…");

            if ($this->lijktOpNaam($kandidaat)) {
                $titel = trim(mb_substr($rest, 0, $plek), " .,:;-–—…");
                $auteur = $kandidaat;

                break;
            }
        }

        return [
            'titel' => $titel,
            'auteur' => $auteur,
            'paginas' => $paginas,
            'arabisch' => $arabisch,
            'ruw' => mb_substr($ruw, 0, 2000),
        ];
    }

    /**
     * Lijkt dit op een persoonsnaam? Hoogstens zes woorden, geen cijfers, en niet
     * een van de bekende kopjes. Bij twijfel: NEE — dan blijft het in de titel
     * staan, en dat is beter dan een verzonnen auteur in de catalogus.
     */
    private function lijktOpNaam(string $kandidaat): bool
    {
        if ($kandidaat === '' || mb_strlen($kandidaat) > 60 || preg_match('/\d/u', $kandidaat)) {
            return false;
        }

        // preg_split geeft FALSE bij ongeldige UTF-8 — die zit in deze bron. Dan
        // weten we het niet zeker, en dus: geen auteur.
        $woorden = preg_split('/\s+/u', $kandidaat) ?: [];

        if ($woorden === [] || count($woorden) > 6) {
            return false;
        }

        if (in_array(mb_strtolower($kandidaat), self::KOPJES, true)) {
            return false;
        }

        // Een naam begint met een hoofdletter of is Arabisch schrift.
        return (bool) preg_match('/^[\p{Lu}\p{Arabic}]/u', $kandidaat);
    }

    private function kort(string $tekst, int $lengte = 120): string
    {
        return mb_substr($tekst, 0, $lengte);
    }
}
