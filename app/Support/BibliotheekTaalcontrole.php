<?php

namespace App\Support;

use App\Models\Bibliotheek\Taal;
use Illuminate\Support\Facades\DB;

/**
 * Corpus-gebaseerde spelling-/typefoutcontrole voor boektitels (Turks, Engels,
 * Nederlands). Zonder extern woordenboek: per taal worden de woordfrequenties over
 * alle titels geteld. Een ZELDZAAM woord dat op een bewerkingsafstand van 1 (of 2
 * voor lange woorden) ligt van een VEEL vaker voorkomend, bijna identiek woord is
 * een waarschijnlijke typefout — met dat vaker voorkomende woord als suggestie.
 *
 * Zo blijven eigennamen en islamitische/Arabische termen die vaak terugkomen buiten
 * schot (die zijn immers frequent), en worden alleen echte uitschieters gemeld.
 * De controle CORRIGEERT niets automatisch; ze levert een reviewlijst op.
 */
class BibliotheekTaalcontrole
{
    public function __construct(
        /** Een verdacht woord komt in hoogstens zoveel titels voor. */
        private int $maxVerdachtFreq = 2,
        /** Een suggestie moet in ten minste zoveel titels voorkomen. */
        private int $minSuggestieFreq = 5,
        /** De suggestie moet minstens deze factor vaker voorkomen dan het verdachte woord. */
        private int $factor = 4,
        /** Woorden korter dan dit worden overgeslagen (te veel toevallige matches). */
        private int $minLengte = 4,
        /** Paren die alleen in een achtervoegsel verschillen (verbuiging) overslaan. */
        private bool $negeerVerbuiging = true,
    ) {}

    /**
     * Analyseer één taal op vermoedelijke typefouten in de titels.
     *
     * @return list<array{id:int,titel:string,verdacht:string,suggestie:string,afstand:int,freq_verdacht:int,freq_suggestie:int}>
     */
    public function voorTaal(int $taalId): array
    {
        $titels = DB::table('bibliotheek_publicaties as p')
            ->join('bibliotheek_publicatie_taal as pt', 'pt.publicatie_id', '=', 'p.id')
            ->where('pt.taal_id', $taalId)
            ->whereNotNull('p.titel')
            ->where('p.titel', '<>', '')
            ->select('p.id', 'p.titel')
            ->get();

        // Documentfrequentie: in hoeveel titels komt een woord voor (uniek per titel).
        $freq = [];
        $perTitel = [];
        foreach ($titels as $rij) {
            $woorden = $this->tokeniseer($rij->titel);
            $perTitel[$rij->id] = ['titel' => $rij->titel, 'woorden' => $woorden];
            foreach (array_unique($woorden) as $w) {
                $freq[$w] = ($freq[$w] ?? 0) + 1;
            }
        }

        // Veelvoorkomende woorden, gebucket op lengte voor snelle kandidaatzoektocht.
        $byLen = [];
        foreach ($freq as $w => $n) {
            if ($n >= $this->minSuggestieFreq) {
                $byLen[$this->lengte($w)][] = $w;
            }
        }

        $flags = [];
        $gezien = [];
        foreach ($perTitel as $id => $data) {
            foreach (array_unique($data['woorden']) as $w) {
                $len = $this->lengte($w);
                if ($len < $this->minLengte) {
                    continue;
                }
                $fv = $freq[$w] ?? 0;
                if ($fv > $this->maxVerdachtFreq || $fv >= $this->minSuggestieFreq) {
                    continue; // niet zeldzaam genoeg
                }
                $suggestie = $this->besteSuggestie($w, $len, $byLen, $freq);
                if ($suggestie === null) {
                    continue;
                }
                [$sugg, $suggFreq, $afstand] = $suggestie;
                if ($suggFreq < $this->factor * max(1, $fv)) {
                    continue;
                }
                // Verbuiging: verschilt het paar alleen in een achtervoegsel
                // (het ene woord is het andere + extra letters aan het eind), dan is
                // het meestal een geldige vervoeging, geen typefout.
                if ($this->negeerVerbuiging) {
                    $korter = $len <= $this->lengte($sugg) ? $w : $sugg;
                    $langer = $korter === $w ? $sugg : $w;
                    if (str_starts_with($langer, $korter)) {
                        continue;
                    }
                }
                $sleutel = $id.'|'.$w;
                if (isset($gezien[$sleutel])) {
                    continue;
                }
                $gezien[$sleutel] = true;

                $flags[] = [
                    'id' => (int) $id,
                    'titel' => $data['titel'],
                    'verdacht' => $w,
                    'suggestie' => $sugg,
                    'afstand' => $afstand,
                    'freq_verdacht' => $fv,
                    'freq_suggestie' => $suggFreq,
                ];
            }
        }

        // Meest waarschijnlijke bovenaan: kleine afstand, groot frequentieverschil.
        usort($flags, fn ($a, $b) => [$a['afstand'], -$a['freq_suggestie']] <=> [$b['afstand'], -$b['freq_suggestie']]);

        return $flags;
    }

