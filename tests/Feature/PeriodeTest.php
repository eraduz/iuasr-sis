<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Periode;
use App\Models\User;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischVakSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, SynthetischVakSeeder::class, GebruikerSeeder::class]);
    }

    public function test_er_is_maar_een_actief_studiejaar_na_activeren(): void
    {
        $oud = Periode::where('actief', true)->first();
        $this->assertSame('2025-2026', $oud->code);

        // Nieuw studiejaar activeren.
        Periode::where('code', '2026-2027')->first()->update(['actief' => true]);

        $this->assertFalse($oud->fresh()->actief);
        $this->assertSame(1, Periode::where('actief', true)->count());
        $this->assertSame('2026-2027', Periode::where('actief', true)->value('code'));
    }

    public function test_beheerder_maakt_nieuw_studiejaar_via_opzoektabellen(): void
    {
        $beheerder = User::where('rol', Rol::Beheerder)->first();

        $this->actingAs($beheerder)->post(route('opzoektabellen.store', 'perioden'), [
            'code' => '2035-2036',
            'naam' => 'Studiejaar 2035 / 2036',
            'startdatum' => '2035-09-01',
            'einddatum' => '2036-07-31',
            'actief' => '1',
        ])->assertRedirect(route('opzoektabellen.tabel', 'perioden'));

        $this->assertSame(1, Periode::where('actief', true)->count());
        $this->assertSame('2035-2036', Periode::where('actief', true)->value('code'));
        $this->assertDatabaseHas('perioden', ['code' => '2035-2036', 'actief' => true]);
    }

    public function test_alleen_beheerder_beheert_perioden(): void
    {
        $sz = User::where('rol', Rol::Studentenzaken)->first();
        $this->actingAs($sz)->get(route('opzoektabellen.tabel', 'perioden'))->assertForbidden();
    }
}
