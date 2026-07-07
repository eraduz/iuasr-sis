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
}
