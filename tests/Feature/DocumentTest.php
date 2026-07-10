<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischVakSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $sz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, SynthetischVakSeeder::class, GebruikerSeeder::class, SynthetischeStudentSeeder::class]);
        Storage::fake('local');
        $this->sz = User::where('rol', Rol::Studentenzaken)->first();
    }

    public function test_studentenzaken_uploadt_bekijkt_en_verwijdert_document(): void
    {
        $student = Student::first();
        $file = UploadedFile::fake()->create('diploma.pdf', 120, 'application/pdf');

        $this->actingAs($this->sz)
            ->post(route('studenten.documenten.upload', $student), ['soort' => 'diploma', 'bestand' => $file])
            ->assertRedirect();

        $doc = $student->documenten()->first();
        $this->assertNotNull($doc);
        $this->assertSame('diploma', $doc->soort);
        Storage::disk('local')->assertExists($doc->pad);

        // Bekijken/downloaden werkt en wordt gelogd.
        $this->actingAs($this->sz)->get(route('documenten.download', $doc))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['veld' => 'document']);

        // Verwijderen ruimt bestand én record op.
        $this->actingAs($this->sz)->delete(route('documenten.destroy', $doc))->assertRedirect();
        Storage::disk('local')->assertMissing($doc->pad);
        $this->assertSame(0, $student->documenten()->count());
    }

    public function test_ongeldig_bestandstype_wordt_geweigerd(): void
    {
        $student = Student::first();
        $file = UploadedFile::fake()->create('script.exe', 10);

        $this->actingAs($this->sz)
            ->post(route('studenten.documenten.upload', $student), ['soort' => 'diploma', 'bestand' => $file])
            ->assertSessionHasErrors('bestand');
        $this->assertSame(0, $student->documenten()->count());
    }

    public function test_later_aanleveren_markering_verschijnt_op_dashboard(): void
    {
        $student = Student::where('studentnummer', '261001')->first();

        $this->actingAs($this->sz)
            ->post(route('studenten.documenten.later', $student), ['documenten_later' => '1'])
            ->assertRedirect();
        $this->assertTrue($student->fresh()->documenten_later);

        $this->actingAs($this->sz)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Documenten later')
            ->assertSee($student->volledigeNaam());
    }

    public function test_docent_mag_geen_documenten_beheren(): void
    {
        $docent = User::where('rol', Rol::Docent)->first();
        $student = Student::first();

        $this->actingAs($docent)->post(route('studenten.documenten.upload', $student), [])->assertForbidden();
    }
}
