<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Faculteit;
use App\Models\Opleiding;
use App\Models\User;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeheerTest extends TestCase
{
    use RefreshDatabase;

    private function beheerder(): User
    {
        return User::create(['naam' => 'Beheer', 'email' => 'beheer@iuasr.test', 'rol' => Rol::Beheerder]);
    }

    private function studentenzaken(): User
    {
        return User::create(['naam' => 'SZ', 'email' => 'sz@iuasr.test', 'rol' => Rol::Studentenzaken]);
    }

    public function test_beheer_is_afgeschermd_voor_studentenzaken(): void
    {
        $sz = $this->studentenzaken();
        $this->actingAs($sz)->get('/gebruikers')->assertForbidden();
        $this->actingAs($sz)->get('/opzoektabellen')->assertForbidden();
        $this->actingAs($sz)->get('/audit-log')->assertForbidden();
    }

    public function test_beheerder_kan_opzoektabellen_openen_en_opleiding_toevoegen(): void
    {
        $this->seed(ReferentieSeeder::class);
        $beheerder = $this->beheerder();

        $this->actingAs($beheerder)->get('/opzoektabellen')->assertOk();
        $this->actingAs($beheerder)->get('/opzoektabellen/opleidingen')->assertOk();

        $faculteit = Faculteit::first();
        $this->actingAs($beheerder)->post('/opzoektabellen/opleidingen', [
            'code' => 'TEST',
            'naam' => 'Testopleiding',
            'faculteit_id' => $faculteit->id,
            'soort' => 'bachelor',
        ])->assertRedirect(route('opzoektabellen.tabel', 'opleidingen'));

        $this->assertDatabaseHas('opleidingen', ['code' => 'TEST', 'naam' => 'Testopleiding']);
    }

    public function test_beheerder_kan_rol_van_gebruiker_wijzigen(): void
    {
        $beheerder = $this->beheerder();
        $doel = User::create(['naam' => 'Doel', 'email' => 'doel@iuasr.test', 'rol' => Rol::Docent]);

        $this->actingAs($beheerder)->put(route('gebruikers.rol', $doel), [
            'rol' => Rol::Examencommissie->value,
            'actief' => '1',
        ])->assertRedirect(route('gebruikers'));

        $this->assertSame(Rol::Examencommissie, $doel->fresh()->rol);
        $this->assertDatabaseHas('audit_logs', ['veld' => 'rol', 'actie' => 'wijziging']);
    }
}
