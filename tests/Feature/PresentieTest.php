<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Inschrijving;
use App\Models\Presentie;
use App\Models\User;
use App\Models\Vak;
use App\Models\Vaktoewijzing;
use App\Support\Presentiebewaking;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischVakSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Database\Seeders\VaktoewijzingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresentieTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, SynthetischVakSeeder::class, DocentSeeder::class, GebruikerSeeder::class,
            SynthetischeStudentSeeder::class, VaktoewijzingSeeder::class]);
    }

    /** Een vak met deelnemers, plus de docent-user die eraan gekoppeld is. */
    private function vakMetDeelnemers(): Vak
    {
        return Vak::where('actief', true)->get()
            ->first(fn (Vak $v) => $v->docent_id !== null && $v->deelnemers()->count() > 0);
    }

    private function docentVan(Vak $vak): User
    {
        return User::where('rol', Rol::Docent)->where('docent_id', $vak->docent_id)->firstOrFail();
    }

    public function test_docent_registreert_aanwezigheid_voor_eigen_vak(): void
    {
        $vak = $this->vakMetDeelnemers();
        $insch = $vak->deelnemers()->first();

        $this->actingAs($this->docentVan($vak))
            ->post(route('vakken.presentie.opslaan', $vak), [
                'presentie' => [$insch->id => [1 => '1', 2 => '0']],
            ])->assertRedirect(route('vakken.presentie', $vak));

        $this->assertDatabaseHas('presenties', [
            'inschrijving_id' => $insch->id, 'vak_id' => $vak->id, 'week' => 1, 'aanwezig' => 1,
        ]);
        $this->assertDatabaseHas('presenties', [
            'inschrijving_id' => $insch->id, 'vak_id' => $vak->id, 'week' => 2, 'aanwezig' => 0,
        ]);
        $this->assertDatabaseHas('audit_logs', ['veld' => 'presentie', 'actie' => 'wijziging']);
    }

    public function test_lege_week_wordt_niet_geregistreerd(): void
    {
        $vak = $this->vakMetDeelnemers();
        $insch = $vak->deelnemers()->first();

        $this->actingAs($this->docentVan($vak))
            ->post(route('vakken.presentie.opslaan', $vak), [
                'presentie' => [$insch->id => [1 => '1', 2 => '']],
            ])->assertRedirect();

        $this->assertDatabaseMissing('presenties', [
            'inschrijving_id' => $insch->id, 'vak_id' => $vak->id, 'week' => 2,
        ]);
    }

    public function test_lege_week_wist_een_eerdere_registratie(): void
    {
        $vak = $this->vakMetDeelnemers();
        $insch = $vak->deelnemers()->first();
        $docent = $this->docentVan($vak);

        Presentie::create(['inschrijving_id' => $insch->id, 'vak_id' => $vak->id, 'week' => 3, 'aanwezig' => true]);

        $this->actingAs($docent)->post(route('vakken.presentie.opslaan', $vak), [
            'presentie' => [$insch->id => [3 => '']],
        ])->assertRedirect();

        $this->assertDatabaseMissing('presenties', [
            'inschrijving_id' => $insch->id, 'vak_id' => $vak->id, 'week' => 3,
        ]);
    }

    public function test_vrijgestelde_student_krijgt_geen_registratie(): void
    {
        $vak = $this->vakMetDeelnemers();
        $insch = $vak->deelnemers()->first();

        Vaktoewijzing::where('vak_id', $vak->id)->where('inschrijving_id', $insch->id)
            ->update(['vrijgesteld' => true, 'vrijstelling_ec' => $vak->ec]);

        $this->actingAs($this->docentVan($vak))
            ->post(route('vakken.presentie.opslaan', $vak), [
                'presentie' => [$insch->id => [1 => '1']],
            ])->assertRedirect();

        $this->assertDatabaseMissing('presenties', ['inschrijving_id' => $insch->id, 'vak_id' => $vak->id]);
    }

    public function test_docent_kan_geen_ander_vak_registreren(): void
    {
        $vak = $this->vakMetDeelnemers();
        $ander = Vak::where('actief', true)->whereNotNull('docent_id')
            ->where('docent_id', '!=', $vak->docent_id)->firstOrFail();

        $this->actingAs($this->docentVan($vak))
            ->post(route('vakken.presentie.opslaan', $ander), ['presentie' => []])
            ->assertForbidden();

        $this->actingAs($this->docentVan($vak))
            ->get(route('vakken.presentie', $ander))->assertForbidden();
    }

    public function test_studentenzaken_heeft_geen_toegang_tot_presentie(): void
    {
        $vak = $this->vakMetDeelnemers();

        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->get(route('vakken.presentie', $vak))->assertForbidden();
        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->get(route('presentieoverzicht'))->assertForbidden();
    }

    public function test_examencommissie_en_bestuur_zien_de_lijst_alleen_lezen(): void
    {
        $vak = $this->vakMetDeelnemers();

        foreach ([Rol::Examencommissie, Rol::Bestuur] as $rol) {
            $this->actingAs(User::where('rol', $rol)->first())
                ->get(route('vakken.presentie', $vak))->assertOk();
            $this->actingAs(User::where('rol', $rol)->first())
                ->post(route('vakken.presentie.opslaan', $vak), ['presentie' => []])
                ->assertForbidden();
        }
    }

    public function test_inzage_wordt_gelogd(): void
    {
        $vak = $this->vakMetDeelnemers();

        $this->actingAs(User::where('rol', Rol::Examencommissie)->first())
            ->get(route('vakken.presentie', $vak))->assertOk();

        $this->assertDatabaseHas('audit_logs', ['veld' => 'presentie', 'actie' => 'inzage']);
    }

    public function test_percentage_telt_alleen_geregistreerde_weken(): void
    {
        $vak = $this->vakMetDeelnemers();
        $insch = $vak->deelnemers()->first();

        // 3 registraties: 2 aanwezig, 1 afwezig => 67%, ondanks 8 weken in het blok.
        Presentie::create(['inschrijving_id' => $insch->id, 'vak_id' => $vak->id, 'week' => 1, 'aanwezig' => true]);
        Presentie::create(['inschrijving_id' => $insch->id, 'vak_id' => $vak->id, 'week' => 2, 'aanwezig' => true]);
        Presentie::create(['inschrijving_id' => $insch->id, 'vak_id' => $vak->id, 'week' => 3, 'aanwezig' => false]);

        $status = Presentiebewaking::status($insch->fresh(), $insch->presenties()->get());

        $this->assertSame(3, $status['geregistreerd']);
        $this->assertSame(67, $status['percentage']);
        // Norm 75% (studiegids §2.3.3); 67% blijft daarmee onvoldoende.
        $this->assertSame(75, $status['norm']);
        $this->assertSame('onvoldoende', $status['status']);
    }

    public function test_regeling_verlaagt_de_norm_naar_vijftig_procent(): void
    {
        $vak = $this->vakMetDeelnemers();
        $insch = $vak->deelnemers()->first();
        $insch->update(['aanwezigheidsregeling_50' => true]);

        Presentie::create(['inschrijving_id' => $insch->id, 'vak_id' => $vak->id, 'week' => 1, 'aanwezig' => true]);
        Presentie::create(['inschrijving_id' => $insch->id, 'vak_id' => $vak->id, 'week' => 2, 'aanwezig' => false]);

        $status = Presentiebewaking::status($insch->fresh(), $insch->presenties()->get());

        $this->assertSame(50, $status['percentage']);
        $this->assertSame(50, $status['norm']);
        $this->assertSame('voldoende', $status['status']);
    }

    public function test_zonder_registratie_is_de_status_onbekend(): void
    {
        $insch = Inschrijving::where('status', 'actief')->firstOrFail();

        $status = Presentiebewaking::status($insch, collect());

        $this->assertNull($status['percentage']);
        $this->assertSame('onbekend', $status['status']);
    }

    public function test_week_is_pas_volledig_als_iedereen_geregistreerd_is(): void
    {
        $vak = Vak::where('actief', true)->get()
            ->first(fn (Vak $v) => $v->docent_id !== null && $v->deelnemers()->count() > 1);
        $deelnemers = $vak->deelnemers()->get();

        Presentie::create(['inschrijving_id' => $deelnemers[0]->id, 'vak_id' => $vak->id, 'week' => 1, 'aanwezig' => true]);
        $this->assertContains(1, Presentiebewaking::voorVak($vak)['samenvatting']['weken_ontbrekend']);

        foreach ($deelnemers->skip(1) as $insch) {
            Presentie::create(['inschrijving_id' => $insch->id, 'vak_id' => $vak->id, 'week' => 1, 'aanwezig' => true]);
        }
        $this->assertNotContains(1, Presentiebewaking::voorVak($vak)['samenvatting']['weken_ontbrekend']);
    }
}
