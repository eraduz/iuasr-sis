<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Inschrijving;
use App\Models\Opleiding;
use App\Models\Periode;
use App\Models\Student;
use App\Models\User;
use App\Models\Vak;
use App\Models\Vaktoewijzing;
use App\Support\Ec;
use App\Support\Overgangsbeoordeling;
use App\Support\Vaktoewijzer;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, CurriculumSeeder::class,
            DocentSeeder::class, GebruikerSeeder::class]);
    }

    private function vak(string $code, string $opleiding): Vak
    {
        return Vak::where('code', $code)
            ->whereHas('opleiding', fn ($q) => $q->where('code', $opleiding))
            ->firstOrFail();
    }

    public function test_curriculum_is_geladen(): void
    {
        $this->assertSame(91, Vak::where('code', 'not like', 'ISLTH-%')->count());
    }

    public function test_halve_studiepunten_blijven_behouden(): void
    {
        $vak = $this->vak('B-AR01-15', 'ISLTH');

        $this->assertSame(2.5, $vak->ec);
        $this->assertDatabaseHas('vakken', ['id' => $vak->id, 'ec' => 2.5]);
    }

    public function test_dezelfde_vakcode_mag_in_twee_opleidingen_bestaan(): void
    {
        $bachelor = $this->vak('B-QR02', 'ISLTH');
        $premaster = $this->vak('B-QR02', 'PMGV');

        $this->assertNotSame($bachelor->id, $premaster->id);
        $this->assertSame(2, Vak::where('code', 'B-QR02')->count());
    }

    public function test_vakcode_blijft_uniek_binnen_een_opleiding(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        $bachelor = $this->vak('B-QR02', 'ISLTH');
        Vak::create([
            'opleiding_id' => $bachelor->opleiding_id, 'code' => 'B-QR02',
            'naam' => 'Duplicaat', 'ec' => 5, 'leerjaar' => 1, 'blok' => 1,
        ]);
    }

    public function test_stages_en_scripties_hebben_geen_vast_blok(): void
    {
        foreach (['B-ST01', 'B-ST02', 'B-ST04', 'B-BR01'] as $code) {
            $this->assertNull($this->vak($code, 'ISLTH')->blok, "{$code} hoort het hele studiejaar te lopen");
        }
    }

    /** Bij tegenstrijdigheid in de bronlijst is de tekstkolom 'Blok' leidend. */
    public function test_tegenstrijdige_blokken_volgen_de_tekstkolom(): void
    {
        $this->assertSame(4, $this->vak('B-SC07', 'ISLTH')->blok);
        $this->assertSame(2, $this->vak('B-AR06-15', 'ISLTH')->blok);
    }

    public function test_keuzeruimte_is_gemarkeerd_als_keuzevak(): void
    {
        $keuze = Vak::where('keuzevak', true)->get();

        $this->assertSame(12, $keuze->count());
        $this->assertSame(55.0, round($keuze->sum('ec'), 1));
        $this->assertTrue($this->vak('B-KV01-16', 'ISLTH')->keuzevak);
        $this->assertFalse($this->vak('B-BR01', 'ISLTH')->keuzevak);
    }

    public function test_verplichte_ec_per_leerjaar_klopt_met_de_bronlijst(): void
    {
        $islth = Opleiding::where('code', 'ISLTH')->value('id');
        $verplicht = fn (int $jaar) => round((float) Vak::where('opleiding_id', $islth)
            ->where('leerjaar', $jaar)->where('keuzevak', false)->sum('ec'), 1);

        $this->assertSame(60.0, $verplicht(1));
        $this->assertSame(62.5, $verplicht(2));
        $this->assertSame(57.5, $verplicht(3));
        $this->assertSame(40.0, $verplicht(4)); // zonder keuzeruimte
    }

    /** De pre-master telt 50 EC (12 vakken), niet de 60 die er eerder stond. */
    public function test_premaster_telt_vijftig_ec(): void
    {
        $pmgv = Opleiding::where('code', 'PMGV')->firstOrFail();

        $this->assertSame(50, $pmgv->ec_totaal);
        $this->assertSame(50.0, round((float) Vak::where('opleiding_id', $pmgv->id)->sum('ec'), 1));
    }

    /**
     * Het nominale totaal van een opleiding moet overeenkomen met het curriculum.
     * Bij de bachelor telt de keuzeruimte niet volledig mee: 220 EC verplicht plus
     * 20 EC die de student uit de keuzeruimte kiest.
     */
    public function test_nominale_ec_totalen_kloppen_met_het_curriculum(): void
    {
        $verplicht = fn (string $code) => round((float) Vak::whereHas('opleiding', fn ($q) => $q->where('code', $code))
            ->where('keuzevak', false)->sum('ec'), 1);

        $this->assertSame(50.0, $verplicht('PMGV'));
        $this->assertSame(50, Opleiding::where('code', 'PMGV')->value('ec_totaal'));

        $this->assertSame(120.0, $verplicht('MGV'));
        $this->assertSame(120, Opleiding::where('code', 'MGV')->value('ec_totaal'));

        // Bachelor: 220 verplicht + 20 uit de keuzeruimte = 240.
        $this->assertSame(220.0, $verplicht('ISLTH'));
        $this->assertSame(240, Opleiding::where('code', 'ISLTH')->value('ec_totaal'));
    }

    public function test_elk_vak_heeft_een_toetsonderdeel(): void
    {
        // Zonder toetsonderdeel kan de docent geen cijfer invoeren.
        $this->assertSame(0, Vak::doesntHave('toetsonderdelen')->count());
    }

    public function test_keuzevakken_worden_niet_automatisch_toegewezen(): void
    {
        $inschrijving = $this->inschrijvingIn('ISLTH', leerjaar: 4);

        Vaktoewijzer::wijsToe($inschrijving);

        $toegewezen = Vaktoewijzing::where('inschrijving_id', $inschrijving->id)
            ->with('vak')->get()->pluck('vak');

        $this->assertSame(3, $toegewezen->count());                 // verplicht jaar 4
        $this->assertSame(40.0, round($toegewezen->sum('ec'), 1));
        $this->assertTrue($toegewezen->every(fn (Vak $v) => ! $v->keuzevak));
    }

    public function test_een_gekozen_keuzevak_telt_mee_in_het_overgangsadvies(): void
    {
        $inschrijving = $this->inschrijvingIn('ISLTH', leerjaar: 4);
        Vaktoewijzer::wijsToe($inschrijving);

        $this->assertSame(40.0, Overgangsbeoordeling::mogelijkeEc($inschrijving));

        // Studentenzaken kent één keuzevak van 5 EC toe.
        $keuzevak = $this->vak('B-KV01-16', 'ISLTH');
        Vaktoewijzing::create(['inschrijving_id' => $inschrijving->id, 'vak_id' => $keuzevak->id, 'automatisch' => false]);

        $this->assertSame(45.0, Overgangsbeoordeling::mogelijkeEc($inschrijving));
    }

    public function test_beheer_kan_een_keuzevak_aanmaken_via_de_vakstructuur(): void
    {
        $islth = Opleiding::where('code', 'ISLTH')->value('id');

        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->post(route('vakstructuur.store'), [
                'opleiding_id' => $islth, 'code' => 'B-TEST-01', 'naam' => 'Testvak',
                'ec' => '2.5', 'leerjaar' => '4', 'blok' => '', 'keuzevak' => '1',
            ])->assertSessionHasNoErrors()->assertRedirect();

        $vak = Vak::where('code', 'B-TEST-01')->firstOrFail();
        $this->assertSame(2.5, $vak->ec);
        $this->assertNull($vak->blok);      // leeg blok = hele studiejaar
        $this->assertTrue($vak->keuzevak);
    }

    public function test_ec_wordt_met_een_komma_getoond(): void
    {
        $this->assertSame('2,5', Ec::toon(2.5));
        $this->assertSame('5', Ec::toon(5.0));
        $this->assertSame('—', Ec::toon(null));
    }

    private function inschrijvingIn(string $opleidingCode, int $leerjaar): Inschrijving
    {
        $student = Student::create([
            'studentnummer' => '269999', 'voornaam' => 'Test', 'achternaam' => 'Student',
            'geboortedatum' => '2000-01-01',
        ]);

        return Inschrijving::create([
            'student_id' => $student->id,
            'opleiding_id' => Opleiding::where('code', $opleidingCode)->value('id'),
            'periode_id' => Periode::where('actief', true)->value('id'),
            'leerjaar' => $leerjaar, 'status' => 'actief', 'inschrijfdatum' => '2025-09-01',
        ]);
    }
}
