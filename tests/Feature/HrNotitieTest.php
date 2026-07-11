<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Medewerker;
use App\Models\MedewerkerNotitie;
use App\Models\User;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\HrSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module HR / Personeelszaken — interne notities per medewerker (logboek van
 * contactmomenten: e-mails, telefoongesprekken, gespreksverslagen). HR/Beheer
 * beheren; Bestuur leest mee; Studentenzaken heeft geen toegang.
 */
class HrNotitieTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;
    private Medewerker $medewerker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferentieSeeder::class, DocentSeeder::class, GebruikerSeeder::class, HrSeeder::class]);

        $this->hr = User::where('email', 'n.aslan@iuasr.nl')->firstOrFail();
        $this->medewerker = Medewerker::where('personeelsnummer', 'P260004')->firstOrFail();
    }

    public function test_hr_voegt_notitie_toe(): void
    {
        $this->actingAs($this->hr)
            ->post(route('medewerkers.notities.store', $this->medewerker), [
                'tekst' => 'Telefoongesprek gevoerd over de vakantieplanning.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('medewerker_notities', [
            'medewerker_id' => $this->medewerker->id,
            'gebruiker_id' => $this->hr->id,
            'tekst' => 'Telefoongesprek gevoerd over de vakantieplanning.',
        ]);
    }

    public function test_notitie_toont_datum_en_auteur(): void
    {
        MedewerkerNotitie::create([
            'medewerker_id' => $this->medewerker->id,
            'gebruiker_id' => $this->hr->id,
            'tekst' => 'E-mailwisseling over de arbeidsvoorwaarden.',
        ]);

        $this->actingAs($this->hr)->get(route('medewerkers.show', $this->medewerker))
            ->assertOk()
            ->assertSee('E-mailwisseling over de arbeidsvoorwaarden.')
            ->assertSee('Nadia Aslan')
            ->assertSee(now()->format('d-m-Y'));
    }

    public function test_hr_verwijdert_notitie(): void
    {
        $notitie = MedewerkerNotitie::create([
            'medewerker_id' => $this->medewerker->id,
            'gebruiker_id' => $this->hr->id,
            'tekst' => 'Tijdelijke notitie.',
        ]);

        $this->actingAs($this->hr)
            ->delete(route('medewerkers.notities.destroy', [$this->medewerker, $notitie]))
            ->assertRedirect();

        $this->assertDatabaseMissing('medewerker_notities', ['id' => $notitie->id]);
    }

    public function test_bestuur_leest_mee_maar_beheert_niet(): void
    {
        MedewerkerNotitie::create([
            'medewerker_id' => $this->medewerker->id,
            'gebruiker_id' => $this->hr->id,
            'tekst' => 'Verslag functioneringsgesprek.',
        ]);

        $bestuur = User::where('rol', Rol::Bestuur)->firstOrFail();

        // Bestuur ziet de notitie op de kaart...
        $this->actingAs($bestuur)->get(route('medewerkers.show', $this->medewerker))
            ->assertOk()
            ->assertSee('Verslag functioneringsgesprek.');

        // ...maar mag geen notitie toevoegen.
        $this->actingAs($bestuur)
            ->post(route('medewerkers.notities.store', $this->medewerker), ['tekst' => 'Mag niet.'])
            ->assertForbidden();
    }

    public function test_studentenzaken_heeft_geen_toegang(): void
    {
        $sz = User::where('rol', Rol::Studentenzaken)->firstOrFail();

        $this->actingAs($sz)
            ->post(route('medewerkers.notities.store', $this->medewerker), ['tekst' => 'Mag niet.'])
            ->assertForbidden();
    }
}
