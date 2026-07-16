<?php

namespace App\Models;

use App\Enums\Bestuursorgaan;
use App\Enums\Bestuurstitel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een lid van het Stichtingsbestuur of een commissaris van de Raad van Toezicht.
 * Het onderscheid zit in `orgaan`. Een afgetreden lid blijft in het register
 * (historie): `actief = false` + `datum_uit_functie`.
 */
class Bestuurslid extends Model
{
    protected $table = 'bestuursleden';

    protected $fillable = [
        'orgaan', 'titel', 'voornaam', 'achternaam', 'geboortedatum', 'adres',
        'telefoon', 'email', 'datum_in_functie', 'datum_uit_functie', 'bevoegdheid', 'actief',
    ];

    protected function casts(): array
    {
        return [
            'orgaan' => Bestuursorgaan::class,
            'titel' => Bestuurstitel::class,
            'geboortedatum' => 'date',
            'datum_in_functie' => 'date',
            'datum_uit_functie' => 'date',
            'actief' => 'boolean',
        ];
    }

    public function aanwezigheden(): HasMany
    {
        return $this->hasMany(BestuursvergaderingAanwezigheid::class);
    }

    public function volledigeNaam(): string
    {
        return trim($this->voornaam.' '.$this->achternaam);
    }

    public function isCommissaris(): bool
    {
        return $this->orgaan === Bestuursorgaan::RaadVanToezicht;
    }

    public function scopeActief(Builder $query): Builder
    {
        return $query->where('actief', true);
    }

    public function scopeVoorOrgaan(Builder $query, Bestuursorgaan|string $orgaan): Builder
    {
        return $query->where('orgaan', $orgaan instanceof Bestuursorgaan ? $orgaan->value : $orgaan);
    }

    public function scopeGeordend(Builder $query): Builder
    {
        return $query->orderBy('achternaam')->orderBy('voornaam');
    }

    public function zichtbaarVoor(User $gebruiker): bool
    {
        return $gebruiker->magStichtingsbestuurInzien();
    }
}
