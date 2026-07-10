<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischVakSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HandleidingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, SynthetischVakSeeder::class, GebruikerSeeder::class]);
    }

    public function test_medewerkershandleiding_is_voor_iedere_ingelogde_gebruiker(): void
    {
        $response = $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->get(route('handleiding.medewerkers'));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_technische_handleiding_voor_beheerder_en_bestuur(): void
    {
        $this->actingAs(User::where('rol', Rol::Beheerder)->first())
            ->get(route('handleiding.technisch'))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs(User::where('rol', Rol::Bestuur)->first())
            ->get(route('handleiding.technisch'))->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->get(route('handleiding.technisch'))->assertForbidden();
        $this->actingAs(User::where('rol', Rol::Directie)->first())
            ->get(route('handleiding.technisch'))->assertForbidden();
    }

    public function test_help_link_staat_in_de_paginabalk(): void
    {
        $this->actingAs(User::where('rol', Rol::Docent)->first())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('handleiding.medewerkers'))
            ->assertSee('Help');
    }
}
