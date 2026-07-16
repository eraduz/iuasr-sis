<?php

namespace Tests\Feature;

use App\Support\BibliotheekTaalcontrole;
use Tests\TestCase;

/**
 * De risicodragende delen van de taalcontrole/auto-correctie: het veilig vervangen
 * van een heel woord (met hoofdletterbehoud) en de strenge 'interne typefout'-regel
 * die bepaalt wat automatisch gecorrigeerd mag worden.
 */
class BibliotheekTaalcontroleTest extends TestCase
{
    public function test_vervangwoord_behoudt_beginhoofdletter(): void
    {
        [$nieuw, $aantal] = BibliotheekTaalcontrole::vervangWoord('De vruchten van het Gellof', 'gellof', 'geloof');

        $this->assertSame('De vruchten van het Geloof', $nieuw);
        $this->assertSame(1, $aantal);
    }

    public function test_vervangwoord_behoudt_volledige_hoofdletters(): void
    {
        [$nieuw] = BibliotheekTaalcontrole::vervangWoord('ISLAM EN RELIGON', 'religon', 'religion');

        $this->assertSame('ISLAM EN RELIGION', $nieuw);
    }

    public function test_vervangwoord_raakt_alleen_hele_woorden(): void
    {
        // 'de' mag niet binnen 'Onder' worden geraakt; alleen het losse woord 'de'.
        [$nieuw, $aantal] = BibliotheekTaalcontrole::vervangWoord('Onder de brug', 'de', 'het');

        $this->assertSame('Onder het brug', $nieuw);
        $this->assertSame(1, $aantal);
    }

    public function test_vervangwoord_zonder_treffer_laat_titel_ongemoeid(): void
    {
        [$nieuw, $aantal] = BibliotheekTaalcontrole::vervangWoord('Een gewone titel', 'xyz', 'abc');

        $this->assertSame('Een gewone titel', $nieuw);
        $this->assertSame(0, $aantal);
    }

    public function test_interne_typfout_alleen_bij_gelijk_begin_en_einde(): void
    {
        // Echte interne typefouten (begin + einde kloppen, >= 6 tekens).
        $this->assertTrue(BibliotheekTaalcontrole::isInterneTypfout('geschidenis', 'geschiedenis'));
        $this->assertTrue(BibliotheekTaalcontrole::isInterneTypfout('philisophy', 'philosophy'));

        // Eerste letter verschilt (zeven/leven) → geen veilige auto-correctie.
        $this->assertFalse(BibliotheekTaalcontrole::isInterneTypfout('zeven', 'leven'));
        // Laatste letter verschilt (moslima/moslims — verbuiging/geldig woord).
        $this->assertFalse(BibliotheekTaalcontrole::isInterneTypfout('moslima', 'moslims'));
        // Te kort (< 6 tekens).
        $this->assertFalse(BibliotheekTaalcontrole::isInterneTypfout('kort', 'korte'));
    }
}
