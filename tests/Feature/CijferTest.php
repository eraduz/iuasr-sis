<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Docent;
use App\Models\Resultaat;
use App\Models\User;
use App\Models\Vak;
use App\Support\Cijferberekening;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CijferTest extends TestCase
{
    use RefreshDatabase;

    private Vak $vak;
    private User $docent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, GebruikerSeeder::class, SynthetischeStudentSeeder::class]);
        $this->vak = Vak::where('code', 'ISLTH-ARA-101')->first();
        $this->docent = User::where('rol', Rol::Docent)->first();
    }

    public function test_docent_ziet_eigen_vak_maar_niet_dat_van_een_ander(): void
    {
        $this->actingAs($this->docent)->get(route('vakken.cijfers', $this->vak))->assertOk();

        $andere = Docent::create(['code' => 'DOC-099', 'achternaam' => 'Anders']);
        $anderVak = Vak::create([
            'opleiding_id' => $this->vak->opleiding_id, 'docent_id' => $andere->id,
            'code' => 'X-999', 'naam' => 'Ander vak', 'ec' => 6, 'leerjaar' => 1, 'actief' => true,
        ]);

        $this->actingAs($this->docent)->get(route('vakken.cijfers', $anderVak))->assertForbidden();
    }

    public function test_studentenzaken_heeft_geen_toegang_tot_cijfers(): void
    {
        $sz = User::create(['naam' => 'SZ', 'email' => 'sz@iuasr.test', 'rol' => Rol::Studentenzaken]);
        $this->actingAs($sz)->get(route('vakken.cijfers', $this->vak))->assertForbidden();
        $this->actingAs($sz)->get(route('cijferoverzicht'))->assertForbidden();
    }

    public function test_docent_voert_cijfers_in_en_ec_wordt_correct_toegekend(): void
    {
        $insch = $this->vak->deelnemers()->first();
        $onderdelen = $this->vak->toetsonderdelen;

        // Alles voldoende -> volledige EC.
        $payload = ['poging' => [$insch->id => 'tentamen'], 'cijfer' => [$insch->id => []]];
        foreach ($onderdelen as $od) {
            $payload['cijfer'][$insch->id][$od->id] = '7,0';
        }

        $this->actingAs($this->docent)
            ->post(route('vakken.cijfers.opslaan', $this->vak), $payload)
            ->assertRedirect(route('vakken.cijfers', $this->vak));

        $rs = Resultaat::where('inschrijving_id', $insch->id)->get();
        $this->assertCount($onderdelen->count(), $rs);
        $this->assertTrue($rs->every(fn ($r) => $r->voldoende === true));
        $this->assertSame(6, Cijferberekening::ec($this->vak->load('toetsonderdelen', 'opleiding'), $rs));

        // Elke cijfermutatie wordt gelogd.
        $this->assertDatabaseHas('audit_logs', ['veld' => 'cijfer', 'actie' => 'wijziging']);
    }

    public function test_onvoldoende_deeltoets_levert_geen_ec_op(): void
    {
        $insch = $this->vak->deelnemers()->first();
        $onderdelen = $this->vak->toetsonderdelen->values();

        $payload = ['poging' => [$insch->id => 'tentamen'], 'cijfer' => [$insch->id => []]];
        $payload['cijfer'][$insch->id][$onderdelen[0]->id] = '4,0'; // onvoldoende
        $payload['cijfer'][$insch->id][$onderdelen[1]->id] = '8,0';
        $payload['cijfer'][$insch->id][$onderdelen[2]->id] = '7,0';

        $this->actingAs($this->docent)->post(route('vakken.cijfers.opslaan', $this->vak), $payload);

        $rs = Resultaat::where('inschrijving_id', $insch->id)->get();
        $this->assertSame(0, Cijferberekening::ec($this->vak->load('toetsonderdelen', 'opleiding'), $rs));
    }

    public function test_examencommissie_heeft_inzage_maar_voert_niet_in(): void
    {
        $ec = User::create(['naam' => 'EC', 'email' => 'ec@iuasr.test', 'rol' => Rol::Examencommissie]);
        $this->actingAs($ec)->get(route('cijferoverzicht'))->assertOk();
        $this->actingAs($ec)->get(route('vakken.cijfers', $this->vak))->assertOk();
        // Reguliere invoer is voorbehouden aan de docent.
        $this->actingAs($ec)->post(route('vakken.cijfers.opslaan', $this->vak), [])->assertForbidden();
    }

    private function examencommissie(): User
    {
        return User::firstOrCreate(['email' => 'ec@iuasr.test'], ['naam' => 'EC', 'rol' => Rol::Examencommissie]);
    }

    private function lijst(): \App\Models\Cijferlijst
    {
        return \App\Models\Cijferlijst::voor($this->vak, \App\Models\Periode::where('actief', true)->first());
    }

    public function test_docent_dient_in_en_examencommissie_stelt_vast(): void
    {
        $insch = $this->vak->deelnemers()->first();
        $payload = ['poging' => [$insch->id => 'tentamen'], 'cijfer' => [$insch->id => []]];
        foreach ($this->vak->toetsonderdelen as $od) {
            $payload['cijfer'][$insch->id][$od->id] = '7,0';
        }

        $this->actingAs($this->docent)->post(route('vakken.cijfers.opslaan', $this->vak), $payload);
        $this->actingAs($this->docent)->post(route('vakken.cijfers.indienen', $this->vak))->assertRedirect();
        $this->assertSame(\App\Enums\CijferlijstStatus::Ingediend, $this->lijst()->status);

        // Docent mag na indienen niet meer bewerken.
        $this->actingAs($this->docent)->post(route('vakken.cijfers.opslaan', $this->vak), $payload)->assertForbidden();

        // Examencommissie stelt vast -> resultaten definitief.
        $this->actingAs($this->examencommissie())->post(route('vakken.cijfers.vaststellen', $this->vak))->assertRedirect();
        $this->assertSame(\App\Enums\CijferlijstStatus::Vastgesteld, $this->lijst()->status);
        $this->assertDatabaseHas('resultaten', ['inschrijving_id' => $insch->id, 'definitief' => true]);
    }

    public function test_terugsturen_zet_de_lijst_terug_naar_concept(): void
    {
        $this->actingAs($this->docent)->post(route('vakken.cijfers.indienen', $this->vak));
        $this->actingAs($this->examencommissie())
            ->post(route('vakken.cijfers.terugsturen', $this->vak), ['opmerking' => 'Cijfer nakijken'])
            ->assertRedirect();

        $lijst = $this->lijst();
        $this->assertSame(\App\Enums\CijferlijstStatus::Concept, $lijst->status);
        $this->assertSame('Cijfer nakijken', $lijst->opmerking);
    }

    public function test_correctie_na_vaststelling_door_examencommissie_wordt_gelogd(): void
    {
        $this->actingAs($this->docent)->post(route('vakken.cijfers.indienen', $this->vak));
        $ec = $this->examencommissie();
        $this->actingAs($ec)->post(route('vakken.cijfers.vaststellen', $this->vak));

        $insch = $this->vak->deelnemers()->first();
        $od = $this->vak->toetsonderdelen->first();
        $this->actingAs($ec)->post(route('vakken.cijfers.opslaan', $this->vak), [
            'poging' => [$insch->id => 'tentamen'],
            'cijfer' => [$insch->id => [$od->id => '8,0']],
        ])->assertRedirect();

        $this->assertDatabaseHas('resultaten', ['inschrijving_id' => $insch->id, 'toetsonderdeel_id' => $od->id, 'cijfer' => 8.0]);
        $this->assertDatabaseHas('audit_logs', ['veld' => 'cijfer', 'actie' => 'wijziging']);
    }
}
