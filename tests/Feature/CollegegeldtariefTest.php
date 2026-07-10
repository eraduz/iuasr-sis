<?php

namespace Tests\Feature;

use App\Models\CollegegeldTarief;
use App\Models\Inschrijving;
use App\Models\Opleiding;
use App\Models\Periode;
use App\Support\Collegegeldstatus;
use App\Support\Collegegeldtermijnen;
use Carbon\Carbon;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De vastgestelde collegegeldtarieven voor studiejaar 2026-2027
 * (opdrachtgever, 2026-07-10). Zie de migratie collegegeldtarieven_2026_2027.
 */
class CollegegeldtariefTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferentieSeeder::class); // draait ook de tarief-migratie
    }

    /** @return array<string, float> code => verwacht jaartarief */
    public static function tarieven(): array
    {
        return [
            'Bachelor Theologie (ISLTH)' => ['ISLTH', 3500.0],
            'Pre-Master GV (PMGV)' => ['PMGV', 3500.0],
            'Master GV / IGV (MGV)' => ['MGV', 4000.0],
            'PABO' => ['PABO', 3500.0],
        ];
    }

    /**
     * @dataProvider tarieven
     */
    public function test_tarief_2026_2027_is_vastgesteld(string $code, float $verwacht): void
    {
        $periode = Periode::where('code', '2026-2027')->firstOrFail();
        $opleiding = Opleiding::where('code', $code)->firstOrFail();

        $tarief = CollegegeldTarief::where('periode_id', $periode->id)
            ->where('opleiding_id', $opleiding->id)->firstOrFail();

        $this->assertSame($verwacht, (float) $tarief->bedrag);
        $this->assertSame(5, $tarief->aantal_termijnen);
    }

    public function test_termijnbedragen_kloppen_met_het_tarief(): void
    {
        Carbon::setTestNow('2026-10-01');
        $periode = Periode::where('code', '2026-2027')->firstOrFail();
        $pmgv = Opleiding::where('code', 'PMGV')->firstOrFail();

        $inschrijving = Inschrijving::create([
            'student_id' => \App\Models\Student::create([
                'studentnummer' => '269000', 'voornaam' => 'T', 'achternaam' => 'Test', 'geboortedatum' => '2000-01-01',
            ])->id,
            'opleiding_id' => $pmgv->id, 'periode_id' => $periode->id,
            'leerjaar' => 1, 'status' => 'actief', 'inschrijfdatum' => '2026-09-01',
        ]);

        $this->assertSame(3500.0, Collegegeldstatus::jaarbedrag($inschrijving));

        $termijnen = Collegegeldtermijnen::voor($inschrijving);
        $this->assertCount(5, $termijnen);
        $this->assertSame(700.0, $termijnen[0]['bedrag']);
        $this->assertSame(3500.0, round($termijnen->sum('bedrag'), 2));

        Carbon::setTestNow();
    }
}
