<?php

namespace Tests\Feature;

use App\Models\HrChecklisttaak;
use App\Models\Medewerker;
use App\Models\User;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\HrSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module HR / Personeelszaken — Fase E (onboarding/offboarding). Bewaakt het
 * starten uit het sjabloon, het afvinken, het toevoegen en de team-scoping.
 */
class HrChecklistTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class, HrSeeder::class]);

        $this->hr = User::where('email', 'n.aslan@iuasr.nl')->firstOrFail();
        $this->manager = User::where('email', 'r.smit@iuasr.nl')->firstOrFail();
    }

    public function test_offboarding_starten_maakt_sjabloontaken(): void
    {
        $medewerker = Medewerker::where('personeelsnummer', 'P260005')->firstOrFail();

        $this->actingAs($this->hr)->post(route('checklist.start', $medewerker), ['soort' => 'offboarding'])->assertRedirect();

        $this->assertGreaterThanOrEqual(5, $medewerker->checklisttaken()->where('soort', 'offboarding')->count());
    }

    public function test_start_is_idempotent(): void
    {
        $medewerker = Medewerker::where('personeelsnummer', 'P260005')->firstOrFail();

        $this->actingAs($this->hr)->post(route('checklist.start', $medewerker), ['soort' => 'onboarding']);
        $aantal = $medewerker->checklisttaken()->where('soort', 'onboarding')->count();
        $this->actingAs($this->hr)->post(route('checklist.start', $medewerker), ['soort' => 'onboarding']);

        $this->assertSame($aantal, $medewerker->checklisttaken()->where('soort', 'onboarding')->count());
    }

    public function test_taak_afvinken_en_toevoegen(): void
    {
        // Johan heeft al een onboarding (seed).
        $taak = HrChecklisttaak::where('gereed', false)->firstOrFail();

        $this->actingAs($this->hr)->post(route('checklist.toggle', $taak))->assertRedirect();
        $this->assertTrue($taak->fresh()->gereed);
        $this->assertNotNull($taak->fresh()->gereed_op);

        $medewerker = $taak->medewerker;
        $this->actingAs($this->hr)->post(route('checklist.store', $medewerker), [
            'soort' => 'onboarding', 'titel' => 'Rondleiding gebouw',
        ])->assertRedirect();
        $this->assertDatabaseHas('hr_checklisttaken', ['medewerker_id' => $medewerker->id, 'titel' => 'Rondleiding gebouw']);
    }

    public function test_manager_kan_geen_checklist_buiten_team_starten(): void
    {
        $fadwa = Medewerker::where('personeelsnummer', 'P260005')->firstOrFail(); // niet Rubens team
        $this->actingAs($this->manager)->post(route('checklist.start', $fadwa), ['soort' => 'onboarding'])->assertForbidden();
    }

    public function test_studentenzaken_heeft_geen_toegang(): void
    {
        $sz = User::where('rol', \App\Enums\Rol::Studentenzaken)->firstOrFail();
        $medewerker = Medewerker::where('personeelsnummer', 'P260005')->firstOrFail();
        $this->actingAs($sz)->post(route('checklist.start', $medewerker), ['soort' => 'onboarding'])->assertForbidden();
    }
}
