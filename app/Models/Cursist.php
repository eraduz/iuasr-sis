<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Een cursist — deelnemer aan de cursussen (los van de studenten). */
class Cursist extends Model
{
    protected $table = 'cursisten';

    protected $fillable = [
        'cursistnummer', 'aanhef', 'voornaam', 'tussenvoegsel', 'achternaam',
        'geboortedatum', 'geslacht', 'adres', 'postcode', 'woonplaats',
        'telefoon', 'email', 'status', 'opmerkingen',
    ];

    protected function casts(): array
    {
        return [
            'geboortedatum' => 'date',
        ];
    }

    public function inschrijvingen(): HasMany
    {
        return $this->hasMany(Cursusinschrijving::class);
    }

    public function volledigeNaam(): string
    {
        return trim(implode(' ', array_filter([$this->voornaam, $this->tussenvoegsel, $this->achternaam])));
    }
}
