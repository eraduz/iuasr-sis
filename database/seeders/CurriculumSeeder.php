<?php

namespace Database\Seeders;

use App\Models\Opleiding;
use App\Models\Vak;
use Illuminate\Database\Seeder;

/**
 * Het werkelijke IUASR-curriculum uit `database/data/curriculum.csv`
 * (bron: 'vakkenlijst update.xlsx', aangeleverd 2026-07-10).
 *
 * Dit is referentiedata, geen persoonsgegevens: de lijst hoort in Git.
 *
 * Idempotent: vakken worden gematcht op (opleiding, code) en bijgewerkt.
 * Bestaande vakken die NIET in de lijst staan (o.a. de synthetische testvakken)
 * blijven ongemoeid, zodat hun cijfers en presentieregistraties bewaard blijven.
 *
 * Uitgangspunten (vastgesteld met de opdrachtgever):
 *  - EC mag een halve punt zijn (2,5).
 *  - Een lege `blok` betekent: het vak loopt het hele studiejaar (stage, scriptie).
 *  - `keuzevak` = ja: hoort bij de keuzeruimte en wordt NIET automatisch toegewezen.
 *  - PABO volgt later.
 */
class CurriculumSeeder extends Seeder
{
    public const BESTAND = 'database/data/curriculum.csv';

    public function run(): void
    {
        $pad = base_path(self::BESTAND);
        if (! is_readable($pad)) {
            $this->command?->warn('curriculum.csv niet gevonden; curriculum overgeslagen.');

            return;
        }

        $opleidingen = Opleiding::pluck('id', 'code');
        $nieuw = 0;
        $bijgewerkt = 0;
        $overgeslagen = [];

        foreach ($this->regels($pad) as $rij) {
            $opleidingId = $opleidingen[$rij['opleiding']] ?? null;
            if ($opleidingId === null) {
                $overgeslagen[] = $rij['opleiding'].'/'.$rij['code'].' (onbekende opleiding)';

                continue;
            }

            $vak = Vak::firstOrNew(['opleiding_id' => $opleidingId, 'code' => $rij['code']]);
            $bestond = $vak->exists;

            $vak->fill([
                'naam' => $rij['naam'],
                'ec' => (float) $rij['ec'],
                'leerjaar' => $rij['leerjaar'] !== '' ? (int) $rij['leerjaar'] : null,
                // Leeg = geen vast blok: het vak loopt het hele studiejaar.
                'blok' => $rij['blok'] !== '' ? (int) $rij['blok'] : null,
                'keuzevak' => strtolower($rij['keuzevak']) === 'ja',
                'actief' => true,
            ])->save();

            // Zonder toetsonderdeel kan een docent geen cijfers invoeren en levert
            // de EC-berekening altijd 0 op. De vakkenlijst bevat geen toetsopbouw,
            // dus krijgt elk nieuw vak één tentamen met weging 100% — dezelfde
            // standaard die de vakstructuur-UI hanteert. Beheer verfijnt dit later.
            if ($vak->toetsonderdelen()->doesntExist()) {
                $vak->toetsonderdelen()->create([
                    'code' => 'TEN', 'naam' => 'Tentamen', 'type' => 'tentamen',
                    'weging' => 1.00, 'telt_mee' => true, 'volgorde' => 1,
                ]);
            }

            $bestond ? $bijgewerkt++ : $nieuw++;
        }

        $this->command?->info("Curriculum: {$nieuw} nieuw, {$bijgewerkt} bijgewerkt.");
        foreach ($overgeslagen as $melding) {
            $this->command?->warn("Overgeslagen: {$melding}");
        }
    }

    /** @return list<array{opleiding:string,code:string,naam:string,ec:string,leerjaar:string,blok:string,keuzevak:string}> */
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
