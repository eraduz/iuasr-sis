<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Docent;
use App\Models\Faculteit;
use App\Models\Resultaat;
use App\Models\Student;
use App\Models\User;
use App\Models\Vak;
use App\Support\MigratieImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Tijdelijke migratie-import (Studentenzaken) van studenten uit de oude Access-
 * database. Preview schrijft niets; import maakt studenten aan; bestaande
 * studentnummers worden niet overschreven.
 */
class MigratieTest extends TestCase
{
    use RefreshDatabase;

    private const CSV = <<<'CSV'
"SDT-NR";"Aanhef";"Voornaam";"Achternaam";"Gb Datum";"Gb Plaats";"Nationaliteit1";"E-mail";"Opleiding";"Diploma"
"990001";"Dhr.";"Ahmed";"El Amrani";"1990-05-12";"Rotterdam";"Nederlandse";"a@x.nl";"VWO";"True"
"990002";"Mevr.";"Sara";"Bennani";"1991-03-08";"Utrecht";"Marokkaanse";"s@x.nl";"HAVO";"False"
CSV;

    private function sz(): User
    {
        return User::create(['naam' => 'SZ', 'email' => 'sz@iuasr.test', 'rol' => Rol::Studentenzaken]);
    }

    private function upload(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('studenten.csv', self::CSV);
    }

    public function test_scherm_is_bereikbaar_voor_studentenzaken(): void
    {
        $this->actingAs($this->sz())->get(route('migratie'))->assertOk();
    }

    public function test_preview_schrijft_niets(): void
    {
        $this->actingAs($this->sz())
            ->post(route('migratie.verwerk'), ['type' => 'studenten', 'modus' => 'preview', 'bestand' => $this->upload()])
            ->assertOk()->assertSee('Controle');

        $this->assertSame(0, Student::count());
    }

    public function test_import_maakt_studenten_aan(): void
    {
        $this->actingAs($this->sz())
            ->post(route('migratie.verwerk'), ['type' => 'studenten', 'modus' => 'import', 'bestand' => $this->upload()])
            ->assertOk();

        $this->assertSame(2, Student::count());
        $ahmed = Student::where('studentnummer', '990001')->firstOrFail();
        $this->assertSame('El Amrani', $ahmed->achternaam);
        $this->assertSame('VWO', $ahmed->vooropleiding);
        $this->assertTrue((bool) $ahmed->diploma);
        $this->assertSame('M', $ahmed->geslacht);
        $this->assertNotNull($ahmed->nationaliteit_id);
        // De oude Access-'E-mail' hoort in het PRIVÉ-veld (in gebruik/zichtbaar),
        // niet in het IUASR-veld (dat leeg blijft tot IUASR eigen mailboxen uitgeeft).
        $this->assertSame('a@x.nl', $ahmed->email_prive);
        $this->assertNull($ahmed->email);
    }

    public function test_import_is_idempotent(): void
    {
        $sz = $this->sz();
        $this->actingAs($sz)->post(route('migratie.verwerk'), ['type' => 'studenten', 'modus' => 'import', 'bestand' => $this->upload()]);
        $this->actingAs($sz)->post(route('migratie.verwerk'), ['type' => 'studenten', 'modus' => 'import', 'bestand' => $this->upload()]);

        $this->assertSame(2, Student::count()); // niet verdubbeld
    }

    public function test_docent_heeft_geen_toegang(): void
    {
        $docent = User::create(['naam' => 'D', 'email' => 'd@iuasr.test', 'rol' => Rol::Docent, 'docent_id' => Docent::create(['code' => 'DOC-X', 'achternaam' => 'X'])->id]);
        $this->actingAs($docent)->get(route('migratie'))->assertForbidden();
    }

    // ---- Fase 2: vakken ----

    private const VAKKEN_CSV = <<<'CSV'
"Vak id";"Vak naam";"EC"
"0";"";""
"B1-AR01A";"STANDAARD ARABISCH";"5"
"B1-QR02A";"QORANRECITATIE";"2,5"
CSV;

    public function test_vakken_import_maakt_historische_vakken_met_ec(): void
    {
        Faculteit::create(['code' => 'FIW', 'naam' => 'Faculteit Islamitische Wetenschappen']);

        $rapport = (new MigratieImport)->verwerkVakken($this->rijen(self::VAKKEN_CSV), dryRun: false);

        $this->assertSame(2, $rapport['nieuw']);
        $this->assertSame(1, $rapport['leeg']); // de "0"-junkregel
        $opleiding = \App\Models\Opleiding::where('code', MigratieImport::HIST_OPLEIDING_CODE)->firstOrFail();
        $this->assertFalse((bool) $opleiding->actief);
        $arabisch = Vak::where('opleiding_id', $opleiding->id)->where('code', 'B1-AR01A')->firstOrFail();
        $this->assertEquals(5.0, (float) $arabisch->ec);
        $this->assertEquals(2.5, (float) Vak::where('code', 'B1-QR02A')->value('ec'));
    }

    // ---- Fase 2: cijfers ----

    private function cijferRij(string $std, string $vak, string $periode, string $gemmid, string $vrijstelling = 'False'): string
    {
        return "\"{$std}\";\"{$vak}\";\"HADITH\";\"{$periode}\";\"{$gemmid}\";\"{$vrijstelling}\"";
    }

    private function cijfersCsv(array $rijen): string
    {
        return "\"CL-STD-NR\";\"C-VAK-ID\";\"CLVAK NAAM\";\"CL-PERIODE\";\"cl-gemmid\";\"vrijstelling\"\n".implode("\n", $rijen);
    }

    private function student(string $nummer): Student
    {
        return Student::create(['studentnummer' => $nummer, 'voornaam' => 'T', 'achternaam' => 'Test']);
    }

