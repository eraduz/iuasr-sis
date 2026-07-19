<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use IntlDateFormatter;

/**
 * De islamitische (hidjri) datum van vandaag, voor onder de zijbalk-quote.
 *
 * De omrekening laten we aan ICU (de intl-extensie) over — zelf maanden tellen
 * loopt gegarandeerd een keer mis. Welke variant geldt, staat in
 * `config/sis.php` ('hidjri.variant'); de varianten kunnen een of twee dagen
 * uiteenlopen en de plaatselijke maansobservatie kan daar weer van afwijken,
 * vandaar de instelbare verschuiving in dagen.
 *
 * Zonder de intl-extensie geeft alles null terug en verdwijnt de regel stil uit
 * de zijbalk: een kalenderregel is nooit reden om een scherm te laten breken.
 */
class Hidjrikalender
{
    /** De maandnamen in Arabisch schrift, in kalendervolgorde (1..12). */
    private const MAANDEN_AR = [
        'المحرم', 'صفر', 'ربيع الأول', 'ربيع الآخر', 'جمادى الأولى', 'جمادى الآخرة',
        'رجب', 'شعبان', 'رمضان', 'شوال', 'ذو القعدة', 'ذو الحجة',
    ];

    /** Transliteratie van de maandnamen, in dezelfde volgorde. */
    private const MAANDEN_NL = [
        'Moeharram', 'Safar', 'Rabi al-awwal', 'Rabi al-thani', 'Djoemada al-oela', 'Djoemada al-akhira',
        'Radjab', 'Shaban', 'Ramadan', 'Shawwal', 'Dhoe al-qada', 'Dhoe al-hidjdja',
    ];

    /**
     * De hidjri-datum van vandaag, of null als die niet te bepalen is.
     *
     * @return array{dag:int, maand:int, jaar:int, maand_nl:string, maand_ar:string, tekst:string, arabisch:string}|null
     */
    public static function vandaag(?CarbonImmutable $moment = null): ?array
    {
        if (! extension_loaded('intl')) {
            return null;
        }

        $moment ??= CarbonImmutable::now(config('app.timezone'));
        $moment = $moment->addDays((int) config('sis.hidjri.dagen_verschuiving', 0));

        $variant = self::variant();

        try {
            // 'yyyy|MM|dd' als losse getallen: de naamgeving doen we zelf, zodat
            // de transliteratie niet met de ICU-locale meebeweegt.
            $formatter = new IntlDateFormatter(
                'en_US@calendar='.$variant,
                IntlDateFormatter::NONE,
                IntlDateFormatter::NONE,
                config('app.timezone'),
                IntlDateFormatter::TRADITIONAL,
                'y|M|d',
            );
            $ruw = $formatter->format($moment->getTimestamp());
        } catch (\Throwable) {
            return null;
        }

        if (! is_string($ruw) || substr_count($ruw, '|') !== 2) {
            return null;
        }

        [$jaar, $maand, $dag] = array_map('intval', explode('|', $ruw));
        if ($maand < 1 || $maand > 12) {
            return null;
        }

        $maandNl = self::MAANDEN_NL[$maand - 1];
        $maandAr = self::MAANDEN_AR[$maand - 1];

        return [
            'dag' => $dag,
            'maand' => $maand,
            'jaar' => $jaar,
            'maand_nl' => $maandNl,
            'maand_ar' => $maandAr,
            'tekst' => $dag.' '.$maandNl.' '.$jaar.' AH',
            'arabisch' => self::arabischeCijfers($dag).' '.$maandAr.' '.self::arabischeCijfers($jaar).' هـ',
        ];
    }

    /**
     * De ingestelde kalendervariant, gecontroleerd tegen wat ICU kent. Dit moet
     * wél: bij een onbekende sleutel valt ICU stilzwijgend terug op de
     * gregoriaanse kalender, en dan zou een typefout in `.env` "19 Safar 2026"
     * opleveren — een datum die nergens op slaat, zonder enige waarschuwing.
     */
    private static function variant(): string
    {
        $toegestaan = ['islamic-umalqura', 'islamic-civil', 'islamic', 'islamic-tbla', 'islamic-rgsa'];
        $variant = (string) config('sis.hidjri.variant', 'islamic-umalqura');

        if (! in_array($variant, $toegestaan, true)) {
            \Illuminate\Support\Facades\Log::warning('Onbekende hidjri-variant in config/sis.php; teruggevallen op islamic-umalqura.', [
                'ingesteld' => $variant,
                'toegestaan' => $toegestaan,
            ]);

            return 'islamic-umalqura';
        }

        return $variant;
    }

    /** Westerse cijfers omzetten naar Arabisch-Indische cijfers (٠١٢٣…). */
    private static function arabischeCijfers(int $getal): string
    {
        return str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            (string) $getal,
        );
    }
}
