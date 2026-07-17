<?php

namespace App\Support;

use App\Models\Quote;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Kiest welke quote er NU in de zijbalk staat.
 *
 * De keuze is AFGELEID uit de klok, niet opgeslagen en niet per bezoeker:
 * `slot = tijdstip ÷ interval`, en daarvan de rest bij deling door het aantal
 * quotes. Dat lost drie dingen tegelijk op:
 *
 *  1. **Het systeem is server-gerenderd.** Een gewone JavaScript-carrousel begint
 *     bij elke paginawissel weer bij nummer 1; wie normaal door het systeem klikt
 *     zou daardoor vrijwel nooit verder komen dan de eerste naam. Met een slot uit
 *     de klok loopt de reeks door, ongeacht hoe vaak iemand navigeert.
 *  2. **Iedereen ziet hetzelfde.** Alle collega's zien in hetzelfde tijdvak
 *     dezelfde Naam — dat is precies de bedoeling van een gedeelde bemoediging.
 *  3. **Geen toestand.** Geen sessie, geen teller, geen achtergrondtaak; een
 *     herstart of tweede webserver verandert niets aan de uitkomst.
 *
 * De lijst zelf wordt gecachet omdat de zijbalk op ELKE pagina rendert; elke
 * mutatie leegt die cache (zie Quote::booted).
 */
class Quoteroulatie
{
    public static function intervalSeconden(): int
    {
        // Ondergrens van een minuut: sneller wisselen leest niet meer, het flikkert.
        return max(60, (int) config('sis.quote.interval_minuten', 5) * 60);
    }

    /** Het hoeveelste tijdvak sinds 1970 we nu in zitten. */
    public static function slot(?int $tijdstip = null): int
    {
        return intdiv($tijdstip ?? now()->getTimestamp(), self::intervalSeconden());
    }

    /** @return Collection<int, Quote> */
    public static function actieve(): Collection
    {
        return Cache::remember(
            Quote::CACHE_SLEUTEL,
            now()->addHour(),
            fn () => Quote::query()->actief()->geordend()->get()
        );
    }

    public static function voorSlot(int $slot): ?Quote
    {
        $quotes = self::actieve();
        if ($quotes->isEmpty()) {
            return null;
        }

        $aantal = $quotes->count();

        // Dubbele modulo: bestand tegen een negatief slot (een klok vóór 1970 is
        // onzin, maar een deling die stilletjes een verkeerde index geeft is erger).
        return $quotes[(($slot % $aantal) + $aantal) % $aantal];
    }

    public static function huidige(): ?Quote
    {
        return self::voorSlot(self::slot());
    }

    /**
     * Hoeveel seconden tot de volgende wissel. De zijbalk geeft dit aan de
     * browser mee, zodat die precies op de grens ververst in plaats van blind te
     * pollen — één verzoek per tijdvak in plaats van elke halve minuut.
     */
    public static function secondenTotVolgende(?int $tijdstip = null): int
    {
        $interval = self::intervalSeconden();

        return $interval - (($tijdstip ?? now()->getTimestamp()) % $interval);
    }
}
