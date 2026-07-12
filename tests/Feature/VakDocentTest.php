<?php

namespace Tests\Feature;

use App\Models\Docent;
use App\Models\Opleiding;
use App\Models\Vak;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\DocentSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\VakDocentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Docenten worden per vak gekoppeld uit de studiegidsen (ISLTH + MGV) via
 * database/data/vakdocenten.csv. Match op genormaliseerde achternaam; stage-/
 * scriptiecoördinatie-vakken krijgen bewust geen docent.
 */
class VakDocentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, CurriculumSeeder::class, DocentSeeder::class, VakDocentSeeder::class]);
    }

    private function vak(string $code, string $opleiding): Vak
    {
        $opleidingId = Opleiding::where('code', $opleiding)->value('id');

        return Vak::where('code', $code)->where('opleiding_id', $opleidingId)->firstOrFail();
    }

    public function test_islth_vak_krijgt_de_juiste_docent(): void
    {
        $this->assertSame('Abba', $this->vak('B-AR01-15', 'ISLTH')->docent?->achternaam);
        $this->assertSame('Abu Alhija', $this->vak('B-QR02', 'ISLTH')->docent?->achternaam);
    }

    public function test_mgv_vak_krijgt_de_juiste_docent(): void
    {
        $this->assertSame('Yalçınkaya', $this->vak('M-GV01', 'MGV')->docent?->achternaam);
    }

    public function test_diacritische_achternaam_matcht(): void
    {
        // De gids schrijft "Biçer-Uslu"; de docententabel "Bicer-Uslu".
        $this->assertSame('Bicer-Uslu', $this->vak('M-GV08', 'MGV')->docent?->achternaam);
    }

    public function test_ontbrekende_docent_wordt_aangemaakt(): void
    {
        $bouy = Docent::where('achternaam', 'Bouyazdouzen')->first();
        $this->assertNotNull($bouy, 'Bouyazdouzen hoort te bestaan (via DocentSeeder).');
        $this->assertGreaterThan(0, $bouy->vakken()->count());
        $this->assertSame($bouy->id, $this->vak('B-KL03', 'ISLTH')->docent_id);
    }

    public function test_stage_en_scriptie_hebben_geen_individuele_docent(): void
    {
        $this->assertNull($this->vak('B-ST01', 'ISLTH')->docent_id); // stagecoördinatie
        $this->assertNull($this->vak('B-BR01', 'ISLTH')->docent_id); // scriptiecoördinatie
        $this->assertNull($this->vak('M-GV17', 'MGV')->docent_id);   // scriptiecoördinator
    }

    public function test_docent_ziet_gekoppeld_vak_via_relatie(): void
    {
        $abba = Docent::where('achternaam', 'Abba')->firstOrFail();
        $codes = $abba->vakken->pluck('code')->all();
        $this->assertContains('B-AR01-15', $codes);
    }
}
