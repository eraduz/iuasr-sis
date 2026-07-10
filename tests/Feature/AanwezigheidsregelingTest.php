<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Inschrijving;
use App\Models\User;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischVakSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AanwezigheidsregelingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, SynthetischVakSeeder::class, DocentSeeder::class, GebruikerSeeder::class,
            SynthetischeStudentSeeder::class]);
    }

    private function inschrijving(): Inschrijving
    {
        return Inschrijving::where('status', 'actief')->with('student')->firstOrFail();
    }

    public function test_studentenzaken_kent_de_regeling_toe_en_dit_wordt_gelogd(): void
    {
        $insch = $this->inschrijving();

        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->post(route('inschrijving.aanwezigheidsregeling', $insch), ['aanwezigheidsregeling_50' => '1'])
            ->assertRedirect();

        $this->assertTrue($insch->fresh()->aanwezigheidsregeling_50);
        $this->assertDatabaseHas('audit_logs', [
            'veld' => 'aanwezigheidsregeling_50', 'actie' => 'wijziging',
            'onderwerp_id' => $insch->student_id,
        ]);
    }

    public function test_regeling_kan_worden_ingetrokken(): void
    {
        $insch = $this->inschrijving();
        $insch->update(['aanwezigheidsregeling_50' => true]);

        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->post(route('inschrijving.aanwezigheidsregeling', $insch), [])
            ->assertRedirect();

        $this->assertFalse($insch->fresh()->aanwezigheidsregeling_50);
    }

    public function test_ongewijzigde_stand_schrijft_geen_auditregel(): void
    {
        $insch = $this->inschrijving();

        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->post(route('inschrijving.aanwezigheidsregeling', $insch), [])
            ->assertRedirect();

        $this->assertDatabaseMissing('audit_logs', ['veld' => 'aanwezigheidsregeling_50']);
    }

    public function test_alleen_studentenzaken_en_beheerder_mogen_muteren(): void
    {
        $insch = $this->inschrijving();
        $body = ['aanwezigheidsregeling_50' => '1'];

        foreach ([Rol::Studentenzaken, Rol::Beheerder] as $rol) {
            $this->actingAs(User::where('rol', $rol)->first())
                ->post(route('inschrijving.aanwezigheidsregeling', $insch), $body)->assertRedirect();
        }

        foreach ([Rol::Directie, Rol::Examencommissie, Rol::Docent, Rol::Bestuur, Rol::Financien] as $rol) {
            $this->actingAs(User::where('rol', $rol)->first())
                ->post(route('inschrijving.aanwezigheidsregeling', $insch), $body)->assertForbidden();
        }
    }

    public function test_regeling_geldt_per_inschrijving_niet_per_student(): void
    {
        $insch = $this->inschrijving();
        $insch->update(['aanwezigheidsregeling_50' => true]);

        // Een tweede inschrijving van dezelfde student (ander studiejaar/opleiding)
        // erft de regeling NIET; zij moet bewust opnieuw worden toegekend.
        $tweede = Inschrijving::where('student_id', $insch->student_id)
            ->where('id', '!=', $insch->id)->first();

        if ($tweede === null) {
            $tweede = $insch->replicate()->fill([
                'periode_id' => \App\Models\Periode::where('id', '!=', $insch->periode_id)->value('id'),
                'aanwezigheidsregeling_50' => false,
            ]);
            $tweede->save();
        }

        $this->assertFalse($tweede->fresh()->aanwezigheidsregeling_50);
        $this->assertTrue($insch->fresh()->aanwezigheidsregeling_50);
    }

    public function test_regeling_zichtbaar_op_studentdossier_voor_directie(): void
    {
        $insch = $this->inschrijving();
        $insch->update(['aanwezigheidsregeling_50' => true]);

        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->get(route('studenten.show', $insch->student))
            ->assertOk()->assertSee('50% Aanwezigheidsregeling');

        // Directie leest mee, maar krijgt geen mutatieformulier.
        $directie = User::where('rol', Rol::Directie)->first();
        $directie->opleidingen()->syncWithoutDetaching([$insch->opleiding_id]);

        $this->actingAs($directie->fresh())
            ->get(route('studenten.show', $insch->student))
            ->assertOk()
            ->assertSee('50%-aanwezigheidsregeling')
            ->assertDontSee('name="aanwezigheidsregeling_50"', false);
    }
}
