<?php

namespace App\Support;

use App\Models\Bibliotheek\Publicatie;
use App\Models\Bibliotheek\Verrijking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Verrijkt de catalogus met een externe bibliografische bron (Open Library):
 * ISBN, uitgavejaar en een gecorrigeerde schrijfwijze van de titel.
 *
 * DE KERNREGEL (opdrachtgever 2026-07-13): "skip als je onzeker bent".
 * Er wordt UITSLUITEND iets gewijzigd bij een ZEKERE match. Zeker betekent hier:
 *
 *   - de gevonden titel lijkt voor ten minste 92% op de onze (na normalisatie), én
 *   - de auteur komt overeen (achternaam), óf wij hebben zelf geen auteur.
 *
 * Alles daaronder wordt vastgelegd als 'onzeker' en NIET toegepast. Zo kan een
 * verkeerde treffer nooit een goede titel overschrijven. De oude waarden worden
 * bewaard, dus elke correctie is terug te draaien.
 *
 * ALLEEN NEDERLANDS, ENGELS EN TURKS (keuze opdrachtgever). Arabische titels
 * worden overgeslagen: die staan in de bron in transliteratie én in Arabisch
 * schrift door elkaar, en de externe bronnen dekken ze slecht — daar zou
 * "corrigeren" neerkomen op gokken.
 *
 * AVG/beveiliging: uitgaand verkeer uitsluitend naar de whitelist-host uit
 * config `sis.bibliotheek.verrijking.host`; SSL-verificatie blijft áltijd aan.
 */
class BibliotheekVerrijker
{
    /** Vanaf deze gelijkenis (0-1) noemen we een titel-match zeker. */
    private const DREMPEL_ZEKER = 0.92;

    /** De talen waarvoor verrijken zinvol is (opdrachtgever). */
    public const TALEN = ['nl', 'en', 'tr'];

    /**
     * Verrijkt één publicatie. Geeft de vastgelegde uitkomst terug.
     * Een publicatie die al is bevraagd wordt overgeslagen (idempotent).
     */
    public function verrijk(Publicatie $publicatie): ?Verrijking
    {
        $bron = (string) config('sis.bibliotheek.verrijking.bron', 'openlibrary');

        if (Verrijking::where('publicatie_id', $publicatie->id)->where('bron', $bron)->exists()) {
            return null; // al eerder bevraagd
        }

        $eigenAuteur = $publicatie->auteurs->first()?->naam;

        try {
            $kandidaten = $this->zoek($publicatie->titel);
        } catch (\Throwable $e) {
            return $this->leg($publicatie, Verrijking::FOUT, toelichting: mb_substr($e->getMessage(), 0, 250));
        }

        if ($kandidaten === []) {
            return $this->leg($publicatie, Verrijking::GEEN_TREFFER);
        }

        // De beste kandidaat op titelgelijkenis.
        $beste = null;
        $besteScore = 0.0;

        foreach ($kandidaten as $kandidaat) {
            $score = $this->gelijkenis($publicatie->titel, $kandidaat['titel']);

            if ($score > $besteScore) {
                $besteScore = $score;
                $beste = $kandidaat;
            }
        }

        $auteurKlopt = $eigenAuteur === null || $eigenAuteur === ''
            || $this->auteurKomtOvereen($eigenAuteur, $beste['auteur'] ?? '');

        $zeker = $besteScore >= self::DREMPEL_ZEKER && $auteurKlopt;

        if (! $zeker) {
            return $this->leg($publicatie, Verrijking::ONZEKER,
                kandidaat: $beste,
                score: $besteScore,
                toelichting: $besteScore < self::DREMPEL_ZEKER
                    ? 'Titel lijkt te weinig op de gevonden titel.'
                    : 'De auteur komt niet overeen.',
            );
        }

        // ZEKER: toepassen. Alleen velden die wij nog niet hebben, plus de
        // schrijfwijze van de titel als die (licht) afwijkt.
        $oudeTitel = $publicatie->titel;
        $wijzigingen = [];

        if (($publicatie->isbn === null || $publicatie->isbn === '') && ! empty($beste['isbn'])) {
            $publicatie->isbn = $beste['isbn'];
            $wijzigingen['isbn'] = ['oud' => null, 'nieuw' => $beste['isbn']];
        }

        if ($publicatie->uitgavejaar === null && ! empty($beste['jaar'])) {
            $publicatie->uitgavejaar = (int) $beste['jaar'];
            $wijzigingen['uitgavejaar'] = ['oud' => null, 'nieuw' => (int) $beste['jaar']];
        }

        // Titel: alleen de schrijfwijze rechtzetten, nooit een andere titel opdringen.
        if ($beste['titel'] !== $oudeTitel && $besteScore >= self::DREMPEL_ZEKER) {
            $publicatie->titel = mb_substr($beste['titel'], 0, 255);
            $wijzigingen['titel'] = ['oud' => $oudeTitel, 'nieuw' => $publicatie->titel];
        }

        if ($wijzigingen !== []) {
            $publicatie->save();

            AuditLogger::log(AuditLogger::WIJZIGING, $publicatie, veld: 'bibliotheek_verrijking', context: $wijzigingen + [
                'bron' => $bron,
                'score' => round($besteScore, 3),
            ]);
        }

        return $this->leg($publicatie, Verrijking::TOEGEPAST,
            kandidaat: $beste,
            score: $besteScore,
            oudeTitel: $oudeTitel,
            oudeAuteur: $eigenAuteur,
            toelichting: $wijzigingen === [] ? 'Zekere match, maar wij hadden alles al.' : implode(', ', array_keys($wijzigingen)).' bijgewerkt.',
        );
    }

