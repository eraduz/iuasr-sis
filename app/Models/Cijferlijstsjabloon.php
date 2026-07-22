<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Het bewerkbare standaard-e-mailsjabloon voor de cijferlijst-mail (onderwerp +
 * tekst), beheerd door de examencommissie. Er is één actieve rij; {@see huidige()}
 * haalt die op of maakt hem met de standaardtekst aan.
 *
 * Variabelen in onderwerp/tekst: {{Naam}}, {{Periode}}, {{Opleiding}}.
 */
class Cijferlijstsjabloon extends Model
{
    protected $table = 'cijferlijstsjablonen';

    protected $fillable = ['onderwerp', 'inhoud'];

    /** De variabelen die in het onderwerp en de tekst gebruikt mogen worden. */
    public const VARIABELEN = ['Naam', 'Periode', 'Opleiding'];

    public const STANDAARD_ONDERWERP = 'Uw studieresultaten — Islamic University of Applied Sciences Rotterdam';

    public const STANDAARD_INHOUD = <<<'TXT'
Geachte {{Naam}},

Hierbij ontvangt u uw studieresultaten van de Islamic University of Applied Sciences Rotterdam. Uw officiële cijferlijst is als PDF-bijlage aan deze e-mail toegevoegd.

De cijferlijst bevat de door de examencommissie vastgestelde resultaten. Het document is digitaal gewaarmerkt; de echtheid kunt u controleren met de verificatiecode die op de laatste regel van de cijferlijst staat.

Hebt u vragen over uw resultaten, neem dan contact op met Bureau Studentenzaken via szaken@iuasr.nl.

Met vriendelijke groet,
Bureau Studentenzaken
Islamic University of Applied Sciences Rotterdam
TXT;

    /** Haal het sjabloon op, of maak het met de standaardtekst aan. */
    public static function huidige(): self
    {
        return static::first() ?? static::create([
            'onderwerp' => self::STANDAARD_ONDERWERP,
            'inhoud' => self::STANDAARD_INHOUD,
        ]);
    }

    /**
     * Vervangt de variabelen door hun waarden. Onbekende variabelen blijven staan,
     * zodat een typefout zichtbaar wordt in plaats van stilzwijgend te verdwijnen.
     *
     * @param  array<string,string>  $waarden
     */
    public function vulIn(string $tekst, array $waarden): string
    {
        foreach ($waarden as $sleutel => $waarde) {
            $tekst = str_replace('{{'.$sleutel.'}}', (string) $waarde, $tekst);
        }

        return $tekst;
    }
}
