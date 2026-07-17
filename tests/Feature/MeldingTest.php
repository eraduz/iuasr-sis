<?php

namespace Tests\Feature;

use App\Enums\Meldingniveau;
use App\Enums\Rol;
use App\Models\Melding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Borgt de systeemmeldingen: de balk verschijnt en verdwijnt op de klok (zonder
 * achtergrondtaak), staat op elke pagina, en alleen de Beheerder plaatst hem.
 */
class MeldingTest extends TestCase
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

    private function melding(array $attributen = []): Melding
    {
        return Melding::create(array_merge([
            'niveau' => Meldingniveau::Waarschuwing,
            'titel' => 'Gepland onderhoud',
            'tekst' => 'Vandaag is het systeem vanaf 18.00 uur niet beschikbaar.',
            'van' => now()->subMinute(),
            'tot' => now()->addDay(),
            'afsluitbaar' => true,
        ], $attributen));
    }

    // --- Het venster ---

    public function test_melding_staat_bovenaan_elke_pagina(): void
    {
        $this->melding();

        // Niet alleen het dashboard: de balk hoort op ELKE pagina van elke module.
        foreach ([route('dashboard'), route('taken')] as $url) {
            $this->actingAs($this->gebruiker(Rol::Studentenzaken))
                ->get($url)->assertOk()->assertSee('Gepland onderhoud');
        }
    }

    public function test_melding_verdwijnt_vanzelf_na_tot(): void
    {
        // De kern van het ontwerp: geen cron, geen status-kolom. Een dag later is
        // de melding weg puur omdat de klok verder staat.
        $this->melding(['tot' => now()->addDay()]);
        $gebruiker = $this->gebruiker(Rol::Docent);

        $this->actingAs($gebruiker)->get(route('dashboard'))->assertSee('Gepland onderhoud');

        Carbon::setTestNow(now()->addDay()->addMinute());
        $this->actingAs($gebruiker)->get(route('dashboard'))->assertDontSee('Gepland onderhoud');
        Carbon::setTestNow();
    }

    public function test_melding_verschijnt_pas_vanaf_van(): void
    {
        $this->melding(['van' => now()->addHours(3), 'tot' => now()->addHours(6)]);
        $gebruiker = $this->gebruiker(Rol::Docent);

        $this->actingAs($gebruiker)->get(route('dashboard'))->assertDontSee('Gepland onderhoud');

        Carbon::setTestNow(now()->addHours(4));
        $this->actingAs($gebruiker)->get(route('dashboard'))->assertSee('Gepland onderhoud');
        Carbon::setTestNow();
    }

    public function test_standaardduur_is_een_dag(): void
    {
        $this->assertSame(24, (int) config('sis.melding.standaard_duur_uren'));
    }

    // --- Doelgroep ---

    public function test_zonder_rollen_ziet_iedereen_de_melding(): void
    {
        $this->melding(['rollen' => null]);

        foreach ([Rol::Studentenzaken, Rol::Docent, Rol::Beheerder] as $rol) {
            $this->actingAs($this->gebruiker($rol))->get(route('dashboard'))->assertSee('Gepland onderhoud');
        }
    }

    public function test_melding_voor_een_rol_bereikt_alleen_die_rol(): void
    {
        $this->melding(['titel' => 'Alleen studentenzaken', 'rollen' => [Rol::Studentenzaken->value]]);

        $this->actingAs($this->gebruiker(Rol::Studentenzaken))->get(route('dashboard'))->assertSee('Alleen studentenzaken');
        $this->actingAs($this->gebruiker(Rol::Docent))->get(route('dashboard'))->assertDontSee('Alleen studentenzaken');
    }

    public function test_gast_ziet_geen_melding(): void
    {
        $this->melding();
        $this->get(route('login'))->assertOk()->assertDontSee('Gepland onderhoud');
    }

    // --- Beheer ---

    public function test_alleen_beheerder_kan_meldingen_beheren(): void
    {
        $this->actingAs($this->gebruiker(Rol::Studentenzaken))->get(route('meldingen'))->assertForbidden();
        $this->actingAs($this->gebruiker())->get(route('meldingen'))->assertOk();
    }

    public function test_melding_plaatsen_en_gelogd(): void
    {
        $this->actingAs($this->gebruiker())->post(route('meldingen.store'), [
            'niveau' => Meldingniveau::Urgent->value,
            'titel' => 'Storing',
            'tekst' => 'Het systeem is tijdelijk traag.',
            'van' => now()->format('Y-m-d\TH:i'),
            'tot' => now()->addDay()->format('Y-m-d\TH:i'),
            'afsluitbaar' => 0,
        ])->assertRedirect(route('meldingen'));

        $melding = Melding::where('titel', 'Storing')->firstOrFail();
        $this->assertTrue($melding->isLopend());
        $this->assertFalse($melding->afsluitbaar);
        $this->assertDatabaseHas('audit_logs', ['actie' => 'aanmaak', 'veld' => 'melding']);
    }

    public function test_tot_moet_na_van_liggen(): void
    {
        // Anders zou de melding nooit verschijnen en zou de beheerder denken dat
        // het systeem stuk is.
        $this->actingAs($this->gebruiker())->post(route('meldingen.store'), [
            'niveau' => Meldingniveau::Info->value,
            'titel' => 'Onmogelijk',
            'tekst' => 'Test',
            'van' => now()->addDay()->format('Y-m-d\TH:i'),
            'tot' => now()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('tot');

        $this->assertSame(0, Melding::count());
    }

    public function test_intrekken_haalt_de_melding_direct_weg(): void
    {
        $m = $this->melding();
        $beheerder = $this->gebruiker();

        $this->actingAs($beheerder)->put(route('meldingen.intrekken', $m));

        $this->assertFalse($m->fresh()->isLopend());

        // De sessie leegmaken: de bevestiging na het intrekken bevat de titel
        // zelf ("... is van de schermen gehaald"), en die zou hieronder als
        // valse treffer meetellen.
        $this->flushSession();
        $this->actingAs($this->gebruiker(Rol::Docent))
            ->get(route('dashboard'))
            ->assertDontSee('sis-melding"', false);
    }

    public function test_niet_afsluitbare_melding_heeft_geen_sluitknop(): void
    {
        $this->melding(['niveau' => Meldingniveau::Urgent, 'afsluitbaar' => false]);

        // Op de klasse zelf toetsen kan niet: die staat ook in het script dat het
        // wegklikken regelt. Het aria-label staat alleen op de knop.
        $this->actingAs($this->gebruiker(Rol::Docent))
            ->get(route('dashboard'))
            ->assertSee('Gepland onderhoud')
            ->assertDontSee('Melding sluiten');
    }

    public function test_afsluitbare_melding_heeft_wel_een_sluitknop(): void
    {
        $this->melding(['afsluitbaar' => true]);

        $this->actingAs($this->gebruiker(Rol::Docent))
            ->get(route('dashboard'))
            ->assertSee('Melding sluiten');
    }

    public function test_gewijzigde_melding_krijgt_een_nieuwe_sluitsleutel(): void
    {
        // Anders zou een correctie ("toch pas 20:00") ongezien blijven bij precies
        // de mensen die de eerste versie al hadden weggeklikt.
        $m = $this->melding();
        $oud = $m->sluitSleutel();

        Carbon::setTestNow(now()->addMinutes(5));
        $m->update(['tekst' => 'Het onderhoud begint toch pas om 20.00 uur.']);
        Carbon::setTestNow();

        $this->assertNotSame($oud, $m->fresh()->sluitSleutel());
    }

    // --- Opruimen ---

    public function test_opruimcommando_laat_recente_meldingen_staan(): void
    {
        $this->melding(['titel' => 'Recent', 'van' => now()->subDays(2), 'tot' => now()->subDay()]);
        $this->melding(['titel' => 'Stokoud', 'van' => now()->subDays(60), 'tot' => now()->subDays(59)]);

        $this->artisan('sis:meldingen-opruimen')->assertSuccessful();

        $this->assertSame(1, Melding::where('titel', 'Recent')->count());
        $this->assertSame(0, Melding::where('titel', 'Stokoud')->count());
    }

    public function test_opruimcommando_proefdraait_zonder_te_verwijderen(): void
    {
        $this->melding(['titel' => 'Stokoud', 'van' => now()->subDays(60), 'tot' => now()->subDays(59)]);

        $this->artisan('sis:meldingen-opruimen --proef')->assertSuccessful();

        $this->assertSame(1, Melding::where('titel', 'Stokoud')->count());
    }
}
