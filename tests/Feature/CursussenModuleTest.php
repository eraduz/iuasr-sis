<?php

namespace Tests\Feature;

use App\Enums\CursusinschrijvingStatus;
use App\Enums\Rol;
use App\Models\Cursist;
use App\Models\Cursus;
use App\Models\Cursusinschrijving;
use App\Models\User;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase B — module Cursussen Administratie: cursusbeheer, cursisten (met
 * bulk-import) en inschrijvingen.
 */
class CursussenModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $cursusadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, GebruikerSeeder::class]);
        $this->cursusadmin = User::where('rol', Rol::Cursusadministratie)->firstOrFail();
    }

    public function test_de_drie_cursussen_zijn_geseed(): void
    {
        $this->assertSame(3, Cursus::count());
        $this->assertSame(265.0, (float) Cursus::where('code', 'ARAB-TAAL')->value('cursusgeld'));
        $this->assertSame(330.0, (float) Cursus::where('code', 'HIFZ')->value('cursusgeld'));
        $this->assertSame(430.0, (float) Cursus::where('code', 'IJAZA')->value('cursusgeld'));
    }

    public function test_cursussen_module_is_actief(): void
    {
        $this->assertTrue(\App\Models\Module::where('sleutel', 'cursussen')->value('actief'));
        $this->assertSame('cursussen.dashboard', \App\Models\Module::where('sleutel', 'cursussen')->first()->startRoute());
    }

    public function test_cursusadministratie_ziet_het_dashboard(): void
    {
        $this->actingAs($this->cursusadmin)->get(route('cursussen.dashboard'))
            ->assertOk()->assertSee('Cursussen Administratie')->assertSee('Arabische Taal');
    }

    public function test_cursusadministratie_landt_op_de_cursusmodule_niet_op_studentenzaken(): void
    {
        // '/' is niet voor deze rol; wordt naar de eigen module gestuurd.
        $this->actingAs($this->cursusadmin)->get('/')->assertRedirect(route('cursussen.dashboard'));
    }

    public function test_nieuwe_cursus_toevoegen(): void
    {
        // Cursussen aanmaken is voorbehouden aan de Beheerder (niet de cursusdirecteur).
        $beheer = User::where('rol', Rol::Beheerder)->firstOrFail();
        $this->actingAs($beheer)->post(route('cursussen.store'), [
            'code' => 'TAJWEED', 'naam' => 'Tajweed', 'cursusgeld' => '199.50', 'actief' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('cursussen.beheer'));

        $this->assertDatabaseHas('cursussen', ['code' => 'TAJWEED', 'cursusgeld' => 199.50]);
    }

    public function test_cursusdirecteur_mag_geen_cursus_aanmaken(): void
    {
        $this->actingAs($this->cursusadmin)->post(route('cursussen.store'), [
            'code' => 'TAJWEED', 'naam' => 'Tajweed', 'cursusgeld' => '199.50', 'actief' => '1',
        ])->assertForbidden();

        $this->assertDatabaseMissing('cursussen', ['code' => 'TAJWEED']);
    }

    public function test_handmatig_cursist_toevoegen_krijgt_een_cursistnummer(): void
    {
        $this->actingAs($this->cursusadmin)->post(route('cursisten.store'), [
            'voornaam' => 'Ahmet', 'achternaam' => 'Yılmaz', 'email' => 'a@example.com',
        ])->assertSessionHasNoErrors();

        $cursist = Cursist::first();
        $this->assertMatchesRegularExpression('/^C\d{6}$/', $cursist->cursistnummer);
    }

    public function test_cursist_direct_inschrijven_neemt_het_cursusgeld_over(): void
    {
        // Arabische Taal is de cursus van de standaard-cursusdirecteur (Hafsa).
        $cursus = Cursus::where('code', 'ARAB-TAAL')->firstOrFail();

        $this->actingAs($this->cursusadmin)->post(route('cursisten.store'), [
            'voornaam' => 'Sara', 'achternaam' => 'El Amrani', 'cursus_id' => $cursus->id,
        ])->assertSessionHasNoErrors();

        $inschrijving = Cursusinschrijving::first();
        $this->assertSame(265.0, (float) $inschrijving->totaalbedrag);
        $this->assertSame(CursusinschrijvingStatus::Actief, $inschrijving->status);
    }

    public function test_niet_dubbel_inschrijven_op_dezelfde_cursus(): void
    {
        $cursus = Cursus::where('code', 'ARAB-TAAL')->firstOrFail();
        $cursist = Cursist::create(['cursistnummer' => 'C260001', 'voornaam' => 'T', 'achternaam' => 'Test']);

        $this->actingAs($this->cursusadmin)->post(route('cursisten.inschrijven', $cursist), ['cursus_id' => $cursus->id]);
        $this->actingAs($this->cursusadmin)->post(route('cursisten.inschrijven', $cursist), ['cursus_id' => $cursus->id]);

        $this->assertSame(1, $cursist->inschrijvingen()->count());
    }

    public function test_inschrijving_status_bijwerken(): void
    {
        $cursus = Cursus::first();
        $cursist = Cursist::create(['cursistnummer' => 'C260002', 'voornaam' => 'T', 'achternaam' => 'Test']);
        $inschrijving = $cursist->inschrijvingen()->create([
            'cursus_id' => $cursus->id, 'inschrijfdatum' => now(),
            'status' => CursusinschrijvingStatus::Actief, 'totaalbedrag' => $cursus->cursusgeld,
        ]);

        $this->actingAs($this->cursusadmin)
            ->put(route('cursisten.inschrijving.update', [$cursist, $inschrijving]), ['status' => 'afgerond'])
            ->assertRedirect();

        $this->assertSame(CursusinschrijvingStatus::Afgerond, $inschrijving->fresh()->status);
    }

    public function test_bulk_import_csv_met_directe_inschrijving(): void
    {
        $csv = "voornaam;tussenvoegsel;achternaam;geboortedatum;email;telefoon;adres;postcode;woonplaats;cursuscode\r\n"
            ."Yusuf;;Demir;01-01-2000;y@example.com;0612345678;Straat 1;3011AB;Rotterdam;ARAB-TAAL\r\n"
            ."Fatima;el;Idrissi;;f@example.com;;;;;ONBEKEND\r\n"; // onbekende cursuscode -> overslaan

        $this->actingAs($this->cursusadmin)->post(route('cursisten.import.controle'), [
            'bestand' => UploadedFile::fake()->createWithContent('cursisten.csv', $csv),
        ])->assertOk()->assertSee('Yusuf Demir')->assertSessionHas('cursist_import');

        $this->assertSame(0, Cursist::count()); // nog niets opgeslagen

        $this->actingAs($this->cursusadmin)->post(route('cursisten.import'))->assertRedirect(route('cursisten'));

        $this->assertSame(1, Cursist::count());               // alleen de geldige rij
        $this->assertSame(1, Cursusinschrijving::count());     // ingeschreven op Arabische Taal
        $this->assertSame(265.0, (float) Cursusinschrijving::first()->totaalbedrag);
    }

    public function test_studentenzaken_heeft_geen_toegang_tot_de_cursusmodule(): void
    {
        $sz = User::where('rol', Rol::Studentenzaken)->first();
        $this->actingAs($sz)->get(route('cursussen.dashboard'))->assertForbidden();
        $this->actingAs($sz)->get(route('cursisten'))->assertForbidden();
    }

    public function test_docent_heeft_geen_toegang(): void
    {
        $docent = User::where('rol', Rol::Docent)->first();
        $this->actingAs($docent)->get(route('cursussen.dashboard'))->assertForbidden();
    }

    public function test_beheerder_heeft_wel_toegang(): void
    {
        $beheer = User::where('rol', Rol::Beheerder)->first();
        $this->actingAs($beheer)->get(route('cursussen.dashboard'))->assertOk();
    }
}
