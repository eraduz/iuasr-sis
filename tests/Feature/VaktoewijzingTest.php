<?php

namespace Tests\Feature;

use App\Enums\InschrijvingStatus;
use App\Enums\Rol;
use App\Models\Inschrijving;
use App\Models\Opleiding;
use App\Models\Periode;
use App\Models\Student;
use App\Models\User;
use App\Models\Vak;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischVakSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaktoewijzingTest extends TestCase
{
    use RefreshDatabase;

    private User $sz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, SynthetischVakSeeder::class, GebruikerSeeder::class]);
        $this->sz = User::where('rol', Rol::Studentenzaken)->first();
        // Deze test gaat over vaktoewijzing, niet over de doorstroomtoets: zet de
        // EC-overgangsdrempel uit zodat een leerjaarwissel niet wordt geblokkeerd.
        Opleiding::query()->update(['ec_overgang_drempel' => null]);
    }

    private function aantalVakken(int $leerjaar): int
    {
        return Vak::where('opleiding_id', Opleiding::where('code', 'ISLTH')->value('id'))
            ->where('actief', true)->where('leerjaar', $leerjaar)->count();
    }

    public function test_inschrijven_wijst_de_vakken_van_het_studiejaar_automatisch_toe(): void
    {
        $this->actingAs($this->sz)->post('/inschrijven', [
            'voornaam' => 'Auto', 'achternaam' => 'Toewijzing',
            'opleiding_id' => Opleiding::where('code', 'ISLTH')->value('id'),
            'periode_id' => Periode::where('actief', true)->value('id'),
            'leerjaar' => 1,
            'inschrijfdatum' => '2026-09-01',
        ]);

        $insch = Student::where('achternaam', 'Toewijzing')->first()->inschrijvingen()->first();
        $this->assertGreaterThan(0, $this->aantalVakken(1));
        $this->assertSame($this->aantalVakken(1), $insch->vaktoewijzingen()->count());
    }

    public function test_herinschrijven_wijst_de_vakken_van_het_nieuwe_jaar_toe(): void
    {
        $student = Student::create(['studentnummer' => '260800', 'voornaam' => 'Her', 'achternaam' => 'Inschrijving']);
        Inschrijving::create([
            'student_id' => $student->id,
            'opleiding_id' => Opleiding::where('code', 'ISLTH')->value('id'),
            'periode_id' => Periode::where('actief', true)->value('id'),
            'leerjaar' => 1, 'status' => InschrijvingStatus::Actief, 'inschrijfdatum' => '2026-09-01',
        ]);

        $this->actingAs($this->sz)->post(route('herinschrijven.store', $student), [
            'opleiding_id' => Opleiding::where('code', 'ISLTH')->value('id'),
            'periode_id' => Periode::where('code', '2026-2027')->value('id'),
            'leerjaar' => 2,
            'inschrijfdatum' => '2027-09-01',
        ]);

        $nieuw = $student->inschrijvingen()->where('leerjaar', 2)->first();
        $this->assertNotNull($nieuw);
        $this->assertSame($this->aantalVakken(2), $nieuw->vaktoewijzingen()->count());
    }

    public function test_studentenadministratie_kan_de_toewijzing_aanpassen(): void
    {
        $student = Student::create(['studentnummer' => '260801', 'voornaam' => 'Aan', 'achternaam' => 'Passen']);
        $insch = Inschrijving::create([
            'student_id' => $student->id,
            'opleiding_id' => Opleiding::where('code', 'ISLTH')->value('id'),
            'periode_id' => Periode::where('actief', true)->value('id'),
            'leerjaar' => 1, 'status' => InschrijvingStatus::Actief, 'inschrijfdatum' => '2026-09-01',
        ]);
        \App\Support\Vaktoewijzer::wijsToe($insch);
        $this->assertSame($this->aantalVakken(1), $insch->vaktoewijzingen()->count());

        // Beperk tot twee vakken.
        $twee = Vak::where('leerjaar', 1)->take(2)->pluck('id')->all();
        $this->actingAs($this->sz)->put(route('inschrijving.vakken.update', $insch), ['vak_ids' => $twee])
            ->assertRedirect(route('studenten.show', $student->id));

        $this->assertSame(2, $insch->vaktoewijzingen()->count());
    }

    public function test_vakstructuur_alleen_voor_studentenadministratie(): void
    {
        $this->actingAs($this->sz)->get(route('vakstructuur'))->assertOk();

        $fin = User::where('rol', Rol::Financien)->first();
        $this->actingAs($fin)->get(route('vakstructuur'))->assertForbidden();

        $docent = User::where('rol', Rol::Docent)->first();
        $this->actingAs($docent)->get(route('vakstructuur'))->assertForbidden();
    }

    public function test_vak_toevoegen_aan_de_structuur(): void
    {
        $this->actingAs($this->sz)->post(route('vakstructuur.store'), [
            'opleiding_id' => Opleiding::where('code', 'ISLTH')->value('id'),
            'code' => 'ISLTH-NIEUW-101', 'naam' => 'Nieuw vak', 'ec' => 6, 'leerjaar' => 1, 'blok' => 4,
        ])->assertRedirect();

        $this->assertDatabaseHas('vakken', ['code' => 'ISLTH-NIEUW-101', 'leerjaar' => 1, 'blok' => 4]);
    }

    public function test_toegewezen_vak_kan_niet_worden_verwijderd(): void
    {
        $student = Student::create(['studentnummer' => '260802', 'voornaam' => 'X', 'achternaam' => 'Y']);
        $insch = Inschrijving::create([
            'student_id' => $student->id,
            'opleiding_id' => Opleiding::where('code', 'ISLTH')->value('id'),
            'periode_id' => Periode::where('actief', true)->value('id'),
            'leerjaar' => 1, 'status' => InschrijvingStatus::Actief, 'inschrijfdatum' => '2026-09-01',
        ]);
        \App\Support\Vaktoewijzer::wijsToe($insch);
        $vak = Vak::where('leerjaar', 1)->first();

        $this->actingAs($this->sz)->delete(route('vakstructuur.destroy', $vak));
        $this->assertDatabaseHas('vakken', ['id' => $vak->id]); // historie beschermd
    }
}
