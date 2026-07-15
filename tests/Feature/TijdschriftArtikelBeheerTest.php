<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Bibliotheek\Artikel;
use App\Models\Bibliotheek\Publicatie;
use App\Models\Bibliotheek\Publicatiesoort;
use App\Models\Bibliotheek\Uitgave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Artikelen bij een tijdschriftuitgave: toevoegen, wijzigen en verwijderen.
 *
 * De catalogus zelf wordt nooit verwijderd (bezit), maar een artikel is een
 * inhoudsopgave-regel: een tikfout of een dubbel ingelezen regel moet je gewoon
 * kunnen weghalen. Elke mutatie wordt gelogd.
 */
class TijdschriftArtikelBeheerTest extends TestCase
{
    use RefreshDatabase;

    private function bibliothecaris(): User
    {
        return User::create(['naam' => 'Bieb', 'email' => 'bieb@iuasr.test', 'rol' => Rol::Bibliotheek]);
    }

    private function uitgave(): Uitgave
    {
        $tijdschrift = Publicatie::create([
            'soort_id' => Publicatiesoort::metCode('tijdschrift')->id,
            'titel' => 'Studia Islamica Rotterdam',
        ]);

        return $tijdschrift->uitgaven()->create(['uitgavenummer' => '2026/1', 'jaar' => 2026]);
    }

    public function test_een_artikel_toevoegen_aan_een_uitgave(): void
    {
        $uitgave = $this->uitgave();

        $this->actingAs($this->bibliothecaris())
            ->post(route('bibliotheek.artikelen.store', $uitgave), [
                'titel' => 'De rol van de moskee in de wijk',
                'auteurs' => ['Laila Haddad', 'Karim Belkacem'],
                'paginas' => '5-21',
                'trefwoorden' => 'moskee, wijk',
                'beschrijving' => 'Korte samenvatting.',
            ])->assertRedirect();

        $artikel = Artikel::where('titel', 'De rol van de moskee in de wijk')->firstOrFail();

        $this->assertSame($uitgave->id, $artikel->uitgave_id);
        $this->assertSame('5-21', $artikel->paginas);
        $this->assertCount(2, $artikel->auteurs);
        $this->assertDatabaseHas('audit_logs', ['veld' => 'tijdschriftartikel', 'actie' => 'aanmaak']);
    }

    public function test_een_artikel_wijzigen_inclusief_de_auteurs(): void
    {
        $uitgave = $this->uitgave();

        $artikel = $uitgave->artikelen()->create(['titel' => 'Verkeerde titel', 'paginas' => '1-2']);
        $artikel->auteurs()->sync(\App\Models\Bibliotheek\Auteur::idsVoorNamen(['Verkeerde Auteur']));

        $this->actingAs($this->bibliothecaris())
            ->put(route('bibliotheek.artikelen.update', $artikel), [
                'titel' => 'Juiste titel',
                'auteurs' => ['Juiste Auteur'],
                'paginas' => '10-25',
                'trefwoorden' => 'fiqh',
            ])->assertRedirect();

        $artikel->refresh()->load('auteurs');

        $this->assertSame('Juiste titel', $artikel->titel);
        $this->assertSame('10-25', $artikel->paginas);
        $this->assertSame('Juiste Auteur', $artikel->auteurs->first()->naam);
        $this->assertCount(1, $artikel->auteurs, 'De oude auteur is losgekoppeld.');

        $this->assertDatabaseHas('audit_logs', ['veld' => 'tijdschriftartikel', 'actie' => 'wijziging']);
    }

    public function test_een_artikel_verwijderen(): void
    {
        $uitgave = $this->uitgave();
        $artikel = $uitgave->artikelen()->create(['titel' => 'Dubbel ingelezen regel']);

        $this->actingAs($this->bibliothecaris())
            ->delete(route('bibliotheek.artikelen.destroy', $artikel))
            ->assertRedirect();

        $this->assertNull(Artikel::find($artikel->id));

        // De uitgave zelf blijft staan.
        $this->assertNotNull(Uitgave::find($uitgave->id));

        // En het is naspeurbaar wát er is verwijderd.
        $this->assertDatabaseHas('audit_logs', ['veld' => 'tijdschriftartikel', 'actie' => 'verwijdering']);
    }

    public function test_de_bewerkknoppen_staan_op_de_uitgavepagina(): void
    {
        $uitgave = $this->uitgave();
        $uitgave->artikelen()->create(['titel' => 'Een artikel', 'paginas' => '1-9']);

        $this->actingAs($this->bibliothecaris())
            ->get(route('bibliotheek.uitgaven.show', $uitgave))
            ->assertOk()
            ->assertSee('Artikel toevoegen')
            ->assertSee('Artikel bewerken of verwijderen');
    }

