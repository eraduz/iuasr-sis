<?php

namespace Tests\Feature;

use App\Enums\BalieRichting;
use App\Enums\BalieSoort;
use App\Enums\Rol;
use App\Models\BalieRegistratie;
use App\Models\Medewerker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module Balie/Receptie. Bewaakt de rolscheiding (Balie registreert; Directie en
 * Bestuur lezen mee; andere modules blijven buiten) en de logica die het
 * datamodel bij elkaar houdt: een bezoek is altijd inkomend, post kent geen
 * onderwerp, en alleen een bezoek kent een vertrekmoment.
 */
class BalieModuleTest extends TestCase
{
    use RefreshDatabase;

    private function gebruiker(Rol $rol): User
    {
        return User::create([
            'naam' => 'Test '.$rol->value,
            'email' => $rol->value.'@iuasr.test',
            'rol' => $rol,
        ]);
    }

    private function registratie(array $overschrijf = []): BalieRegistratie
    {
        return BalieRegistratie::create(array_merge([
            'soort' => BalieSoort::Telefoon,
            'richting' => BalieRichting::Inkomend,
            'datum_tijd' => now(),
            'onderwerp' => 'Vraag over inschrijving',
            'contact_naam' => 'Yusuf Demir',
            'toelichting' => 'Teruggebeld verzocht.',
        ], $overschrijf));
    }

    /** @return array<string,mixed> */
    private function formulier(array $overschrijf = []): array
    {
        return array_merge([
            'soort' => 'telefoon',
            'richting' => 'inkomend',
            'datum_tijd' => now()->format('Y-m-d\TH:i'),
            'onderwerp' => 'Vraag over inschrijving',
            'contact_naam' => 'Yusuf Demir',
            'toelichting' => 'Teruggebeld verzocht.',
        ], $overschrijf);
    }

    public function test_balie_ziet_het_logboek_en_kan_registreren(): void
    {
        $balie = $this->gebruiker(Rol::Balie);

        $this->actingAs($balie)->get(route('balie.dashboard'))->assertOk();
        $this->actingAs($balie)->get(route('balie'))->assertOk();
        $this->actingAs($balie)->get(route('balie.create'))->assertOk();

        $this->actingAs($balie)
            ->post(route('balie.store'), $this->formulier())
            ->assertRedirect(route('balie'));

        $this->assertDatabaseHas('balie_registraties', [
            'contact_naam' => 'Yusuf Demir',
            'soort' => 'telefoon',
            'richting' => 'inkomend',
            'geregistreerd_door_user_id' => $balie->id,
        ]);
    }

