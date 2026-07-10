<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Enums\TaakPrioriteit;
use App\Enums\TaakStatus;
use App\Models\Student;
use App\Models\Taak;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischVakSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TakenTest extends TestCase
{
    use RefreshDatabase;

    private User $sz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, SynthetischVakSeeder::class,
            GebruikerSeeder::class, SynthetischeStudentSeeder::class]);
        $this->sz = User::where('rol', Rol::Studentenzaken)->first();
        Carbon::setTestNow('2026-07-10');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function taak(array $overschrijf = []): Taak
    {
        return Taak::create(array_merge([
            'titel' => 'Diploma opvragen',
            'vervaldatum' => '2026-07-17',
            'prioriteit' => TaakPrioriteit::Normaal,
            'status' => TaakStatus::Open,
            'aangemaakt_door_id' => $this->sz->id,
        ], $overschrijf));
    }

    public function test_studentenzaken_maakt_een_taak_aan(): void
    {
        $this->actingAs($this->sz)->post(route('taken.store'), [
            'titel' => 'NT2-brief versturen',
            'vervaldatum' => '2026-07-20',
            'prioriteit' => 'hoog',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('taken', [
            'titel' => 'NT2-brief versturen', 'status' => 'open',
            'prioriteit' => 'hoog', 'aangemaakt_door_id' => $this->sz->id,
        ]);
    }

    public function test_taak_zonder_begindatum_wordt_geaccepteerd(): void
    {
        // Het snelformulier op het studentdossier stuurt geen begindatum mee.
        $this->actingAs($this->sz)->post(route('taken.store'), [
            'titel' => 'Snelle taak', 'vervaldatum' => '2026-07-20', 'prioriteit' => 'normaal',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('taken', ['titel' => 'Snelle taak', 'startdatum' => null]);
    }

    public function test_vervaldatum_mag_niet_voor_de_begindatum_liggen(): void
    {
        $this->actingAs($this->sz)->post(route('taken.store'), [
            'titel' => 'Fout', 'startdatum' => '2026-07-20',
            'vervaldatum' => '2026-07-10', 'prioriteit' => 'normaal',
        ])->assertSessionHasErrors('vervaldatum');

        $this->assertDatabaseMissing('taken', ['titel' => 'Fout']);
    }

    public function test_taak_zonder_vervaldatum_mag(): void
    {
        $this->actingAs($this->sz)->post(route('taken.store'), [
            'titel' => 'Ooit een keer', 'prioriteit' => 'laag',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('taken', ['titel' => 'Ooit een keer', 'vervaldatum' => null]);
    }

    public function test_afvinken_zet_status_en_afrondmoment(): void
    {
        $taak = $this->taak();

        $this->actingAs($this->sz)->post(route('taken.afronden', $taak))->assertRedirect();

        $taak->refresh();
        $this->assertSame(TaakStatus::Afgerond, $taak->status);
        $this->assertNotNull($taak->afgerond_op);
    }

    public function test_heropenen_wist_het_afrondmoment(): void
    {
        $taak = $this->taak(['status' => TaakStatus::Afgerond, 'afgerond_op' => now()]);

        $this->actingAs($this->sz)->post(route('taken.afronden', $taak))->assertRedirect();

        $taak->refresh();
        $this->assertSame(TaakStatus::Open, $taak->status);
        $this->assertNull($taak->afgerond_op);
    }

    /** 'Te laat' is afgeleid, geen opgeslagen status. */
    public function test_te_laat_wordt_afgeleid_uit_de_vervaldatum(): void
    {
        $verstreken = $this->taak(['vervaldatum' => '2026-07-08']);
        $vandaag = $this->taak(['vervaldatum' => '2026-07-10']);
        $toekomst = $this->taak(['vervaldatum' => '2026-07-15']);

        $this->assertTrue($verstreken->isTeLaat());
        $this->assertSame('2 dagen te laat', $verstreken->urgentie());

        $this->assertFalse($vandaag->isTeLaat());
        $this->assertSame('vandaag', $vandaag->urgentie());

        $this->assertFalse($toekomst->isTeLaat());
        $this->assertSame('over 5 dagen', $toekomst->urgentie());
    }

    public function test_afgeronde_taak_is_nooit_te_laat(): void
    {
        $taak = $this->taak(['vervaldatum' => '2026-07-01', 'status' => TaakStatus::Afgerond, 'afgerond_op' => now()]);

        $this->assertFalse($taak->isTeLaat());
        $this->assertSame('afgerond', $taak->urgentie());
        $this->assertNull($taak->dagenResterend());
    }

    public function test_taak_zonder_vervaldatum_is_nooit_te_laat(): void
    {
        $taak = $this->taak(['vervaldatum' => null]);

        $this->assertFalse($taak->isTeLaat());
        $this->assertSame('geen datum', $taak->urgentie());
    }

    public function test_taak_kan_aan_een_student_worden_gekoppeld(): void
    {
        $student = Student::first();

        $this->actingAs($this->sz)->post(route('taken.store'), [
            'titel' => 'Dossier controleren', 'student_id' => $student->id,
            'vervaldatum' => '2026-07-20', 'prioriteit' => 'normaal',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('taken', ['titel' => 'Dossier controleren', 'student_id' => $student->id]);

        // De taak staat op het dossier van die student.
        $this->actingAs($this->sz)->get(route('studenten.show', $student))
            ->assertOk()->assertSee('Dossier controleren');
    }

    public function test_lijst_toont_eigen_en_vrije_taken_op_het_dashboard(): void
    {
        $ander = User::where('rol', Rol::Beheerder)->first();

        $this->taak(['titel' => 'Van mij', 'toegewezen_aan_id' => $this->sz->id, 'vervaldatum' => '2026-07-11']);
        $this->taak(['titel' => 'Van niemand', 'toegewezen_aan_id' => null, 'vervaldatum' => '2026-07-12']);
        $this->taak(['titel' => 'Van een ander', 'toegewezen_aan_id' => $ander->id, 'vervaldatum' => '2026-07-13']);

        $this->actingAs($this->sz)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Van mij')
            ->assertSee('Van niemand')
            ->assertDontSee('Van een ander');
    }

    public function test_dashboard_toont_geen_taken_ver_in_de_toekomst(): void
    {
        $this->taak(['titel' => 'Volgende maand', 'vervaldatum' => '2026-08-20']);

        $this->actingAs($this->sz)->get(route('dashboard'))
            ->assertOk()->assertDontSee('Volgende maand');
    }

    /**
     * In de standaardweergave staat de werkvoorraad bovenaan en de afgeronde
     * taken in een aparte sectie eronder — beide zijn dus zichtbaar.
     */
    public function test_afgeronde_taken_staan_apart_onder_de_openstaande(): void
    {
        $this->taak(['titel' => 'Nog te doen']);
        $this->taak(['titel' => 'Al gedaan', 'status' => TaakStatus::Afgerond, 'afgerond_op' => now()]);

        $antwoord = $this->actingAs($this->sz)->get(route('taken'))->assertOk();
        $antwoord->assertSee('Nog te doen')->assertSee('Al gedaan')->assertSee('Afgerond');

        // De afgeronde taak staat ná de openstaande in de pagina.
        $html = $antwoord->getContent();
        $this->assertLessThan(strpos($html, 'Al gedaan'), strpos($html, 'Nog te doen'));
    }

    public function test_filteren_op_afgerond_toont_alleen_afgeronde_taken(): void
    {
        $this->taak(['titel' => 'Nog te doen']);
        $this->taak(['titel' => 'Al gedaan', 'status' => TaakStatus::Afgerond, 'afgerond_op' => now()]);

        $this->actingAs($this->sz)->get(route('taken', ['status' => 'afgerond']))
            ->assertOk()->assertSee('Al gedaan')->assertDontSee('Nog te doen');

        $this->actingAs($this->sz)->get(route('taken', ['status' => 'alle']))
            ->assertOk()->assertSee('Nog te doen')->assertSee('Al gedaan');
    }

    public function test_bijwerken_wist_het_afrondmoment_bij_heropenen(): void
    {
        $taak = $this->taak(['status' => TaakStatus::Afgerond, 'afgerond_op' => now()]);

        $this->actingAs($this->sz)->put(route('taken.update', $taak), [
            'titel' => $taak->titel, 'prioriteit' => 'normaal', 'status' => 'bezig',
        ])->assertSessionHasNoErrors()->assertRedirect(route('taken'));

        $taak->refresh();
        $this->assertSame(TaakStatus::Bezig, $taak->status);
        $this->assertNull($taak->afgerond_op);
    }

    public function test_alleen_studentenzaken_en_beheer_hebben_toegang(): void
    {
        $taak = $this->taak();

        foreach ([Rol::Studentenzaken, Rol::Beheerder] as $rol) {
            $this->actingAs(User::where('rol', $rol)->first())->get(route('taken'))->assertOk();
        }

        foreach ([Rol::Docent, Rol::Examencommissie, Rol::Directie, Rol::Bestuur, Rol::Financien] as $rol) {
            $gebruiker = User::where('rol', $rol)->first();
            $this->actingAs($gebruiker)->get(route('taken'))->assertForbidden();
            $this->actingAs($gebruiker)->post(route('taken.afronden', $taak))->assertForbidden();
        }
    }

    public function test_verwijderen_van_een_student_laat_de_taak_bestaan(): void
    {
        $student = Student::first();
        $taak = $this->taak(['student_id' => $student->id]);

        // Verwijderen vereist bevestiging met het studentnummer.
        $this->actingAs(User::where('rol', Rol::Beheerder)->first())
            ->delete(route('studenten.destroy', $student), ['bevestig_nummer' => $student->studentnummer])
            ->assertRedirect(route('studenten.index'));

        $this->assertDatabaseMissing('studenten', ['id' => $student->id]);

        $taak->refresh();
        $this->assertNull($taak->student_id);   // koppeling weg, taak blijft
        $this->assertSame('Diploma opvragen', $taak->titel);
    }
}
