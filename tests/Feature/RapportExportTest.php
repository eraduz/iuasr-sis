<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischVakSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class RapportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, SynthetischVakSeeder::class, GebruikerSeeder::class, SynthetischeStudentSeeder::class]);
    }

    public function test_export_bevat_iban_en_geen_bsn(): void
    {
        $sz = User::where('rol', Rol::Studentenzaken)->first();

        $response = $this->actingAs($sz)->get(route('rapporten.actieve-studenten'));
        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('content-type'));

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        file_put_contents($tmp, $response->streamedContent());
        $sheet = IOFactory::load($tmp)->getActiveSheet();
        $koppen = $sheet->rangeToArray('A1:AE1', null, true, false)[0];
        @unlink($tmp);

        $this->assertContains('IBAN', $koppen);
        $this->assertNotContains('BSN', $koppen);
        $this->assertNotContains('Burgerservicenummer', $koppen);

        // Er staan actieve studenten in (meer dan alleen de kopregel).
        $this->assertGreaterThan(1, $sheet->getHighestRow());

        $this->assertDatabaseHas('audit_logs', ['veld' => 'export']);
    }

    public function test_export_toegang_per_rol(): void
    {
        $this->actingAs(User::where('rol', Rol::Financien)->first())->get(route('rapporten.actieve-studenten'))->assertOk();
        $this->actingAs(User::where('rol', Rol::Beheerder)->first())->get(route('rapporten.actieve-studenten'))->assertOk();

        $this->actingAs(User::where('rol', Rol::Docent)->first())->get(route('rapporten.actieve-studenten'))->assertForbidden();
        $this->actingAs(User::where('rol', Rol::Examencommissie)->first())->get(route('rapporten.actieve-studenten'))->assertForbidden();
    }

    public function test_alle_studenten_contactlijst_bevat_de_hele_database(): void
    {
        // Een 'losse' student zonder actieve inschrijving moet óók in de lijst staan.
        \App\Models\Student::create([
            'studentnummer' => '999999', 'voornaam' => 'Losse', 'achternaam' => 'Student',
            'telefoon' => '0612345678', 'email' => 'losse@iuasr.test',
        ]);

        $sz = User::where('rol', Rol::Studentenzaken)->first();
        $response = $this->actingAs($sz)->get(route('rapporten.alle-studenten'));
        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('content-type'));

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        file_put_contents($tmp, $response->streamedContent());
        $sheet = IOFactory::load($tmp)->getActiveSheet();
        $koppen = $sheet->rangeToArray('A1:G1', null, true, false)[0];
        $rijen = $sheet->getHighestRow() - 1; // zonder kopregel
        $plat = collect($sheet->toArray())->flatten()->filter()->all();
        @unlink($tmp);

        $this->assertContains('Voornaam', $koppen);
        $this->assertContains('Achternaam', $koppen);
        $this->assertContains('Telefoon', $koppen);
        $this->assertContains('E-mail (IUASR)', $koppen);
        $this->assertNotContains('IBAN', $koppen);
        $this->assertNotContains('BSN', $koppen);

        // Hele database: precies alle studenten, incl. de losse zonder inschrijving.
        $this->assertSame(\App\Models\Student::count(), $rijen);
        $this->assertContains('999999', $plat);

        $this->assertDatabaseHas('audit_logs', ['veld' => 'export']);
    }

    public function test_alle_studenten_export_toegang_per_rol(): void
    {
        $this->actingAs(User::where('rol', Rol::Beheerder)->first())->get(route('rapporten.alle-studenten'))->assertOk();
        $this->actingAs(User::where('rol', Rol::Docent)->first())->get(route('rapporten.alle-studenten'))->assertForbidden();
        $this->actingAs(User::where('rol', Rol::Examencommissie)->first())->get(route('rapporten.alle-studenten'))->assertForbidden();
    }
}
