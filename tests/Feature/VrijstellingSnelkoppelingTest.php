<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Database\Seeders\SynthetischVakSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Snelkoppeling 'Vrijstelling' voor de examencommissie: direct naar de
 * studentenlijst (met doel=vrijstelling), en van daaruit rechtstreeks naar het
 * vrijstellingsformulier op het studentdossier (#vrijstelling).
 */
class VrijstellingSnelkoppelingTest extends TestCase
{
    use RefreshDatabase;

    private User $ec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, GebruikerSeeder::class, SynthetischVakSeeder::class, SynthetischeStudentSeeder::class]);
        $this->ec = User::where('rol', Rol::Examencommissie)->firstOrFail();
    }

    public function test_vrijstellingmodus_toont_banner_en_diepe_link(): void
    {
        $this->actingAs($this->ec)->get(route('studenten.index', ['doel' => 'vrijstelling']))
            ->assertOk()
            ->assertSee('Kies een student om een')
            ->assertSee('#vrijstelling');
    }

    public function test_gewone_studentenlijst_heeft_geen_vrijstellingbanner(): void
    {
        $this->actingAs($this->ec)->get(route('studenten.index'))
            ->assertOk()
            ->assertDontSee('Kies een student om een');
    }

    public function test_dossier_heeft_het_vrijstellingsanker(): void
    {
        $student = Student::first();
        $this->actingAs($this->ec)->get(route('studenten.show', $student))
            ->assertOk()->assertSee('id="vrijstelling"', false);
    }
}
