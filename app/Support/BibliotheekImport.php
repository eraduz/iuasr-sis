<?php

namespace App\Support;

use App\Enums\ExemplaarStatus;
use App\Models\Bibliotheek\Publicatiesoort;
use App\Models\Bibliotheek\Auteur;
use App\Models\Bibliotheek\Exemplaar;
use App\Models\Bibliotheek\Kast;
use App\Models\Bibliotheek\Publicatie;
use App\Models\Bibliotheek\Taal;
use App\Models\Bibliotheek\Vakgebied;

/**
 * Import van de bestaande Excel-bibliotheek (het bestand "Boeken bibliotheek.xlsx",
 * werkblad "Alle Boeken"). Zet elke bronregel om in één TITEL met daaronder het
 * opgegeven aantal EXEMPLAREN.
 *
 * De bron is met de hand gegroeid en daarom vervuild. De regels hieronder maken
 * er iets bruikbaars van, zonder gegevens weg te gooien:
 *
 *  - VAKGEBIED komt uit de REKLETTER (A-1 → A → Tafsir), niet uit de vakgebied-
 *    kolom: die bevat 144 spellingvarianten van dezelfde begrippen. De
 *    oorspronkelijke tekst wordt bewaard in de opmerking, zodat niets verdwijnt.
 *  - TAAL wordt genormaliseerd (Arabish/Aabisch → Arabisch, Nederlans →
 *    Nederlands, ...). Waarden die geen taal zijn (woordenboeken, Grammatica,
 *    Poesie) verhuizen naar de opmerking; het boek krijgt dan geen taal.
 *  - AANTAL is het aantal fysieke exemplaren. Serienummers worden afgeleid van de
 *    rekcode: "F. 143" met aantal 3 → F.143-1, F.143-2, F.143-3.
 *  - Werkblad R heeft VERSCHOVEN KOLOMMEN in de bron (titel staat in de kolom
 *    waar elders de taal staat); dat wordt per rekletter opgevangen.
 *  - Een aantal boven de 50 is vrijwel zeker een tikfout (er staat één keer 41306);
 *    zo'n regel wordt ingelezen met 1 exemplaar en gemeld.
 *
 * IDEMPOTENT: de rekcode wordt vastgelegd op de publicatie (`bron_rekcode`).
 * Een tweede import slaat de al ingelezen regels over.
 */
class BibliotheekImport
{
    /** Het werkblad dat alle regels bevat. */
    public const WERKBLAD = 'Alle Boeken';

    /** Boven dit aantal is de aantalkolom vrijwel zeker een tikfout. */
    private const MAX_AANNEMELIJK_AANTAL = 50;

    /** Tikfouten en varianten in de taalkolom → de taal zoals wij die kennen. */
    private const TAALKAART = [
        'arabisch' => 'ar', 'arabish' => 'ar', 'aabisch' => 'ar', 'arabic' => 'ar', 'عربية' => 'ar',
        'turks' => 'tr', 'turk' => 'tr', 'turkce' => 'tr', 'türkçe' => 'tr',
        'engels' => 'en', 'english' => 'en',
        'nederlands' => 'nl', 'nederlans' => 'nl', 'nederland' => 'nl', 'dutch' => 'nl',
        'frans' => 'fr', 'francais' => 'fr', 'français' => 'fr',
        'duits' => 'de', 'deutsch' => 'de',
        'spaans' => 'es', 'spans' => 'es',
        'albanees' => 'sq', 'albania' => 'sq',
    ];

