<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Borgt de veiligheidsgrendel op het anonimiseercommando.
 *
 * Deze test is de reden dat het commando bestaat zoals het bestaat. Op
 * 2026-07-17 is de complete ontwikkeldatabase gewist doordat een commando via
 * `--env=testing` op de verkeerde database uitkwam. Een commando dat gegevens
 * onomkeerbaar overschrijft moet zélf onmogelijk maken dat het de verkeerde
 * database raakt — daar mag geen oplettendheid aan te pas komen.
 *
 * De testdatabase heet `iuasr_sis_test` en eindigt dus NIET op `_demo`. Deze
 * test draait daarmee precies het scenario dat geweigerd moet worden.
 */
class DemoDumpTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonimiseren_weigert_buiten_een_demo_database(): void
    {
        $student = Student::create([
            'studentnummer' => '269999',
            'voornaam' => 'Echte',
            'achternaam' => 'Naam',
            'email' => 'echt@voorbeeld.nl',
        ]);

        // Draait op iuasr_sis_test: moet weigeren, ondanks --force.
        $this->artisan('sis:demo-anonimiseren --force')->assertFailed();

        // En de gegevens moeten onaangeroerd zijn.
        $vers = $student->fresh();
        $this->assertSame('Echte', $vers->voornaam);
        $this->assertSame('Naam', $vers->achternaam);
        $this->assertSame('echt@voorbeeld.nl', $vers->email);
    }

    public function test_de_grendel_kijkt_naar_het_achtervoegsel_van_de_databasenaam(): void
    {
        // Documenteert de regel expliciet, zodat het geen toevalstreffer is.
        $this->assertStringEndsNotWith('_demo', (string) config('database.connections.mysql.database'));

        config(['database.connections.mysql.database' => 'iets_demo']);
        $this->assertStringEndsWith('_demo', (string) config('database.connections.mysql.database'));
    }

    public function test_controlecommando_meldt_echte_gegevens_als_niet_schoon(): void
    {
        // Het controlecommando heeft geen grendel nodig (het wijzigt niets), maar
        // moet wél alarm slaan zodra er een adres buiten @voorbeeld.test staat.
        Student::create([
            'studentnummer' => '269998',
            'voornaam' => 'Echte',
            'achternaam' => 'Naam',
            'email' => 'iemand@hotmail.com',
        ]);

        $this->artisan('sis:demo-controleren')->assertFailed();
    }

    public function test_controlecommando_keurt_geanonimiseerde_gegevens_goed(): void
    {
        Student::create([
            'studentnummer' => '269997',
            'voornaam' => 'Amina',
            'achternaam' => 'Yilmaz',
            'email' => 'student269997@voorbeeld.test',
        ]);

        // De migraties maken zelf de module-accounts aan (balie@iuasr.nl,
        // bibliotheek@iuasr.nl, scriptie@iuasr.nl, stichtingsbestuur@iuasr.nl).
        // Die zijn geen persoonsgegeven, maar het controlecommando kent dat
        // onderscheid niet en slaat er terecht op aan: alles buiten
        // @voorbeeld.test is verdacht. Het echte anonimiseercommando hernoemt ze,
        // dus doet deze test dat hier ook.
        DB::table('users')->update(['email' => DB::raw("CONCAT(id, '@voorbeeld.test')")]);

        $this->artisan('sis:demo-controleren')->assertSuccessful();
    }

    public function test_controlecommando_slaat_aan_op_de_accounts_uit_de_migraties(): void
    {
        // Regressie op een echte valkuil: de module-accounts (balie@iuasr.nl,
        // bibliotheek@iuasr.nl, scriptie@iuasr.nl, stichtingsbestuur@iuasr.nl)
        // worden door MIGRATIES aangemaakt, niet door een seeder. Wie alleen aan
        // de seeders denkt, vergeet ze. Een verse database moet de controle dus
        // uit zichzelf al niet doorstaan.
        $this->assertGreaterThan(0, User::where('email', 'like', '%@iuasr.nl')->count());

        $this->artisan('sis:demo-controleren')->assertFailed();
    }
}
