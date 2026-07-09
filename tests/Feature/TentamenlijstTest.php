<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Docent;
use App\Models\OndertekendDocument;
use App\Models\Resultaat;
use App\Models\User;
use App\Models\Vak;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TentamenlijstTest extends TestCase
{
    use RefreshDatabase;

    private Vak $vak;
    private User $docent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, GebruikerSeeder::class, SynthetischeStudentSeeder::class]);
        $this->vak = Vak::where('code', 'ISLTH-ARA-101')->first();
        $this->docent = User::where('rol', Rol::Docent)->first();
    }

    private function geefCijfer(float $cijfer = 7.0): void
    {
        $insch = $this->vak->deelnemers()->first();
        $od = $this->vak->toetsonderdelen->first();
        Resultaat::create([
            'inschrijving_id' => $insch->id, 'student_id' => $insch->student_id,
            'toetsonderdeel_id' => $od->id, 'poging' => 'tentamen', 'poging_nr' => 1,
            'cijfer' => $cijfer, 'voldoende' => $cijfer >= 5.5,
        ]);
    }

    public function test_tentamenlijst_toont_deelnemers_en_samenvatting(): void
    {
        $this->geefCijfer();
        $deelnemer = $this->vak->deelnemers()->first()->student;

        $this->actingAs($this->docent)->get(route('vakken.tentamenlijst', $this->vak))
            ->assertOk()
            ->assertSee('Presentielijst')
            ->assertSee($deelnemer->volledigeNaam())
            ->assertSee('Handtekening')
            ->assertDontSee('Eindcijfer'); // privacy: geen cijfers/EC op de presentielijst
    }

    public function test_ondertekende_tentamenlijst_pdf(): void
    {
        Storage::fake('local');
        $this->geefCijfer();

        $this->actingAs(User::where('rol', Rol::Examencommissie)->first())
            ->post(route('vakken.tentamenlijst.pdf', $this->vak), ['ontvanger' => 'Examencommissie'])
            ->assertOk();

        $doc = OndertekendDocument::where('type', 'tentamenlijst')->first();
        $this->assertNotNull($doc);
        $this->assertSame(64, strlen($doc->sha256));
    }

    public function test_rolscheiding_tentamenlijst(): void
    {
        // Docent: eigen vak wel, andermans vak niet.
        $this->actingAs($this->docent)->get(route('vakken.tentamenlijst', $this->vak))->assertOk();

        $andere = Docent::create(['code' => 'DOC-098', 'achternaam' => 'Anders']);
        $anderVak = Vak::create([
            'opleiding_id' => $this->vak->opleiding_id, 'docent_id' => $andere->id,
            'code' => 'X-998', 'naam' => 'Ander vak', 'ec' => 6, 'leerjaar' => 1, 'actief' => true,
        ]);
        $this->actingAs($this->docent)->get(route('vakken.tentamenlijst', $anderVak))->assertForbidden();

        // Examencommissie/Directie wel; Studentenzaken niet (middleware).
        $this->actingAs(User::where('rol', Rol::Examencommissie)->first())->get(route('vakken.tentamenlijst', $this->vak))->assertOk();
        $this->actingAs(User::where('rol', Rol::Directie)->first())->get(route('vakken.tentamenlijst', $this->vak))->assertOk();
        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())->get(route('vakken.tentamenlijst', $this->vak))->assertForbidden();
    }
}
