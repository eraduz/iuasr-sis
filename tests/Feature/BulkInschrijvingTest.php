<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BulkInschrijvingTest extends TestCase
{
    use RefreshDatabase;

    private User $sz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, GebruikerSeeder::class, SynthetischeStudentSeeder::class]);
        $this->sz = User::where('rol', Rol::Studentenzaken)->first();
    }

    private function csv(string $inhoud): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('aanmeldingen.csv', $inhoud);
    }

    public function test_controle_toont_overzicht_en_slaat_nog_niets_op(): void
    {
        $csv = "voornaam;achternaam;geboortedatum;email;opleiding;leerjaar\r\n"
            ."Nieuwe;Aanmelding;01-02-2005;nieuwe@example.com;ISLTH;1\r\n"
            ."Tweede;Kandidaat;;tweede@example.com;Islamitische Theologie;1\r\n"
            ."Foute;Opleiding;;x@example.com;ZZZ;1\r\n"          // onbekende opleiding
            ."Yasmin;Demir;14-03-2004;dubbel@example.com;ISLTH;1\r\n"; // bestaat al (naam+geb.datum)

        $voor = Student::count();

        $this->actingAs($this->sz)->post(route('bulk-inschrijven.controle'), ['bestand' => $this->csv($csv)])
            ->assertOk()
            ->assertSee('Nieuwe Aanmelding')
            ->assertSessionHas('bulk_preview');

        $this->assertSame($voor, Student::count()); // nog niets opgeslagen
    }

    public function test_bevestigen_schrijft_de_gecontroleerde_studenten_in(): void
    {
        $csv = "voornaam;achternaam;geboortedatum;email;opleiding;leerjaar\r\n"
            ."Nieuwe;Aanmelding;01-02-2005;nieuwe@example.com;ISLTH;1\r\n"
            ."Tweede;Kandidaat;;tweede@example.com;ISLTH;1\r\n"
            ."Foute;Opleiding;;x@example.com;ZZZ;1\r\n";

        $voor = Student::count();

        $this->actingAs($this->sz)->post(route('bulk-inschrijven.controle'), ['bestand' => $this->csv($csv)])->assertOk();
        $this->actingAs($this->sz)->post(route('bulk-inschrijven.importeer'))
            ->assertRedirect(route('studenten.index'));

        $this->assertSame($voor + 2, Student::count()); // alleen de twee geldige

        $nieuw = Student::where('voornaam', 'Nieuwe')->where('achternaam', 'Aanmelding')->first();
        $this->assertNotNull($nieuw);
        $this->assertNotNull($nieuw->studentnummer);
        $this->assertSame('actief', $nieuw->inschrijvingen()->first()->status->value);
    }

    public function test_bulk_alleen_voor_studentenzaken_en_beheer(): void
    {
        $this->actingAs(User::where('rol', Rol::Beheerder)->first())->get(route('bulk-inschrijven'))->assertOk();
        $this->actingAs(User::where('rol', Rol::Docent)->first())->get(route('bulk-inschrijven'))->assertForbidden();
        $this->actingAs(User::where('rol', Rol::Financien)->first())->post(route('bulk-inschrijven.controle'), [])->assertForbidden();
    }
}
