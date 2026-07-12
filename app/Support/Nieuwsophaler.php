<?php

namespace App\Support;

use App\Enums\Nieuwsbrontype;
use App\Models\Nieuwsbron;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Haalt en normaliseert nieuws van één bron. Ondersteunt Atom, RSS en het
 * scrapen van een server-gerenderde pagina (XPath). Uitgaand verkeer is beperkt
 * tot de whitelist (config sis.nieuws.toegestane_hosts). Testbaar via Http::fake().
 *
 * Geeft per bericht: ['titel', 'samenvatting'(?), 'link', 'gepubliceerd_op'(?Carbon)].
 */
class Nieuwsophaler
{
    /** @return list<array{titel:string,samenvatting:?string,link:string,gepubliceerd_op:?Carbon}> */
    public function haalOp(Nieuwsbron $bron): array
    {
        if (! $bron->type->automatisch()) {
            return []; // handmatige bron: items worden door Beheer toegevoegd
        }
        if (! $this->hostToegestaan($bron)) {
            throw new RuntimeException('Host niet toegestaan (whitelist): '.($bron->host() ?? '—'));
        }

        $body = $this->fetch((string) $bron->url);

        return match ($bron->type) {
            Nieuwsbrontype::Atom, Nieuwsbrontype::Rss => $this->parseFeed($body),
            Nieuwsbrontype::Scrape => $this->parseScrape($body, $bron),
            default => [],
        };
    }

    /** Staat de host van deze bron in de whitelist? */
    public function hostToegestaan(Nieuwsbron $bron): bool
    {
        $host = $bron->host();

        return $host !== null && in_array($host, (array) config('sis.nieuws.toegestane_hosts', []), true);
    }

    private function fetch(string $url): string
    {
        $req = Http::timeout((int) config('sis.nieuws.timeout', 15))
            ->withHeaders(['User-Agent' => 'IUASR-SIS onderwijsnieuws (intern; +https://www.iuasr.nl)']);
        if ($cacert = config('sis.nieuws.cacert')) {
            $req = $req->withOptions(['verify' => $cacert]); // eigen CA-bundel; verificatie blijft aan
        }
        $resp = $req->get($url);
        $resp->throw();

        return $resp->body();
    }

    /** Parse Atom (<feed><entry>) of RSS (<channel><item>). */
    private function parseFeed(string $xml): array
    {
        $vorige = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_use_internal_errors($vorige);
        if ($doc === false) {
            return [];
        }

        $items = [];
        if (isset($doc->entry)) { // Atom
            foreach ($doc->entry as $e) {
                $link = '';
                foreach ($e->link as $l) {
                    $rel = (string) ($l['rel'] ?? 'alternate');
                    if ($rel === 'alternate' || $rel === '') {
                        $link = (string) $l['href'];
                        break;
                    }
                }
                $datum = (string) ($e->published ?: $e->updated);
                $items[] = [
                    'titel' => $this->schoon((string) $e->title, 200),
                    'samenvatting' => $this->schoon((string) ($e->summary ?: $e->content), 300),
                    'link' => trim($link),
                    'gepubliceerd_op' => $this->datum($datum),
                ];
            }
        } elseif (isset($doc->channel->item)) { // RSS
            foreach ($doc->channel->item as $it) {
                $items[] = [
                    'titel' => $this->schoon((string) $it->title, 200),
                    'samenvatting' => $this->schoon((string) $it->description, 300),
                    'link' => trim((string) $it->link),
                    'gepubliceerd_op' => $this->datum((string) $it->pubDate),
                ];
            }
        }

        return array_values(array_filter($items, fn ($i) => $i['titel'] !== '' && $i['link'] !== ''));
    }

    /** Scrape een server-gerenderde pagina met de XPath-selectors van de bron. */
    private function parseScrape(string $html, Nieuwsbron $bron): array
    {
        if (! $bron->item_xpath || ! $bron->titel_xpath || ! $bron->link_xpath) {
            return [];
        }

        $vorige = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_use_internal_errors($vorige);
        $xp = new \DOMXPath($dom);

        $basis = $this->basisUrl((string) $bron->url);
        $items = [];
        foreach ($xp->query($bron->item_xpath) ?: [] as $node) {
            $titel = $this->schoon($this->xpathTekst($xp, $bron->titel_xpath, $node), 200);
            $href = trim($this->xpathTekst($xp, $bron->link_xpath, $node));
            if ($titel === '' || $href === '') {
                continue;
            }
            $datum = $bron->datum_xpath ? $this->xpathTekst($xp, $bron->datum_xpath, $node) : '';
            $items[] = [
                'titel' => $titel,
                'samenvatting' => null,
                'link' => $this->absoluteUrl($href, $basis),
                'gepubliceerd_op' => $this->datum($datum),
            ];
        }

        return $items;
    }

    private function xpathTekst(\DOMXPath $xp, string $expr, \DOMNode $context): string
    {
        $res = $xp->query($expr, $context);

        return $res && $res->length ? trim($res->item(0)->textContent) : '';
    }

    /** Strip HTML/whitespace en kap af op een maximale lengte. */
    private function schoon(string $tekst, int $max = 300): string
    {
        $tekst = trim(preg_replace('/\s+/', ' ', strip_tags($tekst)));

        return mb_strlen($tekst) > $max ? rtrim(mb_substr($tekst, 0, $max - 1)).'…' : $tekst;
    }

    private function datum(string $raw): ?Carbon
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private function basisUrl(string $url): string
    {
        $p = parse_url($url);

        return isset($p['scheme'], $p['host']) ? $p['scheme'].'://'.$p['host'] : '';
    }

    private function absoluteUrl(string $href, string $basis): string
    {
        if ($href === '' || preg_match('#^https?://#i', $href)) {
            return $href;
        }

        return $basis.'/'.ltrim($href, '/');
    }
}
