<?php

namespace Tests\Feature;

use App\Enums\ExemplaarStatus;
use App\Enums\Rol;
use App\Models\Bibliotheek\Publicatie;
use App\Models\Bibliotheek\Publicatiesoort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dubbele tijdschriften samenvoegen.
 *
 * Dezelfde titel komt uit twee bronnen: de boekenlijst levert een PLANKREGEL
 * (exemplaar + rekcode, geen uitgaven), het inhoudsbestand levert het echte
 * TIJDSCHRIFT (uitgaven + artikelen, geen exemplaar). Deze tests bewaken dat het
 * samenvoegen niets weggooit en dat er nooit een tijdschrift mét uitgaven wordt
 * opgeslokt.
 */
class TijdschriftSamenvoegTest extends TestCase
{
    use RefreshDatabase;

    private function bibliothecaris(): User
    {
        return User::create(['naam' => 'Bieb', 'email' => 'bieb@iuasr.test', 'rol' => Rol::Bibliotheek]);
    }

    private function tijdschrift(string $titel, array $overschrijf = []): Publicatie
    {
        return Publicatie::create(array_merge([
            'soort_id' => Publicatiesoort::metCode('tijdschrift')->id,
            'titel' => $titel,
        ], $overschrijf));
    }

    public function test_een_plankregel_wordt_voorgesteld_bij_het_gelijkende_tijdschrift(): void
    {
        // Uit de tijdschriftinhoud: mét uitgaven.
        $echt = $this->tijdschrift('The Muslim World');
        $echt->uitgaven()->create(['uitgavenummer' => 'Vol. 1', 'jaar' => 1970]);

        // Uit de boekenlijst: een plankregel met een exemplaar, andere schrijfwijze.
        $plank = $this->tijdschrift('The Moslim world', ['bron_rekcode' => 'C . 12']);
        $plank->exemplaren()->create(['serienummer' => 'C.12-1', 'status' => ExemplaarStatus::Beschikbaar]);

        $this->actingAs($this->bibliothecaris())
            ->get(route('bibliotheek.samenvoegen'))
            ->assertOk()
            ->assertSee('The Moslim world')
            ->assertSee('The Muslim World')
            ->assertSee('C . 12');
    }

    public function test_samenvoegen_verhuist_exemplaren_en_rekcode_en_gooit_niets_weg(): void
    {
        $echt = $this->tijdschrift('The Muslim World');
        $echt->uitgaven()->create(['uitgavenummer' => 'Vol. 1', 'jaar' => 1970]);

        $plank = $this->tijdschrift('The Moslim world', [
            'bron_rekcode' => 'C . 12',
            'opmerking' => 'Losse jaargang op de plank.',
        ]);
        $plank->exemplaren()->create(['serienummer' => 'C.12-1', 'status' => ExemplaarStatus::Beschikbaar]);

        $this->actingAs($this->bibliothecaris())
            ->post(route('bibliotheek.samenvoegen.uitvoeren'), [
                'paren' => [$plank->id.':'.$echt->id],
            ])->assertRedirect();

        // De plankregel is verdwenen...
        $this->assertNull(Publicatie::find($plank->id));

        // ...maar alles wat eraan hing, hangt nu aan het echte tijdschrift.
        $echt->refresh();
        $this->assertSame(1, $echt->exemplaren()->count());
        $this->assertSame('C.12-1', $echt->exemplaren->first()->serienummer);
        $this->assertSame('C . 12', $echt->bron_rekcode);
        $this->assertStringContainsString('Losse jaargang op de plank', $echt->opmerking);

        // En de uitgave met haar artikelen staat er nog.
        $this->assertSame(1, $echt->uitgaven()->count());

        // De samenvoeging is gelogd (wie, wat, welke titel is opgenomen).
        $this->assertDatabaseHas('audit_logs', ['veld' => 'tijdschrift_samengevoegd']);
    }

    public function test_een_tijdschrift_met_uitgaven_wordt_nooit_opgeslokt(): void
    {
        // Beide hebben uitgaven: dan zouden er artikelen kunnen verdwijnen.
        $een = $this->tijdschrift('Islamic Studies');
        $een->uitgaven()->create(['uitgavenummer' => 'Vol. 1', 'jaar' => 1962]);

        $twee = $this->tijdschrift('Islamic Studies');
        $twee->uitgaven()->create(['uitgavenummer' => 'Vol. 2', 'jaar' => 1963]);

        $this->actingAs($this->bibliothecaris())
            ->post(route('bibliotheek.samenvoegen.uitvoeren'), [
                'paren' => [$een->id.':'.$twee->id],
            ])->assertRedirect();

        // Allebei staan er nog: het veiligheidsslot heeft het geweigerd.
        $this->assertNotNull(Publicatie::find($een->id));
        $this->assertNotNull(Publicatie::find($twee->id));
        $this->assertSame(1, $een->uitgaven()->count());
    }

    public function test_titels_die_te_weinig_lijken_worden_niet_voorgesteld(): void
    {
        $echt = $this->tijdschrift('Islamic Studies');
        $echt->uitgaven()->create(['uitgavenummer' => 'Vol. 1', 'jaar' => 1962]);

        $this->tijdschrift('Journal of Astronomy');

        $this->actingAs($this->bibliothecaris())
            ->get(route('bibliotheek.samenvoegen'))
            ->assertOk()
            ->assertDontSee('Journal of Astronomy');
    }

    public function test_alleen_de_bibliotheek_mag_samenvoegen(): void
    {
        $maak = fn (Rol $rol) => User::create(['naam' => 'T', 'email' => $rol->value.'@iuasr.test', 'rol' => $rol]);

        $this->actingAs($maak(Rol::Beheerder))->get(route('bibliotheek.samenvoegen'))->assertOk();
        $this->actingAs($maak(Rol::Bestuur))->get(route('bibliotheek.samenvoegen'))->assertForbidden();
        $this->actingAs($maak(Rol::Docent))->get(route('bibliotheek.samenvoegen'))->assertForbidden();
    }
}
