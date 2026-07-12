<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Nieuwsbericht;
use App\Models\Nieuwsbron;
use App\Models\User;
use App\Support\Nieuwsophaler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Onderwijsnieuws: ophalen (feed-parsing + whitelist), het commando en het
 * beheerscherm. Geen live internet — alle HTTP-calls worden gefaket.
 */
class NieuwsTest extends TestCase
{
    use RefreshDatabase;

    private const ATOM = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Nieuws</title>
  <entry>
    <title>Hogescholen positief over kabinetsplannen</title>
    <link rel="alternate" href="https://www.vereniginghogescholen.nl/actueel/artikel-1"/>
    <published>2026-07-10T13:55:14+02:00</published>
    <summary>Een korte samenvatting van het bericht.</summary>
  </entry>
  <entry>
    <title>Tweede bericht</title>
    <link rel="alternate" href="https://www.vereniginghogescholen.nl/actueel/artikel-2"/>
    <published>2026-06-15T09:00:00+02:00</published>
    <summary>Nog een samenvatting.</summary>
  </entry>
</feed>
XML;

    private function beheerder(): User
    {
        return User::create(['naam' => 'Beheer', 'email' => 'beheer@iuasr.test', 'rol' => Rol::Beheerder]);
    }

    private function atomBron(): Nieuwsbron
    {
        return Nieuwsbron::create([
            'naam' => 'Vereniging Hogescholen',
            'url' => 'https://www.vereniginghogescholen.nl/actueel/actualiteiten.atom',
            'type' => 'atom', 'actief' => true,
        ]);
    }

    public function test_atom_feed_wordt_geparsed(): void
    {
        Http::fake(['*' => Http::response(self::ATOM, 200)]);

        $items = (new Nieuwsophaler)->haalOp($this->atomBron());

        $this->assertCount(2, $items);
        $this->assertSame('Hogescholen positief over kabinetsplannen', $items[0]['titel']);
        $this->assertSame('https://www.vereniginghogescholen.nl/actueel/artikel-1', $items[0]['link']);
        $this->assertSame('2026-07-10', $items[0]['gepubliceerd_op']->format('Y-m-d'));
    }

    public function test_bron_buiten_de_whitelist_wordt_geweigerd(): void
    {
        Http::fake();
        $bron = Nieuwsbron::create(['naam' => 'Onbekend', 'url' => 'https://evil.example.com/feed.atom', 'type' => 'atom', 'actief' => true]);

        $this->expectException(\RuntimeException::class);
        (new Nieuwsophaler)->haalOp($bron);
        Http::assertNothingSent();
    }

    public function test_handmatige_bron_haalt_niets_op(): void
    {
        $bron = Nieuwsbron::create(['naam' => 'Onderwijsinspectie', 'url' => 'https://www.onderwijsinspectie.nl/actueel/nieuws', 'type' => 'handmatig', 'actief' => true]);
        $this->assertSame([], (new Nieuwsophaler)->haalOp($bron));
    }

    public function test_commando_slaat_berichten_lokaal_op(): void
    {
        Http::fake(['*' => Http::response(self::ATOM, 200)]);
        $bron = $this->atomBron();

        $this->artisan('nieuws:ophalen')->assertSuccessful();

        $this->assertSame(2, Nieuwsbericht::count());
        $this->assertDatabaseHas('nieuwsberichten', ['titel' => 'Tweede bericht']);
        // Idempotent: opnieuw draaien voegt niets toe.
        $this->artisan('nieuws:ophalen')->assertSuccessful();
        $this->assertSame(2, Nieuwsbericht::count());
        $this->assertNotNull($bron->fresh()->laatst_opgehaald_op);
    }

    public function test_beheer_kan_bron_beheren_en_bericht_toevoegen(): void
    {
        $beheer = $this->beheerder();
        $handmatig = Nieuwsbron::create(['naam' => 'Onderwijsinspectie', 'url' => 'https://www.onderwijsinspectie.nl/actueel/nieuws', 'type' => 'handmatig', 'actief' => true]);

        $this->actingAs($beheer)->get(route('nieuws'))->assertOk();

        $this->actingAs($beheer)->post(route('nieuws.bericht'), [
            'nieuwsbron_id' => $handmatig->id,
            'titel' => 'Belangrijk inspectiebericht',
            'link' => 'https://www.onderwijsinspectie.nl/actueel/nieuws/2026/07/10/iets',
            'gepubliceerd_op' => '2026-07-10',
        ])->assertRedirect(route('nieuws'));

        $this->assertDatabaseHas('nieuwsberichten', ['titel' => 'Belangrijk inspectiebericht']);

        $bericht = Nieuwsbericht::first();
        $this->actingAs($beheer)->delete(route('nieuws.bericht.verwijderen', $bericht))->assertRedirect(route('nieuws'));
        $this->assertSame(0, Nieuwsbericht::count());
    }

    public function test_studentenzaken_heeft_geen_toegang_tot_nieuwsbeheer(): void
    {
        $sz = User::create(['naam' => 'SZ', 'email' => 'sz@iuasr.test', 'rol' => Rol::Studentenzaken]);
        $this->actingAs($sz)->get(route('nieuws'))->assertForbidden();
    }
}
