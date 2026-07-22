<?php

namespace Tests\Feature;

use App\Enums\CijferlijstStatus;
use App\Enums\Rol;
use App\Models\Cijferlijst;
use App\Models\Inschrijving;
use App\Models\Periode;
use App\Models\Resultaat;
use App\Models\User;
use App\Models\Vak;
use App\Support\Cijferberekening;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Database\Seeders\SynthetischVakSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end simulatie van de cijferworkflow over de vier betrokken rollen:
 * Docent voert in en dient in → Examencommissie ziet en stelt vast → Directie
 * (eigen opleiding) leest mee → Studentenzaken is afgeschermd (rolscheiding).
 *
 * Deze test bewaakt met name de integratie tussen de cijferINVOER (docent) en
 * de STUDENTPAGINA (examencommissie/directie): een cijfer dat de docent invoert
 * moet daar zichtbaar zijn. Ook de rolscheiding en de EC-toekenning worden hier
 * end-to-end gecontroleerd.
 */
class CijferworkflowIntegratieTest extends TestCase
{
    use RefreshDatabase;

    private Vak $vak;
    private User $docent;
    private User $examen;
    private User $directie;
    private User $sz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, SynthetischVakSeeder::class, GebruikerSeeder::class, SynthetischeStudentSeeder::class]);

        $this->vak = Vak::where('code', 'ISLTH-ARA-101')->with(['toetsonderdelen', 'opleiding'])->firstOrFail();

        // De docent die dit vak geeft (eigen-vak-regel).
        $this->docent = User::where('rol', Rol::Docent)->where('docent_id', $this->vak->docent_id)->firstOrFail();

        $this->examen = User::firstOrCreate(['email' => 'ec@iuasr.test'], ['naam' => 'EC', 'rol' => Rol::Examencommissie]);

        // Directie is opleidinggebonden: koppel aan de opleiding van het vak,
        // anders valt de student buiten het zicht (zichtbaarVoor => 403).
        $this->directie = User::where('rol', Rol::Directie)->firstOrFail();
        $this->directie->opleidingen()->syncWithoutDetaching([$this->vak->opleiding_id]);

        $this->sz = User::where('rol', Rol::Studentenzaken)->firstOrFail();
    }

    private function lijst(): Cijferlijst
    {
        return Cijferlijst::voor($this->vak, Periode::where('actief', true)->firstOrFail());
    }

    /** Payload met voor élk toetsonderdeel hetzelfde 1e-poging-cijfer. */
    private function alleOnderdelen(int $inschId, string $cijfer): array
    {
        $payload = ['cijfer' => [$inschId => []]];
        foreach ($this->vak->toetsonderdelen as $od) {
            $payload['cijfer'][$inschId][$od->id] = $cijfer;
        }

        return $payload;
    }

    public function test_docent_invoer_is_zichtbaar_voor_examencommissie_en_directie_op_de_studentpagina(): void
    {
        /** @var Inschrijving $insch */
        $insch = $this->vak->deelnemers()->first();
        $this->assertNotNull($insch, 'Er is minstens één actieve deelnemer nodig voor deze test.');
        $student = $insch->student;

        // ---- 1) DOCENT: invoeren én in één handeling indienen ------------------
        $payload = $this->alleOnderdelen($insch->id, '7,0');
        $payload['na_opslaan'] = 'indienen';

        $this->actingAs($this->docent)
            ->post(route('vakken.cijfers.opslaan', $this->vak), $payload)
            ->assertRedirect(route('vakken.cijfers', $this->vak));

        // De resultaten zijn opgeslagen mét beide koppelingen (student_id én
        // inschrijving_id) — anders verschijnen ze niet op de studentpagina.
        foreach ($this->vak->toetsonderdelen as $od) {
            $this->assertDatabaseHas('resultaten', [
                'inschrijving_id' => $insch->id,
                'student_id' => $student->id,
                'toetsonderdeel_id' => $od->id,
                'poging_nr' => 1,
                'cijfer' => 7.0,
            ]);
        }
        $this->assertSame(CijferlijstStatus::Ingediend, $this->lijst()->status);

        // ---- 2) EXAMENCOMMISSIE: cijferoverzicht en invoer-/inzagepagina --------
        $this->actingAs($this->examen)->get(route('cijferoverzicht'))
            ->assertOk()->assertSee($this->vak->code);
        $this->actingAs($this->examen)->get(route('vakken.cijfers', $this->vak))
            ->assertOk()->assertSee('7,0');

        // ---- 3) KERN VAN DE MELDING: cijfers op de STUDENTPAGINA ---------------
        // Voor zowel de examencommissie als de directie (eigen opleiding).
        foreach ([$this->examen, $this->directie] as $kijker) {
            $this->actingAs($kijker)->get(route('studenten.show', $student))
                ->assertOk()
                ->assertSee($this->vak->code)
                ->assertSee('EC totaal behaald')
                ->assertDontSee('Nog geen resultaten geregistreerd voor deze student.');
        }

        // ---- 4) ROLSCHEIDING: Studentenzaken ziet GEEN cijfers -----------------
        $this->actingAs($this->sz)->get(route('studenten.show', $student))
            ->assertOk()
            ->assertSee('Cijfers zijn afgeschermd')
            ->assertDontSee('EC totaal behaald');

        // ---- 5) ROLSCHEIDING: de docent komt niet op de studentpagina ----------
        $this->actingAs($this->docent)->get(route('studenten.show', $student))->assertForbidden();

        // ---- 6) EXAMENCOMMISSIE stelt vast -> definitief; cijfers blijven staan -
        $this->actingAs($this->examen)->post(route('vakken.cijfers.vaststellen', $this->vak))->assertRedirect();
        $this->assertSame(CijferlijstStatus::Vastgesteld, $this->lijst()->status);
        $this->assertDatabaseHas('resultaten', ['inschrijving_id' => $insch->id, 'definitief' => true]);

        $this->actingAs($this->examen)->get(route('studenten.show', $student))
            ->assertOk()->assertDontSee('Nog geen resultaten geregistreerd voor deze student.');

        // ---- 7) Transcript (cijferlijst) toont hetzelfde vak -------------------
        $this->actingAs($this->examen)->get(route('cijferlijst', ['student' => $student->id]))
            ->assertOk()->assertSee($this->vak->code);
    }

    public function test_directie_van_andere_opleiding_ziet_de_student_niet(): void
    {
        /** @var Inschrijving $insch */
        $insch = $this->vak->deelnemers()->first();
        $student = $insch->student;

        // Directie zonder koppeling aan de opleiding van deze student.
        $andereDirectie = User::create(['naam' => 'Dir Andere', 'email' => 'dir2@iuasr.test', 'rol' => Rol::Directie]);

        $this->actingAs($andereDirectie)->get(route('studenten.show', $student))->assertForbidden();
    }

    public function test_beste_van_alle_pogingen_bepaalt_het_eindcijfer_op_de_studentpagina(): void
    {
        /** @var Inschrijving $insch */
        $insch = $this->vak->deelnemers()->first();
        $student = $insch->student;
        $eerste = $this->vak->toetsonderdelen->first();

        // Eerste onderdeel: 1e poging onvoldoende, 2e herkansing voldoende.
        $payload = [
            'cijfer' => [$insch->id => [$eerste->id => '4,0']],
            'herkansing2' => [$insch->id => [$eerste->id => '8,0']],
        ];
        // Overige onderdelen voldoende, zodat het vak een volledig eindcijfer krijgt.
        foreach ($this->vak->toetsonderdelen->slice(1) as $od) {
            $payload['cijfer'][$insch->id][$od->id] = '7,0';
        }

        $this->actingAs($this->docent)->post(route('vakken.cijfers.opslaan', $this->vak), $payload)->assertRedirect();

        // De beste poging (8,0) voor het eerste onderdeel telt mee.
        $eigen = Resultaat::where('inschrijving_id', $insch->id)->where('toetsonderdeel_id', $eerste->id)->get();
        $this->assertCount(2, $eigen);
        $this->assertEqualsWithDelta(8.0, (float) Cijferberekening::beste($eigen, $eerste->id)->cijfer, 0.01);

        // Op de studentpagina staat een numeriek eindcijfer (geen 'onvolledig'/leeg).
        $this->actingAs($this->examen)->get(route('studenten.show', $student))
            ->assertOk()
            ->assertSee('EC totaal behaald')
            ->assertDontSee('Nog geen resultaten geregistreerd voor deze student.');
    }

    public function test_multirol_docent_en_examencommissie_wordt_niet_geblokkeerd(): void
    {
        // Een gebruiker met Docent als primaire rol én Examencommissie als extra
        // rol moet met de examencommissie-rechten kunnen inzien/vaststellen.
        $this->docent->rolToewijzingen()->create(['rol' => Rol::Examencommissie]);
        $this->docent->refresh();

        $insch = $this->vak->deelnemers()->first();
        $payload = $this->alleOnderdelen($insch->id, '7,0');
        $payload['na_opslaan'] = 'indienen';
        $this->actingAs($this->docent)->post(route('vakken.cijfers.opslaan', $this->vak), $payload)->assertRedirect();

        // Dankzij de extra rol mag deze gebruiker nu ook het cijferoverzicht en
        // de studentpagina met cijfers openen.
        $this->actingAs($this->docent)->get(route('cijferoverzicht'))->assertOk();
        $this->actingAs($this->docent)->get(route('studenten.show', $insch->student))
            ->assertOk()->assertSee('EC totaal behaald');
    }
}
