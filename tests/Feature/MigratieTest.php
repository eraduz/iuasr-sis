<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Docent;
use App\Models\Student;
use App\Models\User;
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
}