    /**
     * Leest het bestand en geeft per bronregel een genormaliseerde rij terug,
     * zonder iets op te slaan. Dit voedt zowel het voorbeeldscherm (proefdraaien)
     * als de echte import.
     *
     * @return array{rijen: array<int,array<string,mixed>>, overgeslagen: array<int,array<string,string>>, statistiek: array<string,int>}
     */
    public function lees(string $pad): array
    {
        $rijen = [];
        $overgeslagen = [];
        $statistiek = ['gelezen' => 0, 'titels' => 0, 'exemplaren' => 0, 'tijdschriften' => 0, 'zonder_taal' => 0, 'aantal_gecorrigeerd' => 0];

        // Streamend lezen: het bestand telt ruim 12.000 regels. Een bibliotheek die
        // het hele werkblad in het geheugen laadt, loopt daarop vast (getest: 128 MB
        // is niet genoeg). Deze lezer loopt het XML rij voor rij door.
        foreach ($this->rijenUitBestand($pad) as $regelnummer => $kolommen) {
            $statistiek['gelezen']++;

            $rij = $this->normaliseer($kolommen, $regelnummer);

            if ($rij === null) {
                continue; // Kop-, banner- of lege regel: geen boek, geen melding.
            }

            if ($rij['fout'] !== null) {
                $overgeslagen[] = ['regel' => $regelnummer, 'rekcode' => $rij['rekcode'] ?? '', 'reden' => $rij['fout']];

                continue;
            }

            $statistiek['titels']++;
            $statistiek['exemplaren'] += $rij['aantal'];
            $statistiek['tijdschriften'] += $rij['soortcode'] === 'tijdschrift' ? 1 : 0;
            $statistiek['zonder_taal'] += $rij['taalcode'] === null ? 1 : 0;
            $statistiek['aantal_gecorrigeerd'] += $rij['aantal_gecorrigeerd'] ? 1 : 0;

            $rijen[] = $rij;
        }

        return ['rijen' => $rijen, 'overgeslagen' => $overgeslagen, 'statistiek' => $statistiek];
    }

    /**
     * Slaat de gelezen rijen op. Regels waarvan de rekcode al is ingelezen worden
     * overgeslagen, zodat een tweede import niets dubbel aanmaakt.
     *
     * @param  array<int,array<string,mixed>>  $rijen
     * @return array<string,int>
     */
    public function importeer(array $rijen): array
    {
        $resultaat = ['titels' => 0, 'exemplaren' => 0, 'overgeslagen_bestond_al' => 0];

        $soorten = Publicatiesoort::all()->keyBy('code');
        $vakgebieden = Vakgebied::whereNotNull('rekletter')->get()->keyBy('rekletter');
        $kasten = Kast::all()->keyBy('code');
        $talen = Taal::all()->keyBy('code');

        // Wat er vóór deze import al was ingelezen: die regels worden overgeslagen.
        $alGeimporteerd = Publicatie::whereNotNull('bron_rekcode')->pluck('bron_rekcode')->flip();

        // In de bron komen 39 rekcodes meer dan één keer voor. Dat zijn afzonderlijke
        // boeken (of dubbele regels) — ze mogen NIET tegen elkaar wegvallen. Binnen
        // deze import krijgt een herhaalde code daarom een achtervoegsel, zodat de
        // titel wél wordt aangemaakt én een tweede import nog steeds niets dubbel doet.
        $gebruiktInDezeRun = [];

        foreach ($rijen as $rij) {
            $bronsleutel = $rij['rekcode'];

            if (isset($gebruiktInDezeRun[$bronsleutel])) {
                $gebruiktInDezeRun[$bronsleutel]++;
                $bronsleutel = $rij['rekcode'].' ('.$gebruiktInDezeRun[$rij['rekcode']].')';
            } else {
                $gebruiktInDezeRun[$bronsleutel] = 1;
            }

            if ($alGeimporteerd->has($bronsleutel)) {
                $resultaat['overgeslagen_bestond_al']++;

                continue;
            }

            $publicatie = Publicatie::create([
                'soort_id' => $soorten[$rij['soortcode']]->id ?? null,
                'titel' => $rij['titel'],
                'vakgebied_id' => $vakgebieden[$rij['rekletter']]->id ?? null,
                'opmerking' => $rij['opmerking'],
                'bron_rekcode' => $bronsleutel,
            ]);

            if ($rij['auteur'] !== null) {
                $publicatie->auteurs()->sync(Auteur::idsVoorNamen([$rij['auteur']]));
            }

            if ($rij['taalcode'] !== null && isset($talen[$rij['taalcode']])) {
                $publicatie->talen()->sync([$talen[$rij['taalcode']]->id]);
            }

            $kastId = $kasten[$rij['rekletter']]->id ?? null;

            for ($nummer = 1; $nummer <= $rij['aantal']; $nummer++) {
                $serienummer = $this->vrijSerienummer($rij['rekcode'], $nummer);

                $publicatie->exemplaren()->create([
                    'serienummer' => $serienummer,
                    'kast_id' => $kastId,
                    'status' => ExemplaarStatus::Beschikbaar,
                ]);

                $resultaat['exemplaren']++;
            }

            $alGeimporteerd[$bronsleutel] = true;
            $resultaat['titels']++;
        }

        return $resultaat;
    }

