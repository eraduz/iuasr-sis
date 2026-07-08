<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use App\Support\Backup;
use App\Support\DatabaseDump;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, GebruikerSeeder::class]);
    }

    public function test_databasedump_bevat_structuur_en_data(): void
    {
        $pad = tempnam(sys_get_temp_dir(), 'dumptest_');
        $h = fopen($pad, 'w');
        DatabaseDump::schrijf($h);
        fclose($h);

        $sql = file_get_contents($pad);
        @unlink($pad);

        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('`users`', $sql);
        $this->assertStringContainsString('INSERT INTO `users`', $sql);
    }

    public function test_backup_is_versleutelde_zip_met_database_en_env(): void
    {
        $pad = Backup::maak('Sterk-Wachtwoord-123');
        $this->assertFileExists($pad);

        // Zonder wachtwoord is de inhoud niet leesbaar (versleuteld).
        $zonder = new ZipArchive();
        $zonder->open($pad);
        $this->assertNotFalse($zonder->locateName('database.sql'));
        $this->assertNotFalse($zonder->locateName('.env'));
        $this->assertFalse(@$zonder->getFromName('database.sql'));
        $zonder->close();

        // Met wachtwoord wel.
        $met = new ZipArchive();
        $met->open($pad);
        $met->setPassword('Sterk-Wachtwoord-123');
        $this->assertStringContainsString('CREATE TABLE', $met->getFromName('database.sql'));
        $met->close();

        @unlink($pad);
    }

    public function test_beheerder_ziet_backupscherm_en_kan_downloaden(): void
    {
        $beheerder = User::where('rol', Rol::Beheerder)->first();

        $this->actingAs($beheerder)->get(route('backup'))->assertOk()->assertSee('Back-up genereren');

        $this->actingAs($beheerder)
            ->post(route('backup.download'), [
                'wachtwoord' => 'Sterk-Wachtwoord-123',
                'wachtwoord_confirmation' => 'Sterk-Wachtwoord-123',
            ])
            ->assertOk()
            ->assertHeader('content-type', 'application/zip');

        $this->assertDatabaseHas('audit_logs', ['veld' => 'recovery-backup', 'actie' => 'uitgifte']);
    }

    public function test_wachtwoord_verplicht_en_bevestiging_moet_kloppen(): void
    {
        $beheerder = User::where('rol', Rol::Beheerder)->first();

        $this->actingAs($beheerder)->post(route('backup.download'), [
            'wachtwoord' => 'kort',
            'wachtwoord_confirmation' => 'kort',
        ])->assertSessionHasErrors('wachtwoord');

        $this->actingAs($beheerder)->post(route('backup.download'), [
            'wachtwoord' => 'Sterk-Wachtwoord-123',
            'wachtwoord_confirmation' => 'Anders-456',
        ])->assertSessionHasErrors('wachtwoord');
    }

    public function test_backup_alleen_voor_beheerder(): void
    {
        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())->get(route('backup'))->assertForbidden();
        $this->actingAs(User::where('rol', Rol::Directie)->first())->get(route('backup'))->assertForbidden();
    }

    public function test_uitpakken_met_juist_wachtwoord_herstelt_bestanden(): void
    {
        $zip = Backup::maak('Wachtwoord-123!');
        $doel = storage_path('app/test-herstel-'.uniqid());

        $this->artisan('backup:uitpakken', [
            'archief' => $zip, '--wachtwoord' => 'Wachtwoord-123!', '--doel' => $doel,
        ])->assertSuccessful();

        $this->assertFileExists($doel.'/database.sql');
        $this->assertFileExists($doel.'/.env');

        \Illuminate\Support\Facades\File::deleteDirectory($doel);
        @unlink($zip);
    }

    public function test_uitpakken_met_onjuist_wachtwoord_faalt(): void
    {
        $zip = Backup::maak('Wachtwoord-123!');
        $doel = storage_path('app/test-herstel-fout-'.uniqid());

        $this->artisan('backup:uitpakken', [
            'archief' => $zip, '--wachtwoord' => 'FoutWachtwoord', '--doel' => $doel,
        ])->assertFailed();

        \Illuminate\Support\Facades\File::deleteDirectory($doel);
        @unlink($zip);
    }
}
