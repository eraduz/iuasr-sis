<?php

namespace Database\Seeders;

use App\Models\Opleiding;
use App\Models\Resultaat;
use App\Models\Vak;
use Illuminate\Database\Seeder;

/**
 * De werkelijke toetsopbouw (toetsonderdelen + weging) per vak uit
 * `database/data/toetsonderdelen.csv`, ontleend aan de studiegids
 * BA Islamitische Theologie 2025-2026 (hoofdstuk 5, per module de regel
 * "Toetsing:"). Referentiedata, geen persoonsgegevens — hoort in Git.
 *
 * Vervangt de standaard "Tentamen 100%" die `CurriculumSeeder` per vak aanmaakt.
 * Genestte weging is afgevlakt naar losse onderdelen die optellen tot 100%
 * (bv. "Schriftelijk 80% = grammatica 40% + vertalen 40%" + "mondeling 20%"
 * wordt: grammatica 0,40 / vertalen 0,40 / mondeling 0,20).
 *
 * Idempotent en dataveilig: een vak waarvan de huidige onderdelen al
 * resultaten hebben wordt OVERGESLAGEN (vervangen zou cijfers wissen). Alleen
 * ISLTH staat in de bron; PMGV/MGV/PABO volgen met hun eigen studiegids.
 */
class ToetsonderdeelSeeder extends Seeder
{
    public const BESTAND = 'database/data/toetsonderdelen.csv';

    public function run(): void
    {
        $pad = base_path(self::BESTAND);
        if (! is_readable($pad)) {
            $this->command?->warn('toetsonderdelen.csv niet gevonden; toetsopbouw overgeslagen.');

            return;
        }

        $opleidingen = Opleiding::pluck('id', 'code');

        // Groepeer de onderdelen per vak (opleiding + vakcode).
        $perVak = [];
        foreach ($this->regels($pad) as $rij) {
            $perVak[$rij['opleiding'].'|'.$rij['vakcode']][] = $rij;
        }

        $bijgewerkt = 0;
        $overgeslagen = [];
        foreach ($perVak as $sleutel => $onderdelen) {
            [$oplCode, $vakCode] = explode('|', $sleutel, 2);

            $opleidingId = $opleidingen[$oplCode] ?? null;
            if ($opleidingId === null) {
                $overgeslagen[] = $sleutel.' (onbekende opleiding)';

                continue;
            }

            $vak = Vak::where('opleiding_id', $opleidingId)->where('code', $vakCode)->first();
            if (! $vak) {
                $overgeslagen[] = $sleutel.' (onbekend vak)';

                continue;
            }

            // Dataveiligheid: bestaande resultaten mogen niet verloren gaan.
            $heeftResultaten = Resultaat::whereIn('toetsonderdeel_id', $vak->toetsonderdelen()->pluck('id'))->exists();
            if ($heeftResultaten) {
                $overgeslagen[] = $vakCode.' (heeft resultaten; onderdelen behouden)';

                continue;
            }

            $vak->toetsonderdelen()->delete();
            foreach ($onderdelen as $od) {
                $vak->toetsonderdelen()->create([
                    'code' => $od['code'],
                    'naam' => $od['naam'],
                    'type' => $od['type'],
                    'weging' => (float) $od['weging'],
                    'telt_mee' => strtolower($od['telt_mee']) === 'ja',
                    'volgorde' => (int) $od['volgorde'],
                ]);
            }
            $bijgewerkt++;
        }

        $this->command?->info("Toetsopbouw: {$bijgewerkt} vakken bijgewerkt.");
        foreach ($overgeslagen as $melding) {
            $this->command?->warn("Overgeslagen: {$melding}");
        }
    }

    /** @return list<array<string,string>> */
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
