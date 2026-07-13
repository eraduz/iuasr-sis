<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischVakSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VersieTest extends TestCase
{
    use RefreshDatabase;

    public function test_versie_is_ingesteld(): void
    {
        $this->assertNotEmpty(config('sis.versie'));
    }

    public function test_versie_staat_onderaan_de_pagina(): void
    {
        $this->seed([ReferentieSeeder::class, SynthetischVakSeeder::class, GebruikerSeeder::class]);

        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('versie '.config('sis.versie'));
    }
}
