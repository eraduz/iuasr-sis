<?php

namespace Tests\Feature;

use App\Models\Vak;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\ToetsonderdeelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De werkelijke toetsopbouw uit de studiegids (database/data/toetsonderdelen.csv)
 * wordt correct per ISLTH-vak geladen; genestte weging is afgevlakt en telt op
 * tot 100%. Vakken buiten de bron (o.a. keuzevakken) houden hun standaardopbouw.
 */
class ToetsopbouwTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, CurriculumSeeder::class, ToetsonderdeelSeeder::class]);
    }

    private function onderdelen(string $code)
    {
        return Vak::where('code', $code)->firstOrFail()->toetsonderdelen()->orderBy('volgorde')->get();
    }

    public function test_arabisch_i_heeft_grammatica_vertalen_mondeling(): void
    {
        $od = $this->onderdelen('B-AR01-15');
        $this->assertSame(['Grammatica', 'Vertalen', 'Mondeling'], $od->pluck('naam')->all());
        $this->assertEqualsWithDelta(0.40, (float) $od[0]->weging, 0.001);
        $this->assertEqualsWithDelta(0.20, (float) $od[2]->weging, 0.001);
    }

    public function test_qoranrecitatie_heeft_mondeling_en_schriftelijk(): void
    {
        $od = $this->onderdelen('B-QR02');
        $this->assertSame(['Memorisatie', 'Recitatie', 'Schriftelijk'], $od->pluck('naam')->all());
        $this->assertEqualsWithDelta(1.00, (float) $od->sum(fn ($o) => (float) $o->weging), 0.001);
    }

    public function test_enkelvoudig_tentamen_blijft_honderd_procent(): void
    {
        $od = $this->onderdelen('B-KL01');
        $this->assertCount(1, $od);
        $this->assertEqualsWithDelta(1.00, (float) $od[0]->weging, 0.001);
    }

    public function test_weging_telt_per_vak_op_tot_honderd_procent(): void
    {
        // Alle vakken uit de bron (ISLTH + MGV): de som van de weging is 1,00
        // (afgevlakte nesting).
        $codes = ['B-AR05-15', 'B-QR01', 'B-QR07', 'B-QR08-15', 'B-KL03', 'B-KL04',
            'B-MT01', 'B-MT02', 'B-GF01', 'B-SC04', 'B-FQ03', 'B-FQ06', 'B-FQ07a',
            'M-GV02', 'M-GV07', 'M-GV08', 'M-GV13', 'M-GV14', 'M-GV16a'];
        foreach ($codes as $code) {
            $som = (float) $this->onderdelen($code)->sum(fn ($o) => (float) $o->weging);
            $this->assertEqualsWithDelta(1.00, $som, 0.001, "Weging {$code} telt niet op tot 1,00");
        }
    }

    public function test_mgv_module_met_vier_onderdelen(): void
    {
        // M-GV07: Werkstuk 40% + Voordracht 20% + Rollenspel 25% + Moreel beraad 15%.
        $od = $this->onderdelen('M-GV07');
        $this->assertSame(['Werkstuk', 'Voordracht', 'Rollenspel', 'Moreel beraad'], $od->pluck('naam')->all());
        $this->assertEqualsWithDelta(0.40, (float) $od[0]->weging, 0.001);
        $this->assertEqualsWithDelta(0.15, (float) $od[3]->weging, 0.001);
    }

    public function test_mgv_scriptie_en_stage_enkelvoudig(): void
    {
        $this->assertCount(1, $this->onderdelen('M-GV17')); // Masterscriptie 100%
        $this->assertCount(1, $this->onderdelen('M-GV18')); // Stagebeoordeling 100%
        $this->assertSame('Masterscriptie', $this->onderdelen('M-GV17')->first()->naam);
    }

    public function test_keuzevak_buiten_de_bron_houdt_standaardopbouw(): void
    {
        // Keuzevakken staan niet in de bron en houden de standaard "Tentamen 100%".
        $od = $this->onderdelen('B-KV01-17');
        $this->assertCount(1, $od);
        $this->assertSame('Tentamen', $od[0]->naam);
    }

    public function test_elk_vak_houdt_minstens_een_toetsonderdeel(): void
    {
        $this->assertSame(0, Vak::doesntHave('toetsonderdelen')->count());
    }
}
