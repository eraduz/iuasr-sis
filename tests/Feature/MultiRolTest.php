<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Multi-rol: een gebruiker kan naast de primaire rol extra rollen hebben. De
 * rechten zijn de UNIE over alle rollen; de scoping volgt de ruimste rol. De
 * Beheerder kan gebruikers aanmaken en de rolset beheren (gelogd), met een
 * waarschuwing bij risicocombinaties.
 */
class MultiRolTest extends TestCase
{
    use RefreshDatabase;

    private function beheerder(): User
    {
        return User::create(['naam' => 'Beheer', 'email' => 'beheer@iuasr.test', 'rol' => Rol::Beheerder]);
    }

    public function test_extra_rol_geeft_toegang_via_middleware(): void
    {
        // Primair Docent (geen beheer), maar met Beheerder als extra rol.
        $docent = User::create(['naam' => 'D', 'email' => 'd@iuasr.test', 'rol' => Rol::Docent]);
        $this->actingAs($docent)->get('/gebruikers')->assertForbidden();

        $docent->rolToewijzingen()->create(['rol' => Rol::Beheerder->value]);
        $docent->unsetRelation('rolToewijzingen');

        $this->actingAs($docent)->get('/gebruikers')->assertOk();
    }

    public function test_rechten_zijn_unie_over_rollen(): void
    {
        $u = User::create(['naam' => 'SZ+Doc', 'email' => 'szdoc@iuasr.test', 'rol' => Rol::Studentenzaken]);
        $this->assertFalse($u->magCijfersInzien());
        $this->assertTrue($u->magInschrijvingBeheren());

        $u->rolToewijzingen()->create(['rol' => Rol::Examencommissie->value]);
        $u->unsetRelation('rolToewijzingen');

        // Studentenzaken behoudt inschrijvingbeheer; via Examencommissie komt er
        // cijferinzage bij. De rechten zijn opgeteld.
        $this->assertTrue($u->magInschrijvingBeheren());
        $this->assertTrue($u->magCijfersInzien());
        $this->assertTrue($u->heeftRol(Rol::Examencommissie));
        $this->assertTrue($u->heeftRol(Rol::Studentenzaken));
    }

    public function test_opleiding_scoping_verruimt_bij_bredere_rol(): void
    {
        $directie = User::create(['naam' => 'Dir', 'email' => 'dir@iuasr.test', 'rol' => Rol::Directie]);
        $this->assertTrue($directie->isOpleidingBeperkt());

        // Met Studentenzaken erbij (ziet alle opleidingen) vervalt de grens.
        $directie->rolToewijzingen()->create(['rol' => Rol::Studentenzaken->value]);
        $directie->unsetRelation('rolToewijzingen');

        $this->assertFalse($directie->isOpleidingBeperkt());
    }

    public function test_beheerder_maakt_gebruiker_met_extra_rollen_aan(): void
    {
        $this->actingAs($this->beheerder())->post(route('gebruikers.store'), [
            'naam' => 'Nieuwe Collega',
            'email' => 'nieuw@iuasr.test',
            'rol' => Rol::Financien->value,
            'rollen' => [Rol::Cursusadministratie->value],
            'actief' => '1',
        ])->assertRedirect(route('gebruikers'));

        $user = User::where('email', 'nieuw@iuasr.test')->firstOrFail();
        $this->assertSame(Rol::Financien, $user->rol);
        $this->assertTrue($user->heeftRol(Rol::Cursusadministratie));
        $this->assertDatabaseHas('roltoewijzingen', [
            'user_id' => $user->id, 'rol' => Rol::Cursusadministratie->value,
        ]);
        $this->assertDatabaseHas('audit_logs', ['veld' => 'gebruiker', 'actie' => 'aanmaak']);
    }

    public function test_primaire_rol_wordt_niet_dubbel_als_extra_opgeslagen(): void
    {
        $doel = User::create(['naam' => 'X', 'email' => 'x@iuasr.test', 'rol' => Rol::Docent]);

        $this->actingAs($this->beheerder())->put(route('gebruikers.rol', $doel), [
            'rol' => Rol::Docent->value,
            'rollen' => [Rol::Docent->value, Rol::Examencommissie->value],
        ])->assertRedirect(route('gebruikers'));

        // De primaire rol (Docent) hoort niet als extra rol in de koppeltabel.
        $this->assertDatabaseMissing('roltoewijzingen', [
            'user_id' => $doel->id, 'rol' => Rol::Docent->value,
        ]);
        $this->assertDatabaseHas('roltoewijzingen', [
            'user_id' => $doel->id, 'rol' => Rol::Examencommissie->value,
        ]);
    }

    public function test_risicocombinatie_wordt_gemeld_en_gelogd(): void
    {
        $this->actingAs($this->beheerder())->post(route('gebruikers.store'), [
            'naam' => 'Dubbelrol',
            'email' => 'dubbel@iuasr.test',
            'rol' => Rol::Studentenzaken->value,
            'rollen' => [Rol::Docent->value],
        ])->assertSessionHas('status', fn ($status) => str_contains($status, 'cijferinzage'));

        $this->assertDatabaseHas('audit_logs', ['veld' => 'gebruiker', 'actie' => 'aanmaak']);
    }

    public function test_extra_rollen_helper_sluit_primaire_rol_uit(): void
    {
        $u = User::create(['naam' => 'Y', 'email' => 'y@iuasr.test', 'rol' => Rol::Studentenzaken]);
        $u->rolToewijzingen()->create(['rol' => Rol::Financien->value]);
        $u->unsetRelation('rolToewijzingen');

        $this->assertEqualsCanonicalizing(
            [Rol::Studentenzaken->value, Rol::Financien->value],
            $u->rolSleutels()
        );
        $this->assertSame([Rol::Financien->value], $u->extraRollen()->map(fn ($r) => $r->value)->all());
    }
}
