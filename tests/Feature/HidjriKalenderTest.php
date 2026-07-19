<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use App\Support\Hidjrikalender;
use Carbon\CarbonImmutable;
use Database\Seeders\GebruikerSeeder;
use Database\Seeders\QuoteSeeder;
use Database\Seeders\ReferentieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De islamitische (hidjri) datum onder de zijbalk-quote. Bewaakt de omrekening,
 * de instelbare variant en verschuiving, en dat een ontbrekende intl-extensie of
 * een uitgezette kalender het scherm niet breekt.
 */
class HidjriKalenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentieSeeder::class, GebruikerSeeder::class, QuoteSeeder::class]);
    }

    public function test_omrekening_naar_umm_al_qura(): void
    {
        if (! extension_loaded('intl')) {
            $this->markTestSkipped('intl-extensie niet geladen.');
        }

        config(['sis.hidjri.variant' => 'islamic-umalqura', 'sis.hidjri.dagen_verschuiving' => 0]);

        // 19 juli 2026 valt volgens Umm al-Qura op 5 Safar 1448.
        $h = Hidjrikalender::vandaag(CarbonImmutable::parse('2026-07-19 12:00', config('app.timezone')));

        $this->assertSame(5, $h['dag']);
        $this->assertSame(2, $h['maand']);
        $this->assertSame(1448, $h['jaar']);
        $this->assertSame('Safar', $h['maand_nl']);
        $this->assertSame('5 Safar 1448 AH', $h['tekst']);
        // De Arabische regel gebruikt Arabisch-Indische cijfers.
        $this->assertStringContainsString('صفر', $h['arabisch']);
        $this->assertStringContainsString('١٤٤٨', $h['arabisch']);
    }

    public function test_de_verschuiving_werkt(): void
    {
        if (! extension_loaded('intl')) {
            $this->markTestSkipped('intl-extensie niet geladen.');
        }

        $moment = CarbonImmutable::parse('2026-07-19 12:00', config('app.timezone'));

        config(['sis.hidjri.variant' => 'islamic-umalqura', 'sis.hidjri.dagen_verschuiving' => 0]);
        $zonder = Hidjrikalender::vandaag($moment);

        config(['sis.hidjri.dagen_verschuiving' => 1]);
        $met = Hidjrikalender::vandaag($moment);

        $this->assertSame($zonder['dag'] + 1, $met['dag']);
    }

    public function test_de_varianten_kunnen_verschillen(): void
    {
        if (! extension_loaded('intl')) {
            $this->markTestSkipped('intl-extensie niet geladen.');
        }

        $moment = CarbonImmutable::parse('2026-07-19 12:00', config('app.timezone'));
        config(['sis.hidjri.dagen_verschuiving' => 0]);

        config(['sis.hidjri.variant' => 'islamic-umalqura']);
        $umalqura = Hidjrikalender::vandaag($moment);
        config(['sis.hidjri.variant' => 'islamic-civil']);
        $civil = Hidjrikalender::vandaag($moment);

        // Beide leveren een geldige datum; ze hoeven niet gelijk te zijn — juist
        // daarom is de variant instelbaar.
        $this->assertSame(1448, $umalqura['jaar']);
        $this->assertSame(1448, $civil['jaar']);
        $this->assertNotSame($umalqura['dag'], $civil['dag']);
    }

    public function test_onbekende_variant_valt_terug_op_de_standaard(): void
    {
        if (! extension_loaded('intl')) {
            $this->markTestSkipped('intl-extensie niet geladen.');
        }

        $moment = CarbonImmutable::parse('2026-07-19 12:00', config('app.timezone'));
        config(['sis.hidjri.dagen_verschuiving' => 0]);

        config(['sis.hidjri.variant' => 'islamic-umalqura']);
        $goed = Hidjrikalender::vandaag($moment);

        // ICU valt bij een onbekende sleutel stil terug op de gregoriaanse
        // kalender; dan zou hier het jaar 2026 staan in plaats van 1448.
        config(['sis.hidjri.variant' => 'bestaat-niet']);
        $fout = Hidjrikalender::vandaag($moment);

        $this->assertSame($goed, $fout);
        $this->assertSame(1448, $fout['jaar']);
    }

    public function test_de_datum_staat_in_de_zijbalk(): void
    {
        if (! extension_loaded('intl')) {
            $this->markTestSkipped('intl-extensie niet geladen.');
        }

        config(['sis.hidjri.tonen' => true, 'sis.hidjri.variant' => 'islamic-umalqura']);
        $h = Hidjrikalender::vandaag();

        $this->actingAs(User::where('rol', Rol::Studentenzaken)->firstOrFail())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($h['tekst'])
            ->assertSee($h['maand_ar'], false);
    }

    public function test_de_datum_is_uit_te_zetten(): void
    {
        config(['sis.hidjri.tonen' => false]);

        $this->actingAs(User::where('rol', Rol::Studentenzaken)->firstOrFail())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('sis-hidjri', false);
    }
}
