<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Borgt de noodtoegang (break-glass): maximaal twee accounts, uitsluitend
 * actieve Beheerders, elke poging gelogd. Dit is de enige plek in het systeem
 * waar een wachtwoord toegang geeft — de regels zijn niet-onderhandelbaar.
 */
class NoodaccountTest extends TestCase
{
    use RefreshDatabase;

    private const WACHTWOORD = 'een-heel-lange-wachtwoordzin';

    private function gebruiker(Rol $rol, bool $actief = true): User
    {
        return User::create([
            'naam' => 'Test '.$rol->value,
            'email' => $rol->value.'-'.uniqid().'@iuasr.test',
            'rol' => $rol,
            'actief' => $actief,
        ]);
    }

    private function noodaccount(
        int $slot = 1,
        string $wachtwoord = self::WACHTWOORD,
        Rol $rol = Rol::Beheerder,
        bool $actief = true
    ): User {
        $u = $this->gebruiker($rol, $actief);
        $u->forceFill([
            'noodaccount_slot' => $slot,
            'password' => Hash::make($wachtwoord),
            'wachtwoord_gewijzigd_op' => now(),
        ])->save();

        return $u;
    }

    /** De verzoeklimiet deelt state tussen tests; elke test begint schoon. */
    protected function tearDown(): void
    {
        RateLimiter::clear('noodlogin');
        parent::tearDown();
    }

    // --- Inloggen ---

    public function test_noodaccount_kan_inloggen_met_wachtwoord(): void
    {
        $u = $this->noodaccount();

        $this->post('/noodtoegang', [
            'gebruikersnaam' => $u->email,
            'wachtwoord' => self::WACHTWOORD,
        ])->assertRedirect(route('modules.kiezen'));

        $this->assertAuthenticatedAs($u);
    }

    public function test_onjuist_wachtwoord_wordt_geweigerd_en_gelogd(): void
    {
        $u = $this->noodaccount();

        $this->post('/noodtoegang', [
            'gebruikersnaam' => $u->email,
            'wachtwoord' => 'dit-is-het-verkeerde-wachtwoord',
        ]);

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['actie' => 'noodlogin_mislukt', 'user_id' => null]);

