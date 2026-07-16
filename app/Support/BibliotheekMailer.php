<?php

namespace App\Support;

use App\Enums\BibliotheekMailsoort;
use App\Mail\BibliotheekBericht;
use App\Models\Bibliotheek\Emaillog;
use App\Models\Bibliotheek\Emailsjabloon;
use App\Models\Bibliotheek\Uitlening;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Verstuurt de bibliotheekberichten en legt elke verzending vast in het
 * e-maillogboek (datum, soort, ontvanger, CC, gelukt ja/nee).
 *
 * Twee regels die hier bewust zijn ingebouwd:
 *
 *  1. Een mislukte e-mail BLOKKEERT de handeling niet. Kan de mail niet worden
 *     verstuurd (geen e-mailadres, mailserver plat), dan wordt dat gelogd — met
 *     `gelukt = false` en de foutmelding — en gaat de uitlening of inname
 *     gewoon door. Een boek moet uitgeleend kunnen worden als de mailserver hapert.
 *  2. Het logboek is de bron voor "hoeveel mails zijn er verstuurd", die op de
 *     detailpagina van de lener zichtbaar is.
 */
class BibliotheekMailer
{
    /**
     * Verstuur een bericht over een uitlening. Geeft terug of het gelukt is.
     * Ontbreekt het e-mailadres van de lener, dan wordt dat als mislukte poging
     * gelogd — dan is zichtbaar dat er iets níet is verstuurd.
     */
    public static function verstuur(Uitlening $uitlening, BibliotheekMailsoort $soort): bool
    {
        $sjabloon = Emailsjabloon::where('soort', $soort)->where('actief', true)->first();

        if ($sjabloon === null) {
            self::log($uitlening, $soort, $uitlening->lenerEmail() ?? '—', false, 'Geen actief sjabloon voor '.$soort->value);

            return false;
        }

        $ontvanger = $uitlening->lenerEmail();

        if ($ontvanger === null || $ontvanger === '') {
            self::log($uitlening, $soort, '—', false, 'De lener heeft geen e-mailadres in het dossier.');

            return false;
        }

        $waarden = self::variabelen($uitlening);
        $onderwerp = $sjabloon->render($sjabloon->onderwerp, $waarden);
        $tekst = $sjabloon->render($sjabloon->inhoud, $waarden);

        try {
            Mail::to($ontvanger)->send(new BibliotheekBericht($onderwerp, $tekst));
            self::log($uitlening, $soort, $ontvanger, true, null);

            return true;
        } catch (\Throwable $e) {
            // Loggen, niet laten klappen: de uitlening zelf is al vastgelegd.
            Log::warning('Bibliotheek-e-mail mislukt', ['uitlening' => $uitlening->id, 'fout' => $e->getMessage()]);
            self::log($uitlening, $soort, $ontvanger, false, $e->getMessage());

            return false;
        }
    }

    /**
     * De variabelen die in de sjablonen mogen voorkomen.
     *
     * @return array<string,string>
     */
    private static function variabelen(Uitlening $uitlening): array
    {
        $boete = (float) config('sis.bibliotheek.boete_per_boek', 10);

        return [
            'Naam' => $uitlening->lenerNaam(),
            'Titel' => $uitlening->exemplaar?->publicatie?->volledigeTitel() ?? '',
            'Uitleendatum' => $uitlening->uitgeleend_op->format('d-m-Y'),
            'Retourdatum' => ($uitlening->retour_op ?? $uitlening->verwachte_retour_op)->format('d-m-Y'),
            'AantalDagenTeLaat' => (string) $uitlening->dagenTeLaat(),
            // Boete per boek — alleen ter vermelding in de te-laat-mail; het systeem
            // int of administreert niets (config sis.bibliotheek.boete_per_boek).
            'Boete' => '€ '.number_format($boete, 2, ',', '.'),
        ];
    }

    private static function log(Uitlening $uitlening, BibliotheekMailsoort $soort, string $ontvanger, bool $gelukt, ?string $fout): void
    {
        Emaillog::create([
            'uitlening_id' => $uitlening->id,
            'soort' => $soort,
            'ontvanger' => $ontvanger,
            'cc' => config('sis.mail.cc.bibliotheek'),
            'gelukt' => $gelukt,
            'foutmelding' => $fout,
            'verzonden_op' => Carbon::now(),
        ]);
    }
}
