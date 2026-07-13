<?php

namespace Tests\Feature;

use App\Enums\BibliotheekMailsoort;
use App\Enums\ExemplaarStatus;
use App\Enums\Materiaalstaat;
use App\Models\Bibliotheek\Publicatiesoort;
use App\Enums\Rol;
use App\Models\Bibliotheek\Auteur;
use App\Models\Bibliotheek\Emaillog;
use App\Models\Bibliotheek\Emailsjabloon;
use App\Models\Bibliotheek\Exemplaar;
use App\Models\Bibliotheek\Publicatie;
use App\Models\Bibliotheek\Taal;
use App\Models\Bibliotheek\Uitlening;
use App\Models\Medewerker;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Module Bibliotheek. Bewaakt de rolscheiding en de logica die het datamodel bij
 * elkaar houdt: titel en exemplaar zijn gescheiden, 'te laat' en 'op tijd' zijn
 * afleidingen, een uitgeleend exemplaar kan niet nogmaals worden uitgeleend, en
 * schade haalt een exemplaar uit de uitleen.
 */
class BibliotheekModuleTest extends TestCase
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

    private function publicatie(array $overschrijf = []): Publicatie
    {
        return Publicatie::create(array_merge([
            'soort_id' => Publicatiesoort::metCode('boek')->id,
            'titel' => 'Tafsir Ibn Kathir',
            'uitgavejaar' => 2018,
        ], $overschrijf));
    }

    private function exemplaar(?Publicatie $publicatie = null, string $serienummer = 'IUASR-001'): Exemplaar
    {
        return ($publicatie ?? $this->publicatie())->exemplaren()->create([
            'serienummer' => $serienummer,
            'status' => ExemplaarStatus::Beschikbaar,
        ]);
    }

    private function student(): Student
    {
        return Student::create([
            'studentnummer' => '261234',
            'voornaam' => 'Yusuf',
            'achternaam' => 'Demir',
            'email' => 'y.demir@student.iuasr.test',
        ]);
    }

    private function medewerker(): Medewerker
    {
        return Medewerker::create([
            'personeelsnummer' => 'P260001',
            'voornaam' => 'Karima',
            'achternaam' => 'Nassar',
            'email' => 'k.nassar@iuasr.test',
        ]);
    }

    /* ----------------------------------------------------------------
     | Rolscheiding
     |--------------------------------------------------------------- */

    public function test_bibliotheekmedewerker_beheert_de_catalogus(): void
    {
        $bieb = $this->gebruiker(Rol::Bibliotheek);

        $this->actingAs($bieb)->get(route('bibliotheek.dashboard'))->assertOk();
        $this->actingAs($bieb)->get(route('bibliotheek.publicaties'))->assertOk();
        $this->actingAs($bieb)->get(route('bibliotheek.publicaties.create'))->assertOk();

        $this->actingAs($bieb)->post(route('bibliotheek.publicaties.store'), [
            'soort_id' => Publicatiesoort::metCode('boek')->id,
            'titel' => 'صحيح البخاري',
            'auteurs' => ['Sahih al-Bukhari'],
            'exemplaren' => ['IUASR-HAD-001'],
        ])->assertRedirect();

        // De titel is in Arabisch schrift opgeslagen (Unicode).
        $publicatie = Publicatie::where('titel', 'صحيح البخاري')->firstOrFail();
        $this->assertSame(1, $publicatie->exemplaren()->count());
        $this->assertSame('Sahih al-Bukhari', $publicatie->auteurs->first()->naam);
    }

    public function test_schoolbestuur_leest_mee_maar_muteert_niet(): void
    {
        $publicatie = $this->publicatie();
        $bestuur = $this->gebruiker(Rol::Bestuur);

        $this->actingAs($bestuur)->get(route('bibliotheek.publicaties'))->assertOk();
        $this->actingAs($bestuur)->get(route('bibliotheek.rapport'))->assertOk();
        $this->actingAs($bestuur)->get(route('bibliotheek.export'))->assertOk();

        $this->actingAs($bestuur)->get(route('bibliotheek.publicaties.create'))->assertForbidden();
        $this->actingAs($bestuur)->get(route('bibliotheek.publicaties.edit', $publicatie))->assertForbidden();
        $this->actingAs($bestuur)->get(route('bibliotheek.uitlenen'))->assertForbidden();
    }

    public function test_andere_rollen_hebben_geen_toegang(): void
    {
        foreach ([Rol::Studentenzaken, Rol::Docent, Rol::Hrmedewerker, Rol::Directie, Rol::Balie] as $rol) {
            $gebruiker = $this->gebruiker($rol);

            $this->actingAs($gebruiker)->get(route('bibliotheek.dashboard'))->assertForbidden();
            $this->actingAs($gebruiker)->get(route('bibliotheek.publicaties'))->assertForbidden();
            $this->assertFalse($gebruiker->magBibliotheekInzien());
        }
    }

    public function test_bibliotheekmedewerker_ziet_geen_cijfers_of_personeelsdossiers(): void
    {
        $bieb = $this->gebruiker(Rol::Bibliotheek);

        $this->assertFalse($bieb->magCijfersInzien());
        $this->assertFalse($bieb->magInschrijvingBeheren());
        $this->assertFalse($bieb->magHrInzien());
        $this->actingAs($bieb)->get('/studenten')->assertForbidden();
    }

    public function test_alleen_de_beheerder_beheert_de_emailsjablonen(): void
    {
        $this->actingAs($this->gebruiker(Rol::Beheerder))->get(route('bibliotheek.sjablonen'))->assertOk();
        $this->actingAs($this->gebruiker(Rol::Bibliotheek))->get(route('bibliotheek.sjablonen'))->assertForbidden();
        $this->actingAs($this->gebruiker(Rol::Bestuur))->get(route('bibliotheek.sjablonen'))->assertForbidden();
    }

    /* ----------------------------------------------------------------
     | Catalogus: titel en exemplaar gescheiden
     |--------------------------------------------------------------- */

    public function test_een_titel_kan_meerdere_exemplaren_hebben(): void
    {
        $publicatie = $this->publicatie();
        $this->exemplaar($publicatie, 'IUASR-001');
        $this->exemplaar($publicatie, 'IUASR-002');
        $this->exemplaar($publicatie, 'IUASR-003');

        $publicatie->load('exemplaren');

        $this->assertSame(3, $publicatie->exemplaren->count());
        $this->assertSame(3, $publicatie->aantalBeschikbaar());
        $this->assertSame(1, Publicatie::count(), 'De titel staat maar één keer in de catalogus.');
    }

    public function test_een_publicatie_kan_meerdere_talen_hebben(): void
    {
        $publicatie = $this->publicatie();
        $publicatie->talen()->sync(Taal::whereIn('code', ['ar', 'nl'])->pluck('id'));

        $this->assertSame('Arabisch, Nederlands', $publicatie->fresh('talen')->talenTekst());
    }

    public function test_boekreeks_maakt_alle_delen_in_een_keer_aan(): void
    {
        $bieb = $this->gebruiker(Rol::Bibliotheek);

        $this->actingAs($bieb)->post(route('bibliotheek.reeksen.store'), [
            'titel' => 'Tafsir Ibn Kathir',
            'auteurs' => ['Ibn Kathir'],
            'talen' => [Taal::where('code', 'ar')->value('id')],
            'delen' => [
                ['deelnummer' => 1, 'serienummer' => 'TIK-001'],
                ['deelnummer' => 2, 'serienummer' => 'TIK-002'],
                ['deelnummer' => 3, 'serienummer' => 'TIK-003'],
                ['deelnummer' => 4, 'serienummer' => 'TIK-004'],
            ],
        ])->assertRedirect();

        $this->assertSame(4, Publicatie::whereNotNull('reeks_id')->count());
        $this->assertSame(4, Exemplaar::count());

        // Elk deel is los uitleenbaar en draagt de gedeelde auteur.
        $deel2 = Publicatie::where('deelnummer', 2)->firstOrFail();
        $this->assertSame('Tafsir Ibn Kathir — Deel 2', $deel2->volledigeTitel());
        $this->assertSame('Ibn Kathir', $deel2->auteurs->first()->naam);
    }

    public function test_een_tijdschrift_hoort_niet_in_een_boekreeks(): void
    {
        $bieb = $this->gebruiker(Rol::Bibliotheek);
        $reeks = \App\Models\Bibliotheek\Reeks::create(['titel' => 'Een reeks']);

        $this->actingAs($bieb)->post(route('bibliotheek.publicaties.store'), [
            'soort_id' => Publicatiesoort::metCode('tijdschrift')->id,
            'titel' => 'Studia Islamica',
            'reeks_id' => $reeks->id,
            'deelnummer' => 3,
        ])->assertRedirect();

        // De server wist de reekskoppeling: alleen een boek kan deel van een reeks zijn.
        $tijdschrift = Publicatie::where('titel', 'Studia Islamica')->firstOrFail();
        $this->assertNull($tijdschrift->reeks_id);
        $this->assertNull($tijdschrift->deelnummer);
    }

    public function test_artikelen_zijn_te_vinden_op_titel_auteur_trefwoord_en_tijdschrift(): void
    {
        $bieb = $this->gebruiker(Rol::Bibliotheek);
        $tijdschrift = $this->publicatie(['soort_id' => Publicatiesoort::metCode('tijdschrift')->id, 'titel' => 'Studia Islamica']);
        $uitgave = $tijdschrift->uitgaven()->create(['uitgavenummer' => '2025/1', 'jaar' => 2025]);

        $artikel = $uitgave->artikelen()->create([
            'titel' => 'Islamic finance in the Netherlands',
            'paginas' => '3-19',
            'trefwoorden' => 'finance, banking, fiqh',
        ]);
        $artikel->auteurs()->sync(Auteur::idsVoorNamen(['Sarah Whitfield']));

        foreach (['finance', 'Whitfield', 'banking', 'Studia'] as $zoekterm) {
            $this->actingAs($bieb)->get(route('bibliotheek.artikelen', ['q' => $zoekterm]))
                ->assertOk()
                ->assertSee('Islamic finance in the Netherlands');
        }
    }

    /* ----------------------------------------------------------------
     | Uitlenen en innemen
     |--------------------------------------------------------------- */

    public function test_uitlenen_aan_een_student_verstuurt_een_bevestiging_en_logt_die(): void
    {
        Mail::fake();

        $bieb = $this->gebruiker(Rol::Bibliotheek);
        $exemplaar = $this->exemplaar();
        $student = $this->student();

        $this->actingAs($bieb)->post(route('bibliotheek.uitlenen.store'), [
            'exemplaar_id' => $exemplaar->id,
            'lenerstype' => 'student',
            'student_id' => $student->id,
            'uitgeleend_op' => today()->toDateString(),
            'verwachte_retour_op' => today()->addDays(21)->toDateString(),
        ])->assertRedirect(route('bibliotheek.uitleningen'));

        $uitlening = Uitlening::firstOrFail();
        $this->assertSame($student->id, $uitlening->student_id);
        $this->assertNull($uitlening->medewerker_id);

        // De status van het exemplaar volgt de uitleen.
        $this->assertSame(ExemplaarStatus::Uitgeleend, $exemplaar->fresh()->status);

        // De bevestigingsmail is verstuurd én gelogd (datum, type, ontvanger, CC).
        Mail::assertSent(\App\Mail\BibliotheekBericht::class);
        $log = Emaillog::firstOrFail();
        $this->assertSame(BibliotheekMailsoort::Uitleenbevestiging, $log->soort);
        $this->assertSame('y.demir@student.iuasr.test', $log->ontvanger);
        $this->assertSame('bibliotheek@iuasr.nl', $log->cc);
        $this->assertTrue($log->gelukt);
    }

    public function test_een_uitgeleend_exemplaar_kan_niet_nogmaals_worden_uitgeleend(): void
    {
        Mail::fake();

        $bieb = $this->gebruiker(Rol::Bibliotheek);
        $exemplaar = $this->exemplaar();
        $student = $this->student();
        $medewerker = $this->medewerker();

        $formulier = [
            'exemplaar_id' => $exemplaar->id,
            'lenerstype' => 'student',
            'student_id' => $student->id,
            'uitgeleend_op' => today()->toDateString(),
            'verwachte_retour_op' => today()->addDays(21)->toDateString(),
        ];

        $this->actingAs($bieb)->post(route('bibliotheek.uitlenen.store'), $formulier)->assertRedirect();

        // Tweede poging — nu aan een medewerker — moet stuklopen.
        $this->actingAs($bieb)->post(route('bibliotheek.uitlenen.store'), array_merge($formulier, [
            'lenerstype' => 'medewerker',
            'student_id' => null,
            'medewerker_id' => $medewerker->id,
        ]))->assertSessionHasErrors('exemplaar_id');

        $this->assertSame(1, Uitlening::count());
    }

    public function test_uitlenen_vereist_een_lener(): void
    {
        $bieb = $this->gebruiker(Rol::Bibliotheek);
        $exemplaar = $this->exemplaar();

        $this->actingAs($bieb)->post(route('bibliotheek.uitlenen.store'), [
            'exemplaar_id' => $exemplaar->id,
            'lenerstype' => 'student',
            'uitgeleend_op' => today()->toDateString(),
            'verwachte_retour_op' => today()->addDays(21)->toDateString(),
        ])->assertSessionHasErrors('student_id');

        $this->assertSame(0, Uitlening::count());
    }

    public function test_te_laat_en_op_tijd_zijn_afleidingen_geen_kolommen(): void
    {
        $exemplaar = $this->exemplaar();
        $student = $this->student();

        $telaat = Uitlening::create([
            'exemplaar_id' => $exemplaar->id,
            'student_id' => $student->id,
            'uitgeleend_op' => Carbon::today()->subDays(30),
            'verwachte_retour_op' => Carbon::today()->subDays(5),
        ]);

        $this->assertTrue($telaat->isTeLaat());
        $this->assertSame(5, $telaat->dagenTeLaat());
        $this->assertFalse($telaat->isOpTijdIngeleverd());
        $this->assertSame(1, Uitlening::teLaat()->count());

        // Na inname op tijd: geen 'te laat' meer.
        $telaat->update(['retour_op' => Carbon::today()->subDays(6)]);
        $telaat->refresh();

        $this->assertFalse($telaat->isTeLaat());
        $this->assertTrue($telaat->isOpTijdIngeleverd());
        $this->assertSame(0, Uitlening::teLaat()->count());
    }

    public function test_innemen_met_schade_haalt_het_exemplaar_uit_de_uitleen(): void
    {
        Mail::fake();

        $bieb = $this->gebruiker(Rol::Bibliotheek);
        $exemplaar = $this->exemplaar();
        $student = $this->student();

        $uitlening = Uitlening::create([
            'exemplaar_id' => $exemplaar->id,
            'student_id' => $student->id,
            'uitgeleend_op' => Carbon::today()->subDays(10),
            'verwachte_retour_op' => Carbon::today()->addDays(11),
        ]);
        $exemplaar->update(['status' => ExemplaarStatus::Uitgeleend]);

        $this->actingAs($bieb)->put(route('bibliotheek.innemen.store', $uitlening), [
            'retour_op' => today()->toDateString(),
            'staat' => Materiaalstaat::Beschadigd->value,
            'retour_opmerking' => 'Kaft losgeraakt.',
        ])->assertRedirect();

        $this->assertSame(ExemplaarStatus::Beschadigd, $exemplaar->fresh()->status);
        $this->assertFalse($exemplaar->fresh()->isUitleenbaar());

        // Er is een schademelding gelogd.
        $this->assertDatabaseHas('audit_logs', ['veld' => 'schademelding']);
    }

    public function test_innemen_zonder_schade_maakt_het_exemplaar_weer_beschikbaar(): void
    {
        Mail::fake();

        $bieb = $this->gebruiker(Rol::Bibliotheek);
        $exemplaar = $this->exemplaar();
        $student = $this->student();

        $uitlening = Uitlening::create([
            'exemplaar_id' => $exemplaar->id,
            'student_id' => $student->id,
            'uitgeleend_op' => Carbon::today()->subDays(10),
            'verwachte_retour_op' => Carbon::today()->addDays(11),
        ]);
        $exemplaar->update(['status' => ExemplaarStatus::Uitgeleend]);

        $this->actingAs($bieb)->put(route('bibliotheek.innemen.store', $uitlening), [
            'retour_op' => today()->toDateString(),
            'staat' => Materiaalstaat::Goed->value,
        ])->assertRedirect();

        $this->assertSame(ExemplaarStatus::Beschikbaar, $exemplaar->fresh()->status);
        $this->assertTrue($exemplaar->fresh()->isUitleenbaar());
        $this->assertTrue($uitlening->fresh()->isOpTijdIngeleverd());
    }

    /* ----------------------------------------------------------------
     | E-mail: sjablonen, variabelen en herinneringen
     |--------------------------------------------------------------- */

    public function test_de_sjabloonvariabelen_worden_ingevuld(): void
    {
        $sjabloon = Emailsjabloon::where('soort', BibliotheekMailsoort::Uitleenbevestiging)->firstOrFail();

        $tekst = $sjabloon->render('Beste {{Naam}}, u leende {{Titel}} tot {{Retourdatum}}.', [
            'Naam' => 'Yusuf Demir',
            'Titel' => 'Tafsir Ibn Kathir',
            'Retourdatum' => '01-08-2026',
        ]);

        $this->assertSame('Beste Yusuf Demir, u leende Tafsir Ibn Kathir tot 01-08-2026.', $tekst);
    }

    public function test_de_herinneringen_worden_verstuurd_en_niet_dubbel(): void
    {
        Mail::fake();

        $student = $this->student();
        $medewerker = $this->medewerker();
        $publicatie = $this->publicatie();

        // Moet over 3 dagen terug → herinnering vooraf.
        $vooraf = Uitlening::create([
            'exemplaar_id' => $this->exemplaar($publicatie, 'A-1')->id,
            'student_id' => $student->id,
            'uitgeleend_op' => Carbon::today()->subDays(18),
            'verwachte_retour_op' => Carbon::today()->addDays(3),
        ]);

        // Student te laat → waarschuwing.
        $telaatStudent = Uitlening::create([
            'exemplaar_id' => $this->exemplaar($publicatie, 'A-2')->id,
            'student_id' => $student->id,
            'uitgeleend_op' => Carbon::today()->subDays(30),
            'verwachte_retour_op' => Carbon::today()->subDays(4),
        ]);

        // Docent te laat → herinnering (herhaalt elke 3 dagen).
        $telaatDocent = Uitlening::create([
            'exemplaar_id' => $this->exemplaar($publicatie, 'A-3')->id,
            'medewerker_id' => $medewerker->id,
            'uitgeleend_op' => Carbon::today()->subDays(70),
            'verwachte_retour_op' => Carbon::today()->subDays(8),
        ]);

        $this->artisan('bibliotheek:herinneringen')->assertSuccessful();

        $this->assertSame(1, Emaillog::where('uitlening_id', $vooraf->id)->where('soort', BibliotheekMailsoort::HerinneringVooraf)->count());
        $this->assertSame(1, Emaillog::where('uitlening_id', $telaatStudent->id)->where('soort', BibliotheekMailsoort::TeLaatStudent)->count());
        $this->assertSame(1, Emaillog::where('uitlening_id', $telaatDocent->id)->where('soort', BibliotheekMailsoort::TeLaatDocent)->count());

        // Tweede run op dezelfde dag: niets dubbel — ook niet voor de docent, want
        // het interval van 3 dagen is nog niet verstreken.
        $this->artisan('bibliotheek:herinneringen')->assertSuccessful();

        $this->assertSame(3, Emaillog::count());
    }

    public function test_een_mislukte_mail_blokkeert_de_uitlening_niet(): void
    {
        Mail::fake();

        $bieb = $this->gebruiker(Rol::Bibliotheek);
        $exemplaar = $this->exemplaar();

        // Student zonder e-mailadres.
        $student = Student::create([
            'studentnummer' => '265678',
            'voornaam' => 'Sami',
            'achternaam' => 'Bouzid',
        ]);

        $this->actingAs($bieb)->post(route('bibliotheek.uitlenen.store'), [
            'exemplaar_id' => $exemplaar->id,
            'lenerstype' => 'student',
            'student_id' => $student->id,
            'uitgeleend_op' => today()->toDateString(),
            'verwachte_retour_op' => today()->addDays(21)->toDateString(),
        ])->assertRedirect();

        // De uitlening is gewoon vastgelegd...
        $this->assertSame(1, Uitlening::count());

        // ...maar de mislukte verzending staat in het logboek.
        $log = Emaillog::firstOrFail();
        $this->assertFalse($log->gelukt);
        $this->assertStringContainsString('geen e-mailadres', $log->foutmelding);
    }

    /* ----------------------------------------------------------------
     | Integratie met Studentenzaken (opdracht §9)
     |--------------------------------------------------------------- */

    public function test_studentenzaken_ziet_te_late_studenten_op_het_dashboard(): void
    {
        $sz = $this->gebruiker(Rol::Studentenzaken);
        $student = $this->student();

        Uitlening::create([
            'exemplaar_id' => $this->exemplaar()->id,
            'student_id' => $student->id,
            'uitgeleend_op' => Carbon::today()->subDays(40),
            'verwachte_retour_op' => Carbon::today()->subDays(12),
        ]);

        $this->actingAs($sz)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Bibliotheek: te laat')
            ->assertSee('261234')                 // studentnummer
            ->assertSee('Yusuf Demir')            // naam
            ->assertSee('Tafsir Ibn Kathir');     // geleend materiaal

        // De docent heeft dit signaal niet.
        $this->assertFalse($this->gebruiker(Rol::Docent)->magBibliotheekSignaalZien());
    }

    public function test_de_module_verschijnt_op_het_keuzescherm(): void
    {
        $this->actingAs($this->gebruiker(Rol::Bibliotheek))->get(route('modules.kiezen'))
            ->assertOk()
            ->assertSee('Bibliotheek');
    }
}