        // De geprobeerde gebruikersnaam moet herleidbaar zijn in de context.
        $log = DB::table('audit_logs')->where('actie', 'noodlogin_mislukt')->first();
        $this->assertStringContainsString($u->email, (string) $log->context);
    }

    public function test_geslaagde_login_wordt_gelogd_met_actor(): void
    {
        $u = $this->noodaccount();

        $this->post('/noodtoegang', [
            'gebruikersnaam' => $u->email,
            'wachtwoord' => self::WACHTWOORD,
        ]);

        // Bewijst dat er NA Auth::login gelogd wordt: user_id en rol zijn gevuld.
        $this->assertDatabaseHas('audit_logs', [
            'actie' => 'noodlogin',
            'user_id' => $u->id,
            'rol' => Rol::Beheerder->value,
        ]);
    }

    public function test_regulier_account_kan_niet_via_noodtoegang(): void
    {
        // Beheerder MET wachtwoord maar zonder noodaccount-plaats.
        $u = $this->gebruiker(Rol::Beheerder);
        $u->forceFill(['password' => Hash::make(self::WACHTWOORD)])->save();

        $this->post('/noodtoegang', [
            'gebruikersnaam' => $u->email,
            'wachtwoord' => self::WACHTWOORD,
        ]);

        $this->assertGuest();
    }

    public function test_inactief_noodaccount_kan_niet_inloggen(): void
    {
        $u = $this->noodaccount(actief: false);

        $this->post('/noodtoegang', [
            'gebruikersnaam' => $u->email,
            'wachtwoord' => self::WACHTWOORD,
        ]);

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['actie' => 'noodlogin_mislukt']);
    }

    public function test_noodaccount_zonder_rol_beheerder_kan_niet_inloggen(): void
    {
        // Juist wachtwoord, wél een noodaccount-plaats, maar niet de rol Beheerder:
        // magNoodloginGebruiken() moet na attempt alsnog uitloggen.
        $u = $this->noodaccount(rol: Rol::Studentenzaken);

        $this->post('/noodtoegang', [
            'gebruikersnaam' => $u->email,
            'wachtwoord' => self::WACHTWOORD,
        ]);

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['actie' => 'noodlogin_mislukt']);
    }

    public function test_account_zonder_wachtwoord_kan_niet_inloggen(): void
    {
        // Regressie: Hash::check op een null-wachtwoord mag geen exception geven.
        $u = $this->gebruiker(Rol::Beheerder);
        $u->forceFill(['noodaccount_slot' => 1])->save();

        $this->post('/noodtoegang', [
            'gebruikersnaam' => $u->email,
            'wachtwoord' => self::WACHTWOORD,
        ]);

        $this->assertGuest();
    }

    public function test_verzoeklimiet_blokkeert_na_te_veel_pogingen(): void
    {
        $u = $this->noodaccount();
        $maximum = (int) config('sis.noodaccount.max_pogingen');

        for ($i = 0; $i < $maximum; $i++) {
            $this->post('/noodtoegang', [
                'gebruikersnaam' => $u->email,
                'wachtwoord' => 'fout-wachtwoord-'.$i,
            ]);
        }

        // De volgende poging wordt geweigerd — zelfs met het JUISTE wachtwoord.
        $this->post('/noodtoegang', [
            'gebruikersnaam' => $u->email,
            'wachtwoord' => self::WACHTWOORD,
        ])->assertStatus(429);

        $this->assertGuest();
    }

    // --- Beheerscherm ---

    public function test_derde_noodaccount_wordt_geweigerd(): void
    {
        $this->noodaccount(slot: 1);
        $this->noodaccount(slot: 2);
        $kandidaat = $this->gebruiker(Rol::Beheerder);
        $beheerder = $this->gebruiker(Rol::Beheerder);

        $this->actingAs($beheerder)->post(route('noodaccounts.store'), [
            'user_id' => $kandidaat->id,
            'wachtwoord' => self::WACHTWOORD,
            'wachtwoord_confirmation' => self::WACHTWOORD,
        ])->assertSessionHas('fout');

        $this->assertNull($kandidaat->fresh()->noodaccount_slot);
        $this->assertSame(2, User::noodaccount()->count());
    }

    public function test_database_dwingt_maximaal_twee_noodaccounts_af(): void
    {
        $this->noodaccount(slot: 1);
        $this->noodaccount(slot: 2);
        $derde = $this->gebruiker(Rol::Beheerder);

        // Buiten de applicatie om: de database zelf moet dit weigeren.
        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $derde->id)->update(['noodaccount_slot' => 3]);
    }

    public function test_te_kort_wachtwoord_wordt_geweigerd(): void
    {
        $n = $this->noodaccount();
        $oude = $n->password;
        $beheerder = $this->gebruiker(Rol::Beheerder);

        $this->actingAs($beheerder)->put(route('noodaccounts.wachtwoord', $n), [
            'bevestig_email' => $n->email,
            'wachtwoord' => 'kort123',
            'wachtwoord_confirmation' => 'kort123',
        ])->assertSessionHasErrors('wachtwoord');

        $this->assertSame($oude, $n->fresh()->password);
    }

    public function test_wachtwoord_zetten_vereist_juist_emailadres(): void
    {
        $n = $this->noodaccount();
        $oude = $n->password;
        $beheerder = $this->gebruiker(Rol::Beheerder);

        $this->actingAs($beheerder)->put(route('noodaccounts.wachtwoord', $n), [
            'bevestig_email' => 'iets-anders@iuasr.test',
            'wachtwoord' => 'een-ander-heel-lang-wachtwoord',
            'wachtwoord_confirmation' => 'een-ander-heel-lang-wachtwoord',
        ])->assertSessionHas('fout');

        $this->assertSame($oude, $n->fresh()->password);
    }

    public function test_noodtoegang_intrekken_wist_het_wachtwoord(): void
    {
        $n = $this->noodaccount();
        $beheerder = $this->gebruiker(Rol::Beheerder);

        $this->actingAs($beheerder)->delete(route('noodaccounts.destroy', $n));

        $vers = $n->fresh();
        $this->assertNull($vers->noodaccount_slot);
        $this->assertNull($vers->password);
        $this->assertTrue($vers->actief, 'Het account zelf blijft bestaan en actief.');
        $this->assertDatabaseHas('audit_logs', ['actie' => 'verwijdering', 'veld' => 'noodaccount']);
    }

    public function test_bestaand_noodaccount_kan_niet_via_aanwijzen_worden_gereset(): void
    {
        // Zonder guard zou een handmatige POST het account naar het andere slot
        // verhuizen én het wachtwoord resetten, langs de bevestig_email-stap heen.
        $n = $this->noodaccount(slot: 1);
        $oude = $n->password;
        $beheerder = $this->gebruiker(Rol::Beheerder);

        $this->actingAs($beheerder)->post(route('noodaccounts.store'), [
            'user_id' => $n->id,
            'wachtwoord' => 'een-nieuw-heel-lang-wachtwoord',
            'wachtwoord_confirmation' => 'een-nieuw-heel-lang-wachtwoord',
        ])->assertForbidden();

        $vers = $n->fresh();
        $this->assertSame(1, $vers->noodaccount_slot, 'Het slot mag niet verschuiven.');
        $this->assertSame($oude, $vers->password, 'Het wachtwoord mag niet zijn gereset.');
    }

    public function test_noodaccount_kan_niet_stilzwijgend_zijn_beheerdersrol_verliezen(): void
    {
        // Anders blijft het slot bezet terwijl inloggen niet meer lukt: het scherm
        // meldt dan twee noodaccounts terwijl er maar één werkt.
        $n = $this->noodaccount(slot: 1);
        $beheerder = $this->gebruiker(Rol::Beheerder);

        $this->actingAs($beheerder)->put(route('gebruikers.rol', $n), [
            'rol' => Rol::Docent->value,
            'actief' => 1,
        ])->assertSessionHas('fout');

        $this->assertTrue($n->fresh()->magNoodloginGebruiken());
    }

    public function test_noodaccount_kan_niet_stilzwijgend_worden_gedeactiveerd(): void
    {
        $n = $this->noodaccount(slot: 1);
        $beheerder = $this->gebruiker(Rol::Beheerder);

        $this->actingAs($beheerder)->put(route('gebruikers.rol', $n), [
            'rol' => Rol::Beheerder->value,
            'actief' => 0,
        ])->assertSessionHas('fout');

        $this->assertTrue($n->fresh()->actief);
    }

    public function test_inlogpogingen_tonen_zowel_geslaagde_als_mislukte(): void
    {
        // De knop 'Inlogpogingen' filtert op de groep 'noodtoegang'. Filteren op
        // alleen 'noodlogin' zou juist de mislukte pogingen verbergen — en dat is
        // de enige detectie die er is, want er is bewust geen accountblokkade.
        $u = $this->noodaccount();
        $this->post('/noodtoegang', ['gebruikersnaam' => $u->email, 'wachtwoord' => 'fout-wachtwoord-hier']);
        $this->post('/noodtoegang', ['gebruikersnaam' => $u->email, 'wachtwoord' => self::WACHTWOORD]);
        $this->post('/logout');

        $this->actingAs($this->gebruiker(Rol::Beheerder))
            ->get(route('audit-log', ['actie' => 'noodtoegang']))
            ->assertOk()
            ->assertSee('noodlogin_mislukt');
    }

    public function test_niet_beheerder_kan_noodaccountscherm_niet_openen(): void
    {
        $this->actingAs($this->gebruiker(Rol::Studentenzaken))
            ->get(route('noodaccounts'))->assertForbidden();

        $this->post('/logout');
        $this->get(route('noodaccounts'))->assertRedirect('/login');
    }

    public function test_mislukte_poging_flasht_het_wachtwoord_niet_naar_de_sessie(): void
    {
        // Laravel houdt bij een validatiefout alleen velden die 'password' héten
        // uit de flash-input. Ons veld heet 'wachtwoord' en moet dus expliciet in
        // dontFlash staan — anders belandt het noodwachtwoord in leesbare vorm in
        // de sessiestore (database/bestand) bij elke mislukte poging.
        $u = $this->noodaccount();
        $geheim = 'dit-wachtwoord-mag-nergens-blijven-staan';

        $this->post('/noodtoegang', [
            'gebruikersnaam' => $u->email,
            'wachtwoord' => $geheim,
        ]);

        $this->assertNull(session('_old_input.wachtwoord'));
        $this->assertStringNotContainsString($geheim, json_encode(session()->all()));
    }

    public function test_wachtwoord_staat_nooit_in_de_audit_log(): void
    {
        $n = $this->noodaccount();
        $beheerder = $this->gebruiker(Rol::Beheerder);
        $nieuw = 'geheime-lange-wachtwoordzin-xyz';

        $this->actingAs($beheerder)->put(route('noodaccounts.wachtwoord', $n), [
            'bevestig_email' => $n->email,
            'wachtwoord' => $nieuw,
            'wachtwoord_confirmation' => $nieuw,
        ]);

        // Noch het wachtwoord zelf, noch de hash mag in het logboek belanden.
        foreach (DB::table('audit_logs')->pluck('context') as $context) {
            $this->assertStringNotContainsString($nieuw, (string) $context);
            $this->assertStringNotContainsString('$2y$', (string) $context);
        }
    }
}
