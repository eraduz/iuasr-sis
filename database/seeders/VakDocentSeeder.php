<?php

namespace Database\Seeders;

use App\Models\Docent;
use App\Models\Opleiding;
use App\Models\Vak;
use Illuminate\Database\Seeder;

/**
 * Koppelt de docenten aan de vakken op basis van `database/data/vakdocenten.csv`,
 * ontleend aan de studiegidsen (ISLTH BA 2025-2026 en MGV MIGV 2024-2025; per
 * module de regel "Docent"). Zet `vakken.docent_id`, zodat de rol Docent het
 * eigen vak ziet ("Mijn vakken") en cijfers/aanwezigheid kan invoeren.
 *
 * Matcht op ACHTERNAAM (genormaliseerd: zonder diakritische tekens, hoofdletters,
 * spaties en koppeltekens), zodat kleine spellingsverschillen tussen gids en
 * docententabel (bv. "Biçer-Uslu" ↔ "Bicer-Uslu") toch koppelen. Docenten zelf
 * staan in {@see DocentSeeder}; ontbreekt een achternaam daar, dan wordt de vak-
 * koppeling overgeslagen en gemeld (geen docent verzinnen).
 *
 * Idempotent. Stage-/scriptiecoördinatie-vakken staan bewust NIET in de bron
 * (geen individuele docent). PMGV/PABO: geen bron beschikbaar.
 */
class VakDocentSeeder extends Seeder
{
    public const BESTAND = 'database/data/vakdocenten.csv';

    private const TRANSLIT = [
        'ç' => 'c', 'ı' => 'i', 'İ' => 'i', 'ğ' => 'g', 'ş' => 's', 'ü' => 'u', 'ö' => 'o',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e', 'ï' => 'i', 'î' => 'i',
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ó' => 'o', 'ô' => 'o', 'û' => 'u', 'ñ' => 'n',
    ];

    public function run(): void
    {
        $pad = base_path(self::BESTAND);
        if (! is_readable($pad)) {
            $this->command?->warn('vakdocenten.csv niet gevonden; docentkoppeling overgeslagen.');

            return;
        }

        $opleidingen = Opleiding::pluck('id', 'code');
        // Docenten geïndexeerd op genormaliseerde achternaam.
        $docenten = Docent::all()->keyBy(fn (Docent $d) => $this->sleutel($d->achternaam));

        $gekoppeld = 0;
        $overgeslagen = [];
        foreach ($this->regels($pad) as $rij) {
            $opleidingId = $opleidingen[$rij['opleiding']] ?? null;
            if ($opleidingId === null) {
                $overgeslagen[] = $rij['opleiding'].'/'.$rij['vakcode'].' (onbekende opleiding)';

                continue;
            }

            $docent = $docenten[$this->sleutel($rij['achternaam'])] ?? null;
            if ($docent === null) {
                $overgeslagen[] = $rij['vakcode'].' (docent "'.$rij['achternaam'].'" niet gevonden)';

                continue;
            }

            $vak = Vak::where('opleiding_id', $opleidingId)->where('code', $rij['vakcode'])->first();
            if (! $vak) {
                $overgeslagen[] = $rij['opleiding'].'/'.$rij['vakcode'].' (onbekend vak)';

                continue;
            }

            if ($vak->docent_id !== $docent->id) {
                $vak->update(['docent_id' => $docent->id]);
            }
            $gekoppeld++;
        }

        $this->command?->info("Docentkoppeling: {$gekoppeld} vakken gekoppeld.");
        foreach ($overgeslagen as $melding) {
            $this->command?->warn("Overgeslagen: {$melding}");
        }
    }

    /** Genormaliseerde matchsleutel voor een achternaam. */
    private function sleutel(string $achternaam): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtr(mb_strtolower($achternaam), self::TRANSLIT));
    }

    /** @return list<array{opleiding:string,vakcode:string,achternaam:string}> */
    private function regels(string $pad): array
    {
        $handle = fopen($pad, 'r');
        $kop = fgetcsv($handle, 0, ';');
        $kop[0] = ltrim((string) $kop[0], "\xEF\xBB\xBF");

        $regels = [];
        while (($kolommen = fgetcsv($handle, 0, ';')) !== false) {
            if (count($kolommen) < count($kop) || trim((string) $kolommen[0]) === '') {
                continue;
            }
            $regels[] = array_combine($kop, array_map(fn ($c) => trim((string) $c), $kolommen));
        }
        fclose($handle);

        return $regels;
    }
}