    /**
     * Zoekt kandidaten bij de externe bron. Alleen op TITEL: een verkeerd
     * gespelde auteursnaam in onze bron zou een strikte titel+auteur-zoekopdracht
     * kansloos maken. De auteur wordt daarna gebruikt om de match te CONTROLEREN.
     *
     * @return array<int,array{titel:string,auteur:?string,isbn:?string,jaar:?int}>
     */
    private function zoek(string $titel): array
    {
        $host = (string) config('sis.bibliotheek.verrijking.host', 'openlibrary.org');
        $verzoek = Http::timeout((int) config('sis.bibliotheek.verrijking.timeout', 20))
            ->withHeaders(['User-Agent' => 'IUASR-Bibliotheek/1.0 ('.config('sis.mail.cc.bibliotheek').')']);

        // Eigen CA-bundel als de server er geen in php.ini heeft; verificatie blijft AAN.
        if ($cacert = config('sis.bibliotheek.verrijking.cacert') ?: config('sis.nieuws.cacert')) {
            $verzoek = $verzoek->withOptions(['verify' => $cacert]);
        }

        $antwoord = $verzoek->get('https://'.$host.'/search.json', [
            'q' => $this->normaliseer($titel),
            'limit' => 5,
            'fields' => 'title,author_name,first_publish_year,isbn',
        ]);

        if (! $antwoord->successful()) {
            throw new \RuntimeException('HTTP '.$antwoord->status().' van '.$host);
        }

        return collect($antwoord->json('docs') ?? [])
            ->map(fn (array $doc) => [
                'titel' => (string) ($doc['title'] ?? ''),
                'auteur' => $doc['author_name'][0] ?? null,
                'isbn' => $this->bruikbaarIsbn($doc['isbn'] ?? []),
                'jaar' => isset($doc['first_publish_year']) ? (int) $doc['first_publish_year'] : null,
            ])
            ->filter(fn (array $d) => $d['titel'] !== '')
            ->values()
            ->all();
    }

    /** Het eerste ISBN-13, anders het eerste ISBN-10. */
    private function bruikbaarIsbn(array $isbns): ?string
    {
        $dertien = collect($isbns)->first(fn ($i) => strlen((string) $i) === 13);

        return $dertien ?? (collect($isbns)->first(fn ($i) => strlen((string) $i) === 10) ?: null);
    }

    /**
     * Gelijkenis tussen twee titels (0-1), na normalisatie. Gebruikt de
     * Levenshtein-afstand ten opzichte van de lengte: 1.0 = identiek.
     */
    private function gelijkenis(string $a, string $b): float
    {
        $a = $this->normaliseer($a);
        $b = $this->normaliseer($b);

        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 1.0;
        }

        // levenshtein() werkt op bytes; bij lange titels afkappen (limiet 255).
        $afstand = levenshtein(mb_substr($a, 0, 200), mb_substr($b, 0, 200));
        $lengte = max(mb_strlen($a), mb_strlen($b));

        return $lengte > 0 ? max(0.0, 1 - ($afstand / $lengte)) : 0.0;
    }

    /**
     * Komt de auteur overeen? Vergelijkt op ACHTERNAAM, want onze bron schrijft
     * namen op allerlei manieren ("V.S. Naipol", "Naipaul, V. S.").
     */
    private function auteurKomtOvereen(string $onze, string $gevonden): bool
    {
        if ($gevonden === '') {
            return false;
        }

        $delen = fn (string $naam) => collect(preg_split('/[\s,.]+/', $this->normaliseer($naam)))
            ->filter(fn ($deel) => mb_strlen($deel) >= 3);

        $onzeDelen = $delen($onze);
        $gevondenDelen = $delen($gevonden);

        if ($onzeDelen->isEmpty() || $gevondenDelen->isEmpty()) {
            return false;
        }

        // Eén overeenkomend naamdeel van 3+ tekens is genoeg (bijv. de achternaam).
        foreach ($onzeDelen as $deel) {
            foreach ($gevondenDelen as $ander) {
                if ($deel === $ander || $this->gelijkenis($deel, $ander) >= 0.9) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Kleine letters, diakrieten weg, dubbele spaties weg, leestekens weg. */
    private function normaliseer(string $tekst): string
    {
        $tekst = mb_strtolower(trim($tekst));
        $tekst = strtr($tekst, [
            'ı' => 'i', 'İ' => 'i', 'ş' => 's', 'ğ' => 'g', 'ç' => 'c', 'ö' => 'o', 'ü' => 'u',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ï' => 'i', 'á' => 'a', 'à' => 'a', 'ä' => 'a',
            '’' => "'", '‘' => "'",
        ]);
        $tekst = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $tekst);

        return trim(preg_replace('/\s+/', ' ', $tekst));
    }

    /** Legt de uitkomst vast (ook 'geen treffer' en 'onzeker' — dat maakt het herhaalbaar). */
    private function leg(
        Publicatie $publicatie,
        string $status,
        ?array $kandidaat = null,
        ?float $score = null,
        ?string $oudeTitel = null,
        ?string $oudeAuteur = null,
        ?string $toelichting = null,
    ): Verrijking {
        return Verrijking::create([
            'publicatie_id' => $publicatie->id,
            'bron' => (string) config('sis.bibliotheek.verrijking.bron', 'openlibrary'),
            'status' => $status,
            'gevonden_titel' => $kandidaat['titel'] ?? null,
            'gevonden_auteur' => $kandidaat['auteur'] ?? null,
            'isbn' => $kandidaat['isbn'] ?? null,
            'jaar' => $kandidaat['jaar'] ?? null,
            'score' => $score,
            'oude_titel' => $oudeTitel,
            'oude_auteur' => $oudeAuteur,
            'toelichting' => $toelichting,
            'opgehaald_op' => Carbon::now(),
        ]);
    }
}
