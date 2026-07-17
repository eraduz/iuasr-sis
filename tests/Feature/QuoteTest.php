<?php

namespace Tests\Feature;

use App\Enums\Quotesoort;
use App\Enums\Rol;
use App\Models\Quote;
use App\Models\User;
use App\Support\Quoteroulatie;
use Database\Seeders\QuoteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Borgt de zijbalk-quotes: de roulatie volgt de klok (iedereen ziet hetzelfde,
 * en de reeks loopt door ondanks paginawissels), en alleen de Beheerder beheert.
 */
class QuoteTest extends TestCase
{
    use RefreshDatabase;

    private function gebruiker(Rol $rol = Rol::Beheerder): User
    {
        return User::create([
            'naam' => 'Test '.$rol->value,
            'email' => $rol->value.'-'.uniqid().'@iuasr.test',
            'rol' => $rol,
            'actief' => true,
        ]);
    }

    private function quote(string $titel, int $volgorde, bool $actief = true): Quote
    {
        return Quote::create([
            'soort' => Quotesoort::SchoneNaam,
            'titel' => $titel,
            'arabisch' => 'الرحمن',
            'betekenis' => 'Betekenis van '.$titel,
            'volgorde' => $volgorde,
            'actief' => $actief,
        ]);
    }

    // --- Roulatie ---

    public function test_de_quote_volgt_de_klok_en_wisselt_per_tijdvak(): void
    {
        $this->quote('Een', 1);
        $this->quote('Twee', 2);
        $this->quote('Drie', 3);

        $interval = Quoteroulatie::intervalSeconden();

        // Drie opeenvolgende tijdvakken geven drie verschillende quotes, en na een
        // rondje begint de reeks weer bij dezelfde.
        $slot = Quoteroulatie::slot(0);
        $eerste = Quoteroulatie::voorSlot($slot)->titel;
        $tweede = Quoteroulatie::voorSlot($slot + 1)->titel;
        $derde = Quoteroulatie::voorSlot($slot + 2)->titel;

        $this->assertCount(3, array_unique([$eerste, $tweede, $derde]));
        $this->assertSame($eerste, Quoteroulatie::voorSlot($slot + 3)->titel);
        $this->assertSame($interval, 5 * 60);
    }

    public function test_binnen_hetzelfde_tijdvak_blijft_de_quote_staan(): void
    {
        // Dit is de kern: het systeem is server-gerenderd, dus bij elke
        // paginawissel wordt opnieuw gekozen. Binnen één tijdvak moet dat
        // dezelfde uitkomst geven, anders springt de quote bij elke klik.
        $this->quote('Een', 1);
        $this->quote('Twee', 2);

        $tijdvak = 1_000_000 * Quoteroulatie::intervalSeconden();

        $this->assertSame(
            Quoteroulatie::voorSlot(Quoteroulatie::slot($tijdvak))->titel,
            Quoteroulatie::voorSlot(Quoteroulatie::slot($tijdvak + 60))->titel
        );
    }

    public function test_inactieve_quotes_doen_niet_mee(): void
    {
        $this->quote('Zichtbaar', 1);
        $this->quote('Verborgen', 2, actief: false);

        $titels = Quoteroulatie::actieve()->pluck('titel')->all();

        $this->assertSame(['Zichtbaar'], $titels);
    }

    public function test_zonder_quotes_geen_fout(): void
    {
        // Deling door nul is hier de voor de hand liggende bug.
        $this->assertNull(Quoteroulatie::huidige());
        $this->actingAs($this->gebruiker(Rol::Studentenzaken))->get(route('dashboard'))->assertOk();
    }

    public function test_secondentotvolgende_ligt_binnen_het_interval(): void
    {
        $interval = Quoteroulatie::intervalSeconden();

        $this->assertSame($interval, Quoteroulatie::secondenTotVolgende($interval * 10));
        $this->assertSame(1, Quoteroulatie::secondenTotVolgende($interval * 10 - 1));
    }

    // --- Zijbalk ---

    public function test_de_quote_staat_in_de_zijbalk_voor_iedere_rol(): void
    {
        $this->quote('Ar-Rahman', 1);

        foreach ([Rol::Studentenzaken, Rol::Docent, Rol::Beheerder] as $rol) {
            $this->actingAs($this->gebruiker($rol))
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('sis-quote');
        }
    }

