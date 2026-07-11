<?php

namespace App\Support;

use App\Models\Medewerker;

/**
 * Bouwt een iCal-agenda (VCALENDAR) uit het eigen dossier van een medewerker: de
 * geplande HR-gesprekken en het goedgekeurde verlof. Bewust een <b>zelfstandig
 * .ics-bestand</b> (download) — geen live koppeling met Outlook/Teams, zodat de
 * gegevens het intranet niet verlaten (AVG/intranet-only). De gebruiker importeert
 * of abonneert zelf in de eigen agenda.
 */
class Icsagenda
{
    public static function voor(Medewerker $medewerker, string $stempel): string
    {
        $regels = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//IUASR//SIS HR//NL',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:Mijn HR-agenda IUASR',
        ];

        foreach ($medewerker->gesprekken()->where('status', 'gepland')->get() as $gesprek) {
            $regels = array_merge($regels, self::gebeurtenis(
                'gesprek-'.$gesprek->id.'@iuasr-sis',
                $stempel,
                $gesprek->datum->format('Ymd'),
                $gesprek->datum->copy()->addDay()->format('Ymd'),
                $gesprek->type->label().' — HR-gesprek',
                'Gepland '.mb_strtolower($gesprek->type->label()).' bij IUASR.'
            ));
        }

        foreach ($medewerker->verlofaanvragen()->where('status', 'goedgekeurd')->get() as $verlof) {
            $regels = array_merge($regels, self::gebeurtenis(
                'verlof-'.$verlof->id.'@iuasr-sis',
                $stempel,
                $verlof->van->format('Ymd'),
                $verlof->tot->copy()->addDay()->format('Ymd'),
                'Verlof — '.$verlof->verloftype->label(),
                $verlof->uren.' uur '.mb_strtolower($verlof->verloftype->label()).' (goedgekeurd).'
            ));
        }

        $regels[] = 'END:VCALENDAR';

        return implode("\r\n", $regels)."\r\n";
    }

    /** @return array<int, string> de VEVENT-regels (all-day; DTEND is exclusief). */
    private static function gebeurtenis(string $uid, string $stempel, string $start, string $eind, string $titel, string $omschrijving): array
    {
        return [
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.$stempel,
            'DTSTART;VALUE=DATE:'.$start,
            'DTEND;VALUE=DATE:'.$eind,
            'SUMMARY:'.self::escape($titel),
            'DESCRIPTION:'.self::escape($omschrijving),
            'END:VEVENT',
        ];
    }

    /** Escapet de tekens die iCal (RFC 5545) in tekstwaarden vereist. */
    private static function escape(string $tekst): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], $tekst);
    }
}
