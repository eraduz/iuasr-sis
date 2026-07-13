<?php

namespace App\Models\Bibliotheek;

use App\Enums\BibliotheekMailsoort;
use Illuminate\Database\Eloquent\Model;

/**
 * E-mailsjabloon dat de Beheerder kan aanpassen. Ondersteunde variabelen:
 * {{Naam}}, {{Titel}}, {{Uitleendatum}}, {{Retourdatum}}, {{AantalDagenTeLaat}}.
 */
class Emailsjabloon extends Model
{
    protected $table = 'bibliotheek_emailsjablonen';

    protected $fillable = ['soort', 'onderwerp', 'inhoud', 'actief'];

    /** De variabelen die in onderwerp en inhoud mogen voorkomen. */
    public const VARIABELEN = ['Naam', 'Titel', 'Uitleendatum', 'Retourdatum', 'AantalDagenTeLaat'];

    protected function casts(): array
    {
        return [
            'soort' => BibliotheekMailsoort::class,
            'actief' => 'boolean',
        ];
    }

    /**
     * Vervangt de variabelen door hun waarden. Onbekende variabelen blijven staan,
     * zodat een typefout in een sjabloon zichtbaar is in plaats van stilzwijgend
     * een lege plek achter te laten.
     *
     * @param  array<string,string>  $waarden
     */
    public function render(string $tekst, array $waarden): string
    {
        foreach ($waarden as $sleutel => $waarde) {
            $tekst = str_replace('{{'.$sleutel.'}}', (string) $waarde, $tekst);
        }

        return $tekst;
    }
}
