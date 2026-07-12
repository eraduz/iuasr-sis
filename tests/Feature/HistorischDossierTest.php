<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Faculteit;
use App\Models\Student;
use App\Models\User;
use App\Support\MigratieImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Historisch studentdossier — alleen-lezen inzage in gemigreerde cijfers,
 * voorbehouden aan de cijfer-bevoegde rollen (+ Beheerder). Studentenzaken
 * heeft geen toegang (rolscheiding op cijfers).
 */
class HistorischDossierTest extends TestCase
{
    use RefreshDatabase;

    private function seedDossier(): Student
    {
        Faculteit::create(['code' => 'FIW', 'naam' => 'FIW']);
        $student = Student::create(['studentnummer' => '131516', 'voornaam' => 'Ahmed', 'achternaam' => 'El Amrani']);

        $csv = "\"CL-STD-NR\";\"C-VAK-ID\";\"CLVAK NAAM\";\"CL-PERIODE\";\"cl-gemmid\";\"vrijstelling\"\n"
            ."\"131516\";\"B-HD02\";\"HADITH\";\"2016-2017\";\"61\";\"False\"\n"
            ."\"131516\";\"B-QR01\";\"QORAN\";\"2016-2017\";\"30\";\"False\"\n"
            ."\"131516\";\"B-MT01\";\"STUDIE\";\"2017-2018\";\"0\";\"True\"";

        $pad = tempnam(sys_get_temp_dir(), 'hist');
        file_put_contents($pad, $csv);
        (new MigratieImport)->verwerkCijfers(\App\Support\CsvLezer::associatief($pad), dryRun: false);
        @unlink($pad);

        return $student;
    }

    private function examencommissie(): User
    {
        return User::create(['naam' => 'EC', 'email' => 'ec@iuasr.test', 'rol' => Rol::Examencommissie]);
    }

    public function test_index_bereikbaar_voor_examencommissie(): void
    {
        $this->seedDossier();
        $this->actingAs($this->examencommissie())
            ->get(route('historisch.index'))
            ->assertOk()
            ->assertSee('131516');
    }

    public function test_show_toont_cijfers_per_studiejaar(): void
    {
        $student = $this->seedDossier();

        $response = $this->actingAs($this->examencommissie())
            ->get(route('historisch.show', $student))
            ->assertOk();

        $response->assertSee('Studiejaar 2016-2017');
        $response->assertSee('Studiejaar 2017-2018');
        $response->assertSee('6,1');           // 61/10
        $response->assertSee('Onvoldoende');   // 30/10 = 3,0
        $response->assertSee('Vrijstelling');
    }

    public function test_pdf_levert_een_pdf_op(): void
    {
        $student = $this->seedDossier();

        $response = $this->actingAs($this->examencommissie())
            ->get(route('historisch.pdf', $student));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->streamedContent());
    }

    public function test_studentenzaken_heeft_geen_toegang(): void
    {
        $sz = User::create(['naam' => 'SZ', 'email' => 'sz@iuasr.test', 'rol' => Rol::Studentenzaken]);
        $this->actingAs($sz)->get(route('historisch.index'))->assertForbidden();
        $student = $this->seedDossier();
        $this->actingAs($sz)->get(route('historisch.pdf', $student))->assertForbidden();
    }

    public function test_beheerder_heeft_wel_toegang(): void
    {
        $this->seedDossier();
        $beheer = User::create(['naam' => 'B', 'email' => 'b@iuasr.test', 'rol' => Rol::Beheerder]);
        $this->actingAs($beheer)->get(route('historisch.index'))->assertOk();
    }

    public function test_show_zonder_historie_geeft_404(): void
    {
        // Wel een BA-HIST-opleiding (via een dossier), maar deze student heeft er geen.
        $this->seedDossier();
        $leeg = Student::create(['studentnummer' => '999999', 'voornaam' => 'Geen', 'achternaam' => 'Historie']);

        $this->actingAs($this->examencommissie())
            ->get(route('historisch.show', $leeg))
            ->assertNotFound();
    }
}