    public function test_artikel_toevoegen_vanaf_de_tijdschriftpagina_aan_een_bestaande_uitgave(): void
    {
        $uitgave = $this->uitgave();
        $tijdschrift = $uitgave->tijdschrift;

        $this->actingAs($this->bibliothecaris())
            ->post(route('bibliotheek.tijdschrift.artikel', $tijdschrift), [
                'uitgave_id' => $uitgave->id,
                'titel' => 'Een nieuw artikel',
                'auteurs' => ['Yusuf Demir'],
                'paginas' => '3-19',
            ])->assertRedirect(route('bibliotheek.publicaties.show', $tijdschrift));

        $artikel = Artikel::where('titel', 'Een nieuw artikel')->firstOrFail();
        $this->assertSame($uitgave->id, $artikel->uitgave_id);
        $this->assertSame('Yusuf Demir', $artikel->auteurs->first()->naam);
    }

    public function test_artikel_toevoegen_vanaf_de_tijdschriftpagina_maakt_de_uitgave_meteen_aan(): void
    {
        // Nog geen uitgaven: de medewerker voert een nieuw uitgavenummer in.
        $tijdschrift = Publicatie::create([
            'soort_id' => Publicatiesoort::metCode('tijdschrift')->id,
            'titel' => 'Nieuw Tijdschrift',
        ]);

        $this->actingAs($this->bibliothecaris())
            ->post(route('bibliotheek.tijdschrift.artikel', $tijdschrift), [
                'uitgave_id' => null,
                'nieuw_uitgavenummer' => '2026/1',
                'nieuw_jaar' => 2026,
                'titel' => 'Eerste artikel ooit',
                'paginas' => '1-10',
            ])->assertRedirect();

        $uitgave = Uitgave::where('publicatie_id', $tijdschrift->id)->firstOrFail();
        $this->assertSame('2026/1', $uitgave->uitgavenummer);
        $this->assertSame(2026, $uitgave->jaar);
        $this->assertSame(1, $uitgave->artikelen()->count());
    }

    public function test_zonder_uitgavekeuze_gebeurt_er_niets(): void
    {
        $tijdschrift = Publicatie::create([
            'soort_id' => Publicatiesoort::metCode('tijdschrift')->id,
            'titel' => 'Nieuw Tijdschrift',
        ]);

        $this->actingAs($this->bibliothecaris())
            ->post(route('bibliotheek.tijdschrift.artikel', $tijdschrift), [
                'uitgave_id' => null,
                'nieuw_uitgavenummer' => '',
                'titel' => 'Een artikel zonder uitgave',
            ])->assertRedirect();

        // Geen uitgave gekozen én geen nieuw nummer: melding, geen artikel.
        $this->assertSame(0, Artikel::count());
    }

    public function test_bij_het_aanmaken_van_een_tijdschrift_meteen_artikelen_toevoegen(): void
    {
        $this->actingAs($this->bibliothecaris())
            ->post(route('bibliotheek.publicaties.store'), [
                'soort_id' => Publicatiesoort::metCode('tijdschrift')->id,
                'titel' => 'Studia Nova',
                'eerste_uitgavenummer' => '2026/1',
                'eerste_jaar' => 2026,
                'artikelen' => [
                    ['titel' => 'Openingsartikel', 'auteur' => 'A. Auteur', 'paginas' => '1-12'],
                    ['titel' => 'Tweede artikel', 'auteur' => '', 'paginas' => '13-20'],
                    ['titel' => '', 'auteur' => '', 'paginas' => ''],   // lege regel: overslaan
                ],
            ])->assertRedirect();

        $tijdschrift = Publicatie::where('titel', 'Studia Nova')->firstOrFail();
        $uitgave = $tijdschrift->uitgaven()->firstOrFail();

        $this->assertSame('2026/1', $uitgave->uitgavenummer);
        $this->assertSame(2, $uitgave->artikelen()->count(), 'De lege regel is overgeslagen.');
        $this->assertSame('A. Auteur', Artikel::where('titel', 'Openingsartikel')->firstOrFail()->auteurs->first()->naam);
    }

    public function test_wie_de_bibliotheek_niet_beheert_kan_geen_artikelen_muteren(): void
    {
        $uitgave = $this->uitgave();
        $artikel = $uitgave->artikelen()->create(['titel' => 'Een artikel']);

        // Het Schoolbestuur leest mee, maar muteert niet.
        $bestuur = User::create(['naam' => 'B', 'email' => 'bestuur@iuasr.test', 'rol' => Rol::Bestuur]);

        $this->actingAs($bestuur)->post(route('bibliotheek.artikelen.store', $uitgave), ['titel' => 'Nieuw'])->assertForbidden();
        $this->actingAs($bestuur)->put(route('bibliotheek.artikelen.update', $artikel), ['titel' => 'Anders'])->assertForbidden();
        $this->actingAs($bestuur)->delete(route('bibliotheek.artikelen.destroy', $artikel))->assertForbidden();

        $this->assertSame('Een artikel', $artikel->fresh()->titel);
    }
}