    /** Taal-id opzoeken op ISO-code (nl/en/tr). */
    public static function taalId(string $code): ?int
    {
        return Taal::where('code', $code)->value('id');
    }

    /**
     * Is dit een 'interne' typefout die veilig genoeg is om automatisch toe te
     * passen? De eerste én laatste letter kloppen (de fout zit binnenin het woord,
     * geen verbogen begin/einde) en het woord is minstens 6 tekens. Zo blijven
     * verbuigingen (ander achtervoegsel) en begin-letter-verwisselingen buiten schot.
     */
    public static function isInterneTypfout(string $verdacht, string $suggestie): bool
    {
        $a = preg_split('//u', $verdacht, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $b = preg_split('//u', $suggestie, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($a) < 6 || count($b) < 3) {
            return false;
        }

        return $a[0] === $b[0] && end($a) === end($b);
    }

    /**
     * Vervang (het eerste voorkomen van) een heel woord in een titel door de
     * suggestie, met behoud van de hoofdletter-opmaak. Geeft [nieuweTitel, aantal].
     *
     * @return array{0:string,1:int}
     */
    public static function vervangWoord(string $titel, string $woord, string $suggestie): array
    {
        $aantal = 0;
        $nieuw = preg_replace_callback(
            '/(?<!\p{L})'.preg_quote($woord, '/').'(?!\p{L})/iu',
            fn ($m) => self::pasHoofdletterAan($m[0], $suggestie),
            $titel,
            1,
            $aantal
        );

        return [$nieuw ?? $titel, $aantal];
    }

    /** Neem de hoofdletter-opmaak van het gevonden woord over op de suggestie. */
    private static function pasHoofdletterAan(string $gevonden, string $suggestie): string
    {
        if (mb_strtoupper($gevonden) === $gevonden) {
            return mb_strtoupper($suggestie);
        }
        $eerste = mb_substr($gevonden, 0, 1);
        if (mb_strtoupper($eerste) === $eerste) {
            return mb_strtoupper(mb_substr($suggestie, 0, 1)).mb_substr($suggestie, 1);
        }

        return $suggestie;
    }

    /** @return list<string> */
    private function tokeniseer(string $titel): array
    {
        $delen = preg_split('/[^\p{L}]+/u', mb_strtolower($titel), -1, PREG_SPLIT_NO_EMPTY);

        return $delen ?: [];
    }

    private function lengte(string $w): int
    {
        return mb_strlen($w);
    }

    /**
     * De beste suggestie voor een verdacht woord: het frequentste woord binnen de
     * toegestane bewerkingsafstand (1, of 2 voor woorden vanaf 8 tekens).
     *
     * @param  array<int, list<string>>  $byLen
     * @param  array<string, int>  $freq
     * @return array{0:string,1:int,2:int}|null  [suggestie, frequentie, afstand]
     */
    private function besteSuggestie(string $w, int $len, array $byLen, array $freq): ?array
    {
        $max = $len >= 8 ? 2 : 1;
        $beste = null;
        $besteFreq = 0;
        $besteAfstand = $max + 1;

        for ($l = $len - $max; $l <= $len + $max; $l++) {
            foreach ($byLen[$l] ?? [] as $kandidaat) {
                if ($kandidaat === $w) {
                    continue;
                }
                $d = $this->afstand($w, $kandidaat, $max);
                if ($d > $max) {
                    continue;
                }
                $f = $freq[$kandidaat];
                if ($d < $besteAfstand || ($d === $besteAfstand && $f > $besteFreq)) {
                    $beste = $kandidaat;
                    $besteFreq = $f;
                    $besteAfstand = $d;
                }
            }
        }

        return $beste === null ? null : [$beste, $besteFreq, $besteAfstand];
    }

    /**
     * Multibyte-veilige Levenshtein-afstand met vroege afbreking zodra de afstand
     * groter wordt dan $max. Nodig omdat de ingebouwde levenshtein() op bytes werkt
     * en Turkse/Nederlandse diakrieten (ç, ğ, ı, ö, ş, ü, ë) meerdere bytes beslaan.
     */
    private function afstand(string $a, string $b, int $max): int
    {
        $sa = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $sb = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $la = count($sa);
        $lb = count($sb);
        if (abs($la - $lb) > $max) {
            return $max + 1;
        }

        $vorige = range(0, $lb);
        for ($i = 1; $i <= $la; $i++) {
            $huidig = [$i];
            $rijMin = $i;
            for ($j = 1; $j <= $lb; $j++) {
                $kost = $sa[$i - 1] === $sb[$j - 1] ? 0 : 1;
                $huidig[$j] = min($vorige[$j] + 1, $huidig[$j - 1] + 1, $vorige[$j - 1] + $kost);
                if ($huidig[$j] < $rijMin) {
                    $rijMin = $huidig[$j];
                }
            }
            if ($rijMin > $max) {
                return $max + 1;
            }
            $vorige = $huidig;
        }

        return $vorige[$lb];
    }
}