    public function test_cijfers_import_maakt_eindcijfer_resultaat(): void
    {
        Faculteit::create(['code' => 'FIW', 'naam' => 'FIW']);
        $student = $this->student('131516');

        $csv = $this->cijfersCsv([$this->cijferRij('131516', 'B-HD02', '2016-2017', '61')]);
        $rapport = (new MigratieImport)->verwerkCijfers($this->rijen($csv), dryRun: false);

        $this->assertSame(1, $rapport['nieuw']);
        $this->assertSame(1, $rapport['vakken_bij']);
        $this->assertSame(1, $rapport['inschrijvingen_bij']);
        $resultaat = Resultaat::where('student_id', $student->id)->firstOrFail();
        $this->assertSame('6.1', (string) $resultaat->cijfer); // 61/10
        $this->assertTrue((bool) $resultaat->voldoende);
        $this->assertTrue((bool) $resultaat->definitief);
    }

    public function test_cijfers_import_is_idempotent(): void
    {
        Faculteit::create(['code' => 'FIW', 'naam' => 'FIW']);
        $this->student('131516');
        $csv = $this->cijfersCsv([$this->cijferRij('131516', 'B-HD02', '2016-2017', '80')]);

        (new MigratieImport)->verwerkCijfers($this->rijen($csv), dryRun: false);
        $tweede = (new MigratieImport)->verwerkCijfers($this->rijen($csv), dryRun: false);

        $this->assertSame(0, $tweede['nieuw']);
        $this->assertSame(1, $tweede['overgeslagen']);
        $this->assertSame(1, Resultaat::count());
    }

    public function test_cijfers_onbekende_student_en_lege_cijfers_worden_overgeslagen(): void
    {
        Faculteit::create(['code' => 'FIW', 'naam' => 'FIW']);
        $this->student('131516');
        $csv = $this->cijfersCsv([
            $this->cijferRij('999999', 'B-HD02', '2016-2017', '70'),   // onbekende student
            $this->cijferRij('131516', 'B-HD02', '2016-2017', '0'),    // geen cijfer, geen vrijstelling
        ]);

        $rapport = (new MigratieImport)->verwerkCijfers($this->rijen($csv), dryRun: false);

        $this->assertSame(0, $rapport['nieuw']);
        $this->assertSame(1, $rapport['student_onbekend']);
        $this->assertSame(1, $rapport['geen_cijfer']);
        $this->assertSame(0, Resultaat::count());
    }

    public function test_cijfers_vrijstelling_geeft_resultaat_zonder_cijfer(): void
    {
        Faculteit::create(['code' => 'FIW', 'naam' => 'FIW']);
        $student = $this->student('131516');
        $csv = $this->cijfersCsv([$this->cijferRij('131516', 'B-HD02', '2016-2017', '0', 'True')]);

        $rapport = (new MigratieImport)->verwerkCijfers($this->rijen($csv), dryRun: false);

        $this->assertSame(1, $rapport['nieuw']);
        $resultaat = Resultaat::where('student_id', $student->id)->firstOrFail();
        $this->assertNull($resultaat->cijfer);
        $this->assertTrue((bool) $resultaat->vrijstelling);
        $this->assertTrue((bool) $resultaat->voldoende);
    }

    public function test_cijfers_vakcode_is_hoofdletter_ongevoelig(): void
    {
        Faculteit::create(['code' => 'FIW', 'naam' => 'FIW']);
        $this->student('131516');
        $this->student('131517');
        // Twee regels naar hetzelfde vak, met verschillende casing.
        $csv = $this->cijfersCsv([
            $this->cijferRij('131516', 'B-FQ04', '2016-2017', '70'),
            $this->cijferRij('131517', 'b-fq04', '2016-2017', '80'),
        ]);

        $rapport = (new MigratieImport)->verwerkCijfers($this->rijen($csv), dryRun: false);

        $this->assertSame(2, $rapport['nieuw']);
        $this->assertSame(1, $rapport['vakken_bij']); // slechts één vak aangemaakt
        $this->assertSame(1, Vak::count());
        $this->assertSame(2, Resultaat::count());
    }

    public function test_cijfers_los_startjaar_wordt_studiejaar(): void
    {
        Faculteit::create(['code' => 'FIW', 'naam' => 'FIW']);
        $this->student('121469');
        $csv = $this->cijfersCsv([$this->cijferRij('121469', 'B1-HD01', '2012', '60')]);

        $rapport = (new MigratieImport)->verwerkCijfers($this->rijen($csv), dryRun: false);

        $this->assertSame(1, $rapport['nieuw']);
        $this->assertSame(0, count($rapport['fouten']));
        $this->assertTrue(\App\Models\Periode::where('code', '2012-2013')->exists());
    }

    public function test_cijfers_preview_schrijft_niets(): void
    {
        Faculteit::create(['code' => 'FIW', 'naam' => 'FIW']);
        $this->student('131516');
        $csv = $this->cijfersCsv([$this->cijferRij('131516', 'B-HD02', '2016-2017', '80')]);

        $rapport = (new MigratieImport)->verwerkCijfers($this->rijen($csv), dryRun: true);

        $this->assertSame(1, $rapport['nieuw']);
        $this->assertSame(0, Resultaat::count());
        $this->assertSame(0, Vak::count());
    }

    /** @return list<array<string,string>> */
    private function rijen(string $csv): array
    {
        $pad = tempnam(sys_get_temp_dir(), 'mig');
        file_put_contents($pad, $csv);
        $rijen = \App\Support\CsvLezer::associatief($pad);
        @unlink($pad);

        return $rijen;
    }
}
