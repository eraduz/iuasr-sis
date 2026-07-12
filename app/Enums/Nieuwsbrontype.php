<?php

namespace App\Enums;

/**
 * Hoe het nieuws van een bron wordt opgehaald:
 *  - atom/rss : een machineleesbare feed (voorkeur; betrouwbaar en licht).
 *  - scrape   : een server-gerenderde nieuwspagina, uitgelezen met XPath-selectors.
 *  - handmatig: geen feed/API mogelijk (bv. een JavaScript-app) → Beheer voegt de
 *               belangrijke items zelf toe.
 */
enum Nieuwsbrontype: string
{
    case Atom = 'atom';
    case Rss = 'rss';
    case Scrape = 'scrape';
    case Handmatig = 'handmatig';

    public function label(): string
    {
        return match ($this) {
            self::Atom => 'Atom-feed',
            self::Rss => 'RSS-feed',
            self::Scrape => 'Nieuwspagina (scrape)',
            self::Handmatig => 'Handmatig',
        };
    }

    /** Wordt deze bron automatisch opgehaald (dus niet handmatig)? */
    public function automatisch(): bool
    {
        return $this !== self::Handmatig;
    }

    /** @return array<int, string> */
    public static function waarden(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
