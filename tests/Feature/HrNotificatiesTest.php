<?php

namespace Tests\Feature;

use App\Mail\VerjaardagFelicitatie;
use App\Mail\VerlofStartMelding;
use App\Models\HrNotificatie;
use App\Models\Medewerker;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\HrSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Dagelijkse automatische HR-e-mails: verjaardagsfelicitaties en meldingen van
 * startend wettelijk verlof, met CC naar Personeelszaken en idempotentie.
 */
class HrNotificatiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class, HrSeeder::class]);
    }

    public function test_jarige_medewerker_krijgt_felicitatie_met_cc_personeelszaken(): void
    {
        Mail::fake();

        $m = Medewerker::where('personeelsnummer', 'P260006')->firstOrFail();
        $m->update([
            'geboortedatum' => Carbon::create(1990, now()->month, now()->day),
            'email' => 'johan.bakker@iuasr.nl',
        ]);

        $this->artisan('hr:notificaties')->assertSuccessful();

        Mail::assertSent(VerjaardagFelicitatie::class, fn ($mail) => $mail->hasTo('johan.bakker@iuasr.nl')
            && $mail->hasCc('personeelszaken@iuasr.nl'));
    }

    public function test_felicitatie_is_idempotent(): void
    {
        Mail::fake();

        $m = Medewerker::where('personeelsnummer', 'P260006')->firstOrFail();
        $m->update(['geboortedatum' => Carbon::create(1990, now()->month, now()->day), 'email' => 'johan.bakker@iuasr.nl']);

        $this->artisan('hr:notificaties')->assertSuccessful();
        $this->artisan('hr:notificaties')->assertSuccessful(); // tweede run zelfde dag

        // Slechts één verzendregel → geen dubbele felicitatie.
        $this->assertSame(1, HrNotificatie::where('type', 'verjaardag')->where('medewerker_id', $m->id)->count());
    }

    public function test_dashboard_toont_aankomende_verjaardag(): void
    {
        $m = Medewerker::where('personeelsnummer', 'P260006')->firstOrFail();
        $m->update(['geboortedatum' => Carbon::create(1990, now()->month, now()->day)]); // vandaag jarig

        $hr = \App\Models\User::where('email', 'n.aslan@iuasr.nl')->firstOrFail();
        $this->actingAs($hr)->get(route('hr.dashboard'))
            ->assertOk()
            ->assertSee('Verjaardagen')
            ->assertSee($m->volledigeNaam());
    }

    public function test_startend_wettelijk_verlof_meldt_personeelszaken(): void
    {
        Mail::fake();

        $nadia = Medewerker::where('personeelsnummer', 'P260002')->firstOrFail();
        $nadia->verlofaanvragen()->create([
            'verloftype' => 'zwangerschap',
            'van' => now()->toDateString(),
            'tot' => now()->addWeeks(16)->toDateString(),
            'uren' => 512,
            'status' => 'goedgekeurd',
            'aangevraagd_door_id' => $nadia->user_id,
        ]);

        $this->artisan('hr:notificaties')->assertSuccessful();

        Mail::assertSent(VerlofStartMelding::class, fn ($mail) => $mail->hasTo('personeelszaken@iuasr.nl'));
    }

    public function test_gewoon_verlof_dat_vandaag_start_geeft_geen_melding(): void
    {
        Mail::fake();

        $nadia = Medewerker::where('personeelsnummer', 'P260002')->firstOrFail();
        $nadia->verlofaanvragen()->create([
            'verloftype' => 'vakantie', // niet-wettelijk → geen UWV-melding
            'van' => now()->toDateString(),
            'tot' => now()->addDays(5)->toDateString(),
            'uren' => 40,
            'status' => 'goedgekeurd',
            'aangevraagd_door_id' => $nadia->user_id,
        ]);

        $this->artisan('hr:notificaties')->assertSuccessful();

        Mail::assertNotSent(VerlofStartMelding::class);
    }
}
