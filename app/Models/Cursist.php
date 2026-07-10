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

    /**
     * Beperk een query tot de cursisten die deze gebruiker mag zien. Een
     * cursusdirecteur ziet alleen cursisten met een inschrijving op de eigen
     * cursus(sen); Beheer en Bestuur zien alle cursisten.
     */
    public function scopeZichtbaarVoor($query, User $gebruiker)
    {
        if (! $gebruiker->isCursusBeperkt()) {
            return $query;
        }

        $ids = $gebruiker->cursusIds();

        return $query->whereHas('inschrijvingen', fn ($iq) => $iq->whereIn('cursus_id', $ids));
    }

    /** Mag deze gebruiker dit cursistdossier openen? */
    public function zichtbaarVoor(User $gebruiker): bool
    {
        if (! $gebruiker->isCursusBeperkt()) {
            return true;
        }

        return $this->inschrijvingen->pluck('cursus_id')
            ->intersect($gebruiker->cursusIds())
            ->isNotEmpty();
    }
}
