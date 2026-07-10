<?php

namespace Tests\Feature;

use App\Enums\InschrijvingStatus;
use App\Enums\Rol;
use App\Models\Inschrijving;
use App\Models\Opleiding;
use App\Models\Periode;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischVakSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlumniRapportTest extends TestCase
{
    use RefreshDatabase;

    private function maakStudent(string $nummer, string $achternaam, InschrijvingStatus $status, array $extra = []): Student
    {
        $student = Student::create(array_merge([
            'studentnummer' => $nummer, 'voornaam' => 'A', 'achternaam' => $achternaam,
        ], $extra));
        Inschrijving::create([
            'student_id' => $student->id,
            'opleiding_id' => Opleiding::where('code', 'ISLTH')->value('id'),
            'periode_id' => Periode::where('actief', true)->value('id'),
            'status' => $status,
            'inschrijfdatum' => '2026-09-01',
        ]);

        return $student;
    }

    private function seedTwee(): void
    {
        $this->seed(ReferentieSeeder::class);
        $this->maakStudent('260001', 'Afgestudeerd', InschrijvingStatus::Afgestudeerd, [
            'email' => 'alumnus@student.iuasr.nl', 'telefoon' => '06 12 34 56 78',
        ]);
        $this->maakStudent('260002', 'Actief', InschrijvingStatus::Actief);
    }

    public function test_studentenzaken_ziet_alumni_met_contactgegevens(): void
    {
        $this->seedTwee();
        $sz = User::create(['naam' => 'SZ', 'email' => 'sz@iuasr.test', 'rol' => Rol::Studentenzaken]);

        $this->actingAs($sz)->get(route('rapporten.alumni'))
            ->assertOk()
            ->assertSee('alumnus@student.iuasr.nl')  // e-mail
            ->assertSee('06 12 34 56 78')            // telefoon
            ->assertSee('260001')                    // afgestudeerde student
            ->assertDontSee('260002');               // actieve student niet in alumni
    }

    public function test_directie_mag_het_alumni_rapport_zien(): void
    {
        $this->seedTwee();
        $directie = User::create(['naam' => 'Dir', 'email' => 'dir@iuasr.test', 'rol' => Rol::Directie]);
        // Directie is opleidinggebonden: toewijzen aan de opleiding van de alumnus.
        $directie->opleidingen()->attach(Opleiding::where('code', 'ISLTH')->value('id'));

        $this->actingAs($directie)->get(route('rapporten.alumni'))->assertOk()->assertSee('260001');
    }

    public function test_directie_zonder_toewijzing_ziet_geen_alumni(): void
    {
        $this->seedTwee();
        $directie = User::create(['naam' => 'Dir2', 'email' => 'dir2@iuasr.test', 'rol' => Rol::Directie]);

        // Geen opleidingtoewijzing -> geen studenten zichtbaar (restrictief, need-to-know).
        $this->actingAs($directie)->get(route('rapporten.alumni'))->assertOk()->assertDontSee('260001');
    }

    /** Het Schoolbestuur ziet alle alumni: het is niet opleidinggebonden. */
    public function test_schoolbestuur_mag_het_alumni_rapport_zien(): void
    {
        $this->seedTwee();
        $bestuur = User::create(['naam' => 'Bestuur', 'email' => 'bestuur@iuasr.test', 'rol' => Rol::Bestuur]);

        $this->actingAs($bestuur)->get(route('rapporten.alumni'))
            ->assertOk()
            ->assertSee('260001')
            ->assertSee('alumnus@student.iuasr.nl')
            ->assertDontSee('260002');
    }

    /**
     * Het bestuur heeft geen rapportenoverzicht; de 'Terug'-link mag dus niet naar
     * de SZ-route `rapporten` wijzen, want daar krijgt het bestuur een 403.
     */
    public function test_schoolbestuur_krijgt_geen_link_naar_een_verboden_rapportenpagina(): void
    {
        $this->seedTwee();
        $bestuur = User::create(['naam' => 'Bestuur2', 'email' => 'bestuur2@iuasr.test', 'rol' => Rol::Bestuur]);

        // Exact op de href toetsen: route('rapporten.alumni') bevat '/rapporten'
        // als deelreeks, dus een losse tekstvergelijking zou altijd raak zijn.
        $this->actingAs($bestuur)->get(route('rapporten.alumni'))
            ->assertOk()
            ->assertDontSee('href="'.route('rapporten').'"', false);

        // En die pagina blijft ook echt afgeschermd.
        $this->actingAs($bestuur)->get(route('rapporten'))->assertForbidden();
    }

    /** De examencommissie mag het alumni-rapport zien (geen cijfers/BSN) en ziet alle alumni. */
    public function test_examencommissie_mag_het_alumni_rapport_zien(): void
    {
        $this->seedTwee();
        $ec = User::create(['naam' => 'EC', 'email' => 'ec@iuasr.test', 'rol' => Rol::Examencommissie]);

        $this->actingAs($ec)->get(route('rapporten.alumni'))
            ->assertOk()->assertSee('260001')->assertDontSee('260002');
    }

    public function test_docent_en_financien_mogen_het_alumni_rapport_niet_zien(): void
    {
        $this->seedTwee();

        foreach ([Rol::Docent, Rol::Financien] as $rol) {
            $gebruiker = User::create([
                'naam' => $rol->value, 'email' => $rol->value.'@iuasr.test', 'rol' => $rol,
            ]);
            $this->actingAs($gebruiker)->get(route('rapporten.alumni'))->assertForbidden();
        }
    }
}