    public function test_huidig_endpoint_geeft_de_quote_van_dit_tijdvak(): void
    {
        $this->quote('Ar-Rahman', 1);

        $this->actingAs($this->gebruiker(Rol::Docent))
            ->getJson(route('quotes.huidig'))
            ->assertOk()
            ->assertJsonPath('quote.kop', 'Ar-Rahman')
            ->assertJsonStructure(['slot', 'volgende_over', 'quote' => ['kop', 'arabisch', 'betekenis']]);
    }

    public function test_gast_krijgt_geen_quote(): void
    {
        $this->quote('Ar-Rahman', 1);
        $this->getJson(route('quotes.huidig'))->assertUnauthorized();
    }

    // --- Beheer ---

    public function test_alleen_beheerder_kan_quotes_beheren(): void
    {
        $this->actingAs($this->gebruiker(Rol::Studentenzaken))->get(route('quotes'))->assertForbidden();
        $this->actingAs($this->gebruiker(Rol::Beheerder))->get(route('quotes'))->assertOk();
    }

    public function test_quote_toevoegen_met_afbeelding(): void
    {
        Storage::fake('local');

        $this->actingAs($this->gebruiker())->post(route('quotes.store'), [
            'soort' => Quotesoort::Quote->value,
            'titel' => 'Geduld',
            'betekenis' => 'Waarlijk, Allah is met de geduldigen.',
            'bron' => 'Soera Al-Baqara 2:153',
            'actief' => 1,
            'afbeelding' => UploadedFile::fake()->image('naam.png', 456, 456),
        ])->assertRedirect(route('quotes'));

        $quote = Quote::where('titel', 'Geduld')->firstOrFail();
        $this->assertTrue($quote->heeftAfbeelding());
        Storage::disk('local')->assertExists($quote->afbeelding_pad);
    }

    public function test_niet_afbeelding_wordt_geweigerd(): void
    {
        Storage::fake('local');

        // Een als plaatje vermomd bestand mag er niet in: de zijbalk toont dit
        // aan iedere medewerker.
        $this->actingAs($this->gebruiker())->post(route('quotes.store'), [
            'soort' => Quotesoort::Quote->value,
            'betekenis' => 'Test',
            'afbeelding' => UploadedFile::fake()->create('stiekem.php', 8, 'application/x-php'),
        ])->assertSessionHasErrors('afbeelding');

        $this->assertSame(0, Quote::count());
    }

    public function test_toggle_zet_de_quote_aan_en_uit(): void
    {
        $q = $this->quote('Ar-Rahman', 1);
        $beheerder = $this->gebruiker();

        $this->actingAs($beheerder)->put(route('quotes.toggle', $q));
        $this->assertFalse($q->fresh()->actief);

        $this->actingAs($beheerder)->put(route('quotes.toggle', $q));
        $this->assertTrue($q->fresh()->actief);
    }

    public function test_verwijderen_wist_ook_het_bestand_en_wordt_gelogd(): void
    {
        Storage::fake('local');
        $beheerder = $this->gebruiker();

        $this->actingAs($beheerder)->post(route('quotes.store'), [
            'soort' => Quotesoort::Quote->value,
            'titel' => 'Weg',
            'betekenis' => 'Verdwijnt',
            'actief' => 1,
            'afbeelding' => UploadedFile::fake()->image('weg.png', 456, 456),
        ]);

        $quote = Quote::where('titel', 'Weg')->firstOrFail();
        $pad = $quote->afbeelding_pad;

        $this->actingAs($beheerder)->delete(route('quotes.destroy', $quote));

        $this->assertSame(0, Quote::where('titel', 'Weg')->count());
        Storage::disk('local')->assertMissing($pad);
        $this->assertDatabaseHas('audit_logs', ['actie' => 'verwijdering', 'veld' => 'quote']);
    }

    public function test_seeder_laadt_99_schone_namen_en_is_idempotent(): void
    {
        $this->seed(QuoteSeeder::class);
        $this->assertSame(99, Quote::where('soort', Quotesoort::SchoneNaam)->count());

        // Opnieuw draaien mag niets dubbel toevoegen en niets overschrijven.
        Quote::where('titel', 'Ar-Rahman')->update(['betekenis' => 'Handmatig aangepast']);
        $this->seed(QuoteSeeder::class);

        $this->assertSame(99, Quote::where('soort', Quotesoort::SchoneNaam)->count());
        $this->assertSame('Handmatig aangepast', Quote::where('titel', 'Ar-Rahman')->value('betekenis'));
    }
}
