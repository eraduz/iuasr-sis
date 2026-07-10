<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Mail\ResultatenCijferlijstMail;
use App\Models\Resultaat;
use App\Models\Student;
use App\Models\User;
use App\Models\Vak;
use Database\Seeders\DocentSeeder;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\ReferentieSeeder;
use Database\Seeders\SynthetischVakSeeder;
use Database\Seeders\SynthetischeStudentSeeder;
use Database\Seeders\VaktoewijzingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResultatenMailTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;
    private int $opleidingId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, SynthetischVakSeeder::class, DocentSeeder::class, GebruikerSeeder::class,
            SynthetischeStudentSeeder::class, VaktoewijzingSeeder::class]);

        $this->student = Student::where('studentnummer', '261001')->first();
        $this->student->update(['email' => 'yasmin@example.test']);

        // Eén vastgesteld (definitief) resultaat, zodat de student in aanmerking komt.
        $insch = $this->student->inschrijvingen()->first();
        $this->opleidingId = $insch->opleiding_id;
        $vak = Vak::where('opleiding_id', $insch->opleiding_id)->where('leerjaar', $insch->leerjaar)
            ->with('toetsonderdelen')->first();
        Resultaat::create([
            'inschrijving_id' => $insch->id, 'student_id' => $this->student->id,
            'toetsonderdeel_id' => $vak->toetsonderdelen->first()->id,
            'poging' => 'tentamen', 'poging_nr' => 1, 'cijfer' => 7.5, 'voldoende' => true, 'definitief' => true,
        ]);
    }

    private function examencommissie(): User
    {
        return User::where('rol', Rol::Examencommissie)->first();
    }

    public function test_overzicht_toont_ontvangers(): void
    {
        $this->actingAs($this->examencommissie())
            ->get(route('resultaten-mailen', ['opleiding_id' => $this->opleidingId]))
            ->assertOk()
            ->assertSee('Ontvangers')
            ->assertSee($this->student->volledigeNaam())
            ->assertSee('yasmin@example.test');
    }

    public function test_versturen_mailt_alleen_studenten_met_vastgestelde_resultaten(): void
    {
        Mail::fake();
        Storage::fake('local');

        $this->actingAs($this->examencommissie())
            ->post(route('resultaten-mailen.versturen'), ['opleiding_id' => $this->opleidingId])
            ->assertRedirect();

        // Precies één student (261001) komt in aanmerking; de rest heeft geen definitieve resultaten.
        Mail::assertSent(ResultatenCijferlijstMail::class, 1);
        Mail::assertSent(ResultatenCijferlijstMail::class, fn ($m) => $m->hasTo('yasmin@example.test'));
        $this->assertDatabaseHas('audit_logs', ['veld' => 'resultaten-email', 'actie' => 'uitgifte']);
    }

    public function test_student_zonder_emailadres_wordt_overgeslagen(): void
    {
        Mail::fake();
        Storage::fake('local');
        $this->student->update(['email' => null, 'email_prive' => null]);

        $this->actingAs($this->examencommissie())
            ->post(route('resultaten-mailen.versturen'), ['opleiding_id' => $this->opleidingId])
            ->assertRedirect();

        Mail::assertNothingSent();
    }

    public function test_rolscheiding_alleen_cijferinzage(): void
    {
        $this->actingAs($this->examencommissie())
            ->get(route('resultaten-mailen', ['opleiding_id' => $this->opleidingId]))->assertOk();
        $this->actingAs(User::where('rol', Rol::Studentenzaken)->first())
            ->get(route('resultaten-mailen', ['opleiding_id' => $this->opleidingId]))->assertForbidden();
    }
}