    /**
     * Loopt het werkblad rij voor rij door zonder het hele bestand in het geheugen
     * te laden. Een .xlsx is een ZIP met XML; hier wordt alleen het gevraagde
     * werkblad gestreamd.
     *
     * @return \Generator<int,array<int,string>>  regelnummer => kolommen (0 = A, 1 = B, ...)
     */
    private function rijenUitBestand(string $pad): \Generator
    {
        $zip = new \ZipArchive();

        if ($zip->open($pad) !== true) {
            throw new \RuntimeException('Het bestand kon niet worden geopend; is het wel een .xlsx?');
        }

        try {
            $werkbladPad = $this->zoekWerkblad($zip);
            $gedeeldeTeksten = $this->gedeeldeTeksten($zip);

            $reader = new \XMLReader();
            $reader->XML($zip->getFromName($werkbladPad), 'UTF-8');

            $regelnummer = 0;

            while ($reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->name !== 'row') {
                    continue;
                }

                $regelnummer = (int) ($reader->getAttribute('r') ?: $regelnummer + 1);
                $rij = new \SimpleXMLElement($reader->readOuterXml());
                $kolommen = [];

                foreach ($rij->c as $cel) {
                    $verwijzing = (string) $cel['r'];                       // bv. "C123"
                    $index = $this->kolomIndex(preg_replace('/\d+/', '', $verwijzing));
                    $type = (string) $cel['t'];

                    if ($type === 'inlineStr') {
                        $waarde = (string) $cel->is->t;
                    } elseif ($type === 's') {
                        $waarde = $gedeeldeTeksten[(int) $cel->v] ?? '';
                    } else {
                        $waarde = (string) $cel->v;
                    }

                    $kolommen[$index] = $waarde;
                }

                if ($kolommen !== []) {
                    // Gaten opvullen, zodat de kolomindexen kloppen (A = 0, B = 1, ...).
                    yield $regelnummer => array_replace(array_fill(0, max(array_keys($kolommen)) + 1, ''), $kolommen);
                }
            }

            $reader->close();
        } finally {
            $zip->close();
        }
    }

    /** Het pad binnen de ZIP van het werkblad met de naam self::WERKBLAD. */
    private function zoekWerkblad(\ZipArchive $zip): string
    {
        $werkboek = new \SimpleXMLElement($zip->getFromName('xl/workbook.xml'));
        $relaties = new \SimpleXMLElement($zip->getFromName('xl/_rels/workbook.xml.rels'));

        $doelen = [];
        foreach ($relaties->Relationship as $relatie) {
            $doelen[(string) $relatie['Id']] = (string) $relatie['Target'];
        }

        foreach ($werkboek->sheets->sheet as $blad) {
            if ((string) $blad['name'] !== self::WERKBLAD) {
                continue;
            }

            $id = (string) $blad->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
            $doel = ltrim($doelen[$id] ?? '', '/');

            return str_starts_with($doel, 'xl/') ? $doel : 'xl/'.$doel;
        }

        throw new \RuntimeException('Het werkblad "'.self::WERKBLAD.'" ontbreekt in dit bestand.');
    }

    /**
     * De gedeelde tekstentabel van het bestand (xlsx bewaart tekst één keer en
     * verwijst er per cel naar).
     *
     * @return array<int,string>
     */
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
                // Een tekst kan uit meerdere stukken bestaan (opmaak); plak ze aaneen.
                $teksten[] = implode('', array_map('strval', $si->xpath('.//*[local-name()="t"]') ?: []));
            }
        }

        $reader->close();

        return $teksten;
    }

    /** "A" → 0, "B" → 1, ... "AA" → 26. */
    private function kolomIndex(string $letters): int
    {
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }

    /**
     * Zet één bronregel om. Geeft null terug als het geen boekregel is (kop,
     * banner, lege regel), en een rij met 'fout' als de regel onbruikbaar is.
     *
     * @param  array<int,mixed>  $k  de kolommen A..G (0-geïndexeerd)
     * @return array<string,mixed>|null
     */
    private function normaliseer(array $kolommen, int $regelnummer): ?array
    {
        $rekcode = trim((string) ($kolommen[0] ?? ''));

        // Een boekregel begint met een rekcode: letter + scheidingsteken + cijfer.
        if (! preg_match('/^([A-Z])\s*[-.\s]\s*\d/u', $rekcode, $treffer)) {
            return null;
        }

        $rekletter = $treffer[1];

        // Werkblad R heeft in de bron verschoven kolommen: daar staat de taal in C,
        // de titel in D en de auteur in E, terwijl het elders D/E/F is.
        [$taalKolom, $titelKolom, $auteurKolom] = $rekletter === 'R' ? [2, 3, 4] : [3, 4, 5];

        $titel = trim((string) ($kolommen[$titelKolom] ?? ''));
        $auteur = trim((string) ($kolommen[$auteurKolom] ?? ''));
        $taalTekst = trim((string) ($kolommen[$taalKolom] ?? ''));
        $vakgebiedTekst = trim((string) ($kolommen[2] ?? ''));
        $opmerkingTekst = trim((string) ($kolommen[6] ?? ''));
        $aantalTekst = trim((string) ($kolommen[1] ?? ''));

        // LET OP: de +-operator overschrijft bestaande sleutels NIET; de foutregel
        // wordt daarom expliciet samengesteld.
        $basis = ['regel' => $regelnummer, 'rekcode' => $rekcode, 'rekletter' => $rekletter];

        if ($titel === '') {
            return $basis + ['fout' => 'Geen titel in de bron.'];
        }

        // Een regel met "Tijdschriften" in de aantalkolom is een tijdschrift, geen boek.
        $isTijdschrift = mb_stripos($aantalTekst, 'tijdschrift') !== false;

        // Aantal exemplaren. Onleesbaar of onwaarschijnlijk hoog → 1, met melding.
        $aantalGecorrigeerd = false;
        $aantal = 1;

        if (! $isTijdschrift) {
            if (preg_match('/^\d+$/', $aantalTekst) === 1) {
                $gelezen = (int) $aantalTekst;

                if ($gelezen >= 1 && $gelezen <= self::MAX_AANNEMELIJK_AANTAL) {
                    $aantal = $gelezen;
                } else {
                    $aantalGecorrigeerd = true; // bijv. de 41306 bij B - 777
                }
            } elseif ($aantalTekst !== '') {
                $aantalGecorrigeerd = true; // bijv. "8 vol"
            }
        }

        // Taal normaliseren; wat geen taal is, verhuist naar de opmerking.
        $taalcode = self::TAALKAART[mb_strtolower($taalTekst)] ?? null;
        $taalRest = $taalcode === null && $taalTekst !== '' ? $taalTekst : null;

        // Niets uit de bron gaat verloren: de oorspronkelijke vakgebied- en
        // taalkolom worden bewaard in de opmerking.
        $opmerking = collect([
            $opmerkingTekst ?: null,
            $vakgebiedTekst ? 'Bron-vakgebied: '.$vakgebiedTekst : null,
            $taalRest ? 'Bron-taal: '.$taalRest : null,
            $aantalGecorrigeerd ? 'Let op: het aantal in de bron ("'.$aantalTekst.'") is onbruikbaar; ingelezen als 1 exemplaar.' : null,
            'Bron: Excel-bibliotheek, rek '.$rekcode.'.',
        ])->filter()->implode(' | ');

        return $basis + [
            'fout' => null,
            // De soort als CODE; de import kent de opzoektabel niet uit zijn hoofd.
            'soortcode' => $isTijdschrift ? 'tijdschrift' : 'boek',
            'titel' => mb_substr($titel, 0, 255),
            'auteur' => $auteur !== '' ? mb_substr($auteur, 0, 255) : null,
            'taalcode' => $taalcode,
            'aantal' => $isTijdschrift ? 1 : $aantal,
            'aantal_gecorrigeerd' => $aantalGecorrigeerd,
            'opmerking' => $opmerking,
        ];
    }

    /**
     * Een vrij serienummer voor dit exemplaar. De rekcode is meestal uniek, maar
     * 39 codes komen in de bron dubbel voor; dan krijgt de tweede een achtervoegsel
     * in plaats van dat de import stukloopt op de unieke sleutel.
     */
    private function vrijSerienummer(string $rekcode, int $nummer): string
    {
        $basis = mb_substr(preg_replace('/\s+/', '', $rekcode), 0, 30).'-'.$nummer;

        if (! Exemplaar::where('serienummer', $basis)->exists()) {
            return $basis;
        }

        for ($poging = 2; $poging <= 99; $poging++) {
            $kandidaat = $basis.'.'.$poging;

            if (! Exemplaar::where('serienummer', $kandidaat)->exists()) {
                return $kandidaat;
            }
        }

        throw new \RuntimeException('Kon geen vrij serienummer bepalen voor '.$rekcode.'.');
    }
}