    public function test_de_registratie_wordt_gelogd(): void
    {
        $balie = $this->gebruiker(Rol::Balie);

        $this->actingAs($balie)->post(route('balie.store'), $this->formulier());

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $balie->id,
            'actie' => 'aanmaak',
            'onderwerp_type' => 'BalieRegistratie',
            'veld' => 'balie_registratie',
        ]);
    }

    public function test_schoolbestuur_leest_mee_maar_muteert_niet(): void
    {
        $registratie = $this->registratie();
        $bestuur = $this->gebruiker(Rol::Bestuur);

        $this->actingAs($bestuur)->get(route('balie'))->assertOk();
        $this->actingAs($bestuur)->get(route('balie.export'))->assertOk();

        $this->actingAs($bestuur)->get(route('balie.create'))->assertForbidden();
        $this->actingAs($bestuur)->get(route('balie.edit', $registratie))->assertForbidden();
        $this->actingAs($bestuur)->post(route('balie.store'), $this->formulier())->assertForbidden();

        $this->assertSame(1, BalieRegistratie::count());
    }

    public function test_andere_rollen_hebben_geen_toegang_tot_de_balie(): void
    {
        // Directie hoort hier uitdrukkelijk bij (keuze opdrachtgever 2026-07-13):
        // het balielogboek is een werkregister van de balie, geen opleidingsinformatie.
        foreach ([Rol::Directie, Rol::Studentenzaken, Rol::Docent, Rol::Hrmedewerker, Rol::Financien] as $rol) {
            $gebruiker = $this->gebruiker($rol);

            $this->actingAs($gebruiker)->get(route('balie.dashboard'))->assertForbidden();
            $this->actingAs($gebruiker)->get(route('balie'))->assertForbidden();
            $this->actingAs($gebruiker)->get(route('balie.export'))->assertForbidden();
            $this->assertFalse($gebruiker->magBalieInzien());
        }
    }

    public function test_balie_ziet_geen_studenten_of_cijfers(): void
    {
        $balie = $this->gebruiker(Rol::Balie);

        // De baliemedewerker is bewust een smalle rol: geen studentdossiers, geen
        // cijfers, geen personeelsdossiers.
        $this->actingAs($balie)->get('/studenten')->assertForbidden();
        $this->assertFalse($balie->magCijfersInzien());
        $this->assertFalse($balie->magInschrijvingBeheren());
        $this->assertFalse($balie->magHrInzien());
    }

    public function test_een_bezoek_is_altijd_inkomend_en_kent_een_vertrekmoment(): void
    {
        $balie = $this->gebruiker(Rol::Balie);

        // Ook als 'uitgaand' wordt meegestuurd, dwingt de server 'inkomend' af.
        $this->actingAs($balie)->post(route('balie.store'), $this->formulier([
            'soort' => 'bezoek',
            'richting' => 'uitgaand',
            'onderwerp' => 'Sollicitatiegesprek',
            'contact_naam' => 'Hakan Yilmaz',
        ]))->assertRedirect(route('balie'));

        $bezoek = BalieRegistratie::where('contact_naam', 'Hakan Yilmaz')->firstOrFail();
        $this->assertSame(BalieRichting::Inkomend, $bezoek->richting);
        $this->assertTrue($bezoek->isNogAanwezig());

        // Afmelden legt het vertrekmoment vast.
        $this->actingAs($balie)->post(route('balie.vertrek', $bezoek))->assertRedirect();
        $this->assertNotNull($bezoek->fresh()->vertrokken_op);
        $this->assertFalse($bezoek->fresh()->isNogAanwezig());

        // Een tweede keer afmelden is een nette melding, geen dubbele mutatie.
        $this->actingAs($balie)->post(route('balie.vertrek', $bezoek))->assertSessionHas('fout');
    }

    public function test_bij_post_wordt_geen_onderwerp_vastgelegd_en_bij_telefoon_wel(): void
    {
        $balie = $this->gebruiker(Rol::Balie);

        // Post: onderwerp is niet verplicht en wordt niet bewaard.
        $this->actingAs($balie)->post(route('balie.store'), $this->formulier([
            'soort' => 'post',
            'richting' => 'inkomend',
            'onderwerp' => 'Wordt genegeerd',
            'contact_naam' => 'DUO',
        ]))->assertRedirect(route('balie'));

        $this->assertNull(BalieRegistratie::where('contact_naam', 'DUO')->firstOrFail()->onderwerp);

        // Telefoon: onderwerp is verplicht.
        $this->actingAs($balie)->post(route('balie.store'), $this->formulier([
            'onderwerp' => '',
            'contact_naam' => 'Nour Haddad',
        ]))->assertSessionHasErrors('onderwerp');

        $this->assertDatabaseMissing('balie_registraties', ['contact_naam' => 'Nour Haddad']);
    }

    public function test_alleen_een_bezoek_houdt_een_vertrekmoment_over(): void
    {
        $balie = $this->gebruiker(Rol::Balie);

        // Een vertrektijd bij een telefoongesprek is betekenisloos en wordt gewist.
        $this->actingAs($balie)->post(route('balie.store'), $this->formulier([
            'contact_naam' => 'Sami Bouzid',
            'vertrokken_op' => now()->addHour()->format('Y-m-d\TH:i'),
        ]))->assertRedirect(route('balie'));

        $this->assertNull(BalieRegistratie::where('contact_naam', 'Sami Bouzid')->firstOrFail()->vertrokken_op);
    }

    public function test_het_logboek_is_doorzoekbaar_en_filterbaar(): void
    {
        $balie = $this->gebruiker(Rol::Balie);

        $this->registratie(['onderwerp' => 'Vraag over collegegeld', 'contact_naam' => 'Nour Haddad']);
        $this->registratie([
            'soort' => BalieSoort::Post,
            'richting' => BalieRichting::Uitgaand,
            'onderwerp' => null,
            'contact_naam' => 'Inspectie van het Onderwijs',
        ]);

        // Vrij zoeken vindt op onderwerp.
        $this->actingAs($balie)->get(route('balie', ['q' => 'collegegeld']))
            ->assertOk()->assertSee('Nour Haddad')->assertDontSee('Inspectie van het Onderwijs');

        // Filteren op soort.
        $this->actingAs($balie)->get(route('balie', ['soort' => 'post']))
            ->assertOk()->assertSee('Inspectie van het Onderwijs')->assertDontSee('Nour Haddad');
    }

    public function test_de_export_levert_een_csv_en_wordt_gelogd(): void
    {
        $balie = $this->gebruiker(Rol::Balie);
        $this->registratie();

        $response = $this->actingAs($balie)->get(route('balie.export'));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Yusuf Demir', $response->streamedContent());

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $balie->id,
            'actie' => 'inzage',
            'veld' => 'balie_export',
        ]);
    }

    public function test_registratie_wordt_aan_een_medewerker_gekoppeld_met_afdeling_als_terugval(): void
    {
        $balie = $this->gebruiker(Rol::Balie);

        $medewerker = Medewerker::create([
            'personeelsnummer' => 'P260001',
            'voornaam' => 'Karima',
            'achternaam' => 'Nassar',
        ]);

        // Gekoppeld aan een medewerker: die naam is de bestemming.
        $this->actingAs($balie)->post(route('balie.store'), $this->formulier([
            'contact_naam' => 'Yusuf Demir',
            'medewerker_id' => $medewerker->id,
        ]))->assertRedirect(route('balie'));

        $gekoppeld = BalieRegistratie::where('contact_naam', 'Yusuf Demir')->firstOrFail();
        $this->assertSame($medewerker->id, $gekoppeld->medewerker_id);
        $this->assertSame('Karima Nassar', $gekoppeld->bestemdVoor());

        // Zonder medewerker valt het terug op de afdeling.
        $viaAfdeling = $this->registratie(['contact_naam' => 'Sami Bouzid', 'afdeling' => 'Studentenzaken']);
        $this->assertSame('Studentenzaken', $viaAfdeling->bestemdVoor());

        // Een onbekende medewerker wordt geweigerd (echte foreign key, geen tekst).
        $this->actingAs($balie)->post(route('balie.store'), $this->formulier([
            'contact_naam' => 'Onbekend',
            'medewerker_id' => 999999,
        ]))->assertSessionHasErrors('medewerker_id');
    }

    public function test_de_module_verschijnt_op_het_keuzescherm_van_de_balie(): void
    {
        $balie = $this->gebruiker(Rol::Balie);

        $this->actingAs($balie)->get(route('modules.kiezen'))
            ->assertOk()
            ->assertSee('Balie / Receptie');
    }
}
