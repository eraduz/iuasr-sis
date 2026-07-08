<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Enums\VrijstellingsbesluitStatus;
use App\Models\Student;
use App\Models\User;
use App\Models\Vak;
use App\Models\Vaktoewijzing;
use App\Models\Vrijstellingsbesluit;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Database\Seeders\VaktoewijzingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VrijstellingsbesluitTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;
    private Vaktoewijzing $toewijzing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class,
            SynthetischeStudentSeeder::class, VaktoewijzingSeeder::class]);
        $this->student = Student::where('studentnummer', '261001')->first();
        $this->toewijzing = $this->student->inschrijvingen()->first()
            ->vaktoewijzingen()->where('vrijgesteld', false)->with('vak')->firstOrFail();
    }

    private function besluitData(?int $vakId = null): array
    {
        return [
            'vak_id' => $vakId ?? $this->toewijzing->vak_id,
            'grondslag' => 'eerder_behaald',
            'besluit' => 'EC-2026-555',
            'besluit_datum' => '2026-09-10',
            'toelichting' => 'Test',
        ];
    }

    public function test_examencommissie_stuurt_besluit_naar_studentenzaken(): void
    {
        $this->actingAs(User::where('rol', Rol::Examencommissie)->first())
            ->post(route('vrijstellingsbesluiten.store', $this->student), $this->besluitData())
            ->assertRedirect();

        $besluit = Vrijstellingsbesluit::first();
        $this->assertNotNull($besluit);
        $this->assertSame(VrijstellingsbesluitStatus::Open, $besluit->status);
        $this->assertDatabaseHas('audit_logs', ['veld' => 'vrijstellingsbesluit', 'actie' => 'aanmaak']);
    }

    public function test_studentenzaken_verwerkt_besluit_legt_vrijstelling_vast(): void
    {
        $besluit = Vrijstellingsbesluit::create($this->besluitData() + [
            'student_id' => $this->student->id,
            'status' => 'open', 'aangemaakt_door_id' => User::where('rol', Rol::Examencommissie)->first()->id,
        ]);

        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->post(route('vrijstellingsbesluiten.verwerken', $besluit))
            ->assertRedirect();

        $this->assertTrue($this->toewijzing->fresh()->vrijgesteld);
        $this->assertSame('EC-2026-555', $this->toewijzing->fresh()->vrijstelling_besluit);
        $this->assertSame(VrijstellingsbesluitStatus::Verwerkt, $besluit->fresh()->status);
        $this->assertSame($this->toewijzing->id, $besluit->fresh()->vaktoewijzing_id);
    }

    public function test_verwerken_faalt_als_vak_niet_is_toegewezen(): void
    {
        // Een vak van dezelfde opleiding dat NIET aan de student is toegewezen.
        $nietToegewezen = Vak::create([
            'opleiding_id' => $this->toewijzing->vak->opleiding_id, 'code' => 'X-777',
            'naam' => 'Ongekoppeld vak', 'ec' => 5, 'leerjaar' => 1, 'actief' => true,
        ]);
        $besluit = Vrijstellingsbesluit::create($this->besluitData($nietToegewezen->id) + [
            'student_id' => $this->student->id,
            'status' => 'open', 'aangemaakt_door_id' => User::where('rol', Rol::Examencommissie)->first()->id,
        ]);

        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->post(route('vrijstellingsbesluiten.verwerken', $besluit))
            ->assertRedirect()->assertSessionHas('fout');

        $this->assertSame(VrijstellingsbesluitStatus::Open, $besluit->fresh()->status);
    }

    public function test_rolscheiding_besluit_workflow(): void
    {
        // Studentenzaken mag GEEN besluit aanmaken (dat is de examencommissie).
        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->post(route('vrijstellingsbesluiten.store', $this->student), $this->besluitData())
            ->assertForbidden();

        // Examencommissie mag NIET verwerken (dat doet Studentenzaken).
        $besluit = Vrijstellingsbesluit::create($this->besluitData() + [
            'student_id' => $this->student->id,
            'status' => 'open', 'aangemaakt_door_id' => User::where('rol', Rol::Examencommissie)->first()->id,
        ]);
        $this->actingAs(User::where('rol', Rol::Examencommissie)->first())
            ->post(route('vrijstellingsbesluiten.verwerken', $besluit))
            ->assertForbidden();
    }
}
