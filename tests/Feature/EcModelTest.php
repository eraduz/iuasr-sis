<?php

namespace Tests\Feature;

use App\Models\Resultaat;
use App\Models\Vak;
use App\Support\Cijferberekening;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Database\Seeders\SynthetischVakSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EC-model (knock-out vs. compensatorisch). Instelbaar per opleiding met terugval
 * op config('sis.cijfers.ec_model'); zie de studiegids-analyse 2026-07-11. De
 * standaard blijft 'knockout' (bestaand gedrag), 'compensatorisch' laat een
 * onvoldoende onderdeel compenseren door een voldoende gewogen eindcijfer.
 */
class EcModelTest extends TestCase
{
    use RefreshDatabase;

    private Vak $vak;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, SynthetischVakSeeder::class, GebruikerSeeder::class, SynthetischeStudentSeeder::class]);
        $this->vak = Vak::where('code', 'ISLTH-ARA-101')->with(['toetsonderdelen', 'opleiding'])->firstOrFail();
    }

    /**
     * Bouwt onbewaarde resultaten: het onderdeel met de KLEINSTE weging krijgt een
     * onvoldoende, de rest een ruime voldoende. Zo zakt de student op knock-out
     * (één onderdeel < cesuur) maar slaagt hij compensatorisch (gewogen ≥ cesuur).
     *
     * @return \Illuminate\Support\Collection<int, Resultaat>
     */
    private function resultatenEenOnvoldoende(): \Illuminate\Support\Collection
    {
        $kleinste = $this->vak->toetsonderdelen->sortBy('weging')->first();

        return $this->vak->toetsonderdelen->map(function ($od) use ($kleinste) {
            return (new Resultaat)->forceFill([
                'toetsonderdeel_id' => $od->id,
                'cijfer' => $od->id === $kleinste->id ? 4.0 : 8.0,
                'vrijstelling' => false,
                'poging' => 'tentamen',
                'poging_nr' => 1,
            ]);
        })->values();
    }

    public function test_standaard_model_is_knockout(): void
    {
        $this->assertSame('knockout', Cijferberekening::ecModel($this->vak));
    }

    public function test_knockout_geen_ec_bij_een_onvoldoende_onderdeel(): void
    {
        $ec = Cijferberekening::ec($this->vak, $this->resultatenEenOnvoldoende());
        $this->assertSame(0.0, $ec);
    }

    public function test_compensatorisch_kent_ec_toe_bij_voldoende_eindcijfer(): void
    {
        $this->vak->opleiding->update(['ec_model' => 'compensatorisch']);
        $vak = Vak::with(['toetsonderdelen', 'opleiding'])->find($this->vak->id);

        $this->assertSame('compensatorisch', Cijferberekening::ecModel($vak));

        // Het onderdeel met de kleinste weging is onvoldoende (4,0), de rest 8,0.
        // Het gewogen eindcijfer blijft ruim ≥ cesuur, dus volledige vak-EC.
        $ec = Cijferberekening::ec($vak, $this->resultatenEenOnvoldoende());
        $this->assertSame((float) $vak->ec, $ec);
    }

    public function test_compensatorisch_geen_ec_als_eindcijfer_onvoldoende(): void
    {
        $this->vak->opleiding->update(['ec_model' => 'compensatorisch']);
        $vak = Vak::with(['toetsonderdelen', 'opleiding'])->find($this->vak->id);

        // Alle onderdelen onvoldoende → gewogen eindcijfer < cesuur → 0 EC.
        $resultaten = $vak->toetsonderdelen->map(fn ($od) => (new Resultaat)->forceFill([
            'toetsonderdeel_id' => $od->id, 'cijfer' => 4.0, 'vrijstelling' => false,
            'poging' => 'tentamen', 'poging_nr' => 1,
        ]))->values();

        $this->assertSame(0.0, Cijferberekening::ec($vak, $resultaten));
    }

    public function test_config_terugval_wordt_gebruikt_zonder_opleidingskeuze(): void
    {
        config(['sis.cijfers.ec_model' => 'compensatorisch']);
        // Opleiding heeft geen eigen ec_model → terugval op de config.
        $this->assertNull($this->vak->opleiding->ec_model);
        $this->assertSame('compensatorisch', Cijferberekening::ecModel($this->vak));
    }
}
