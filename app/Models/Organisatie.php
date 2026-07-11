<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een externe organisatie/relatie binnen de module Relatiebeheer & Stagebeheer
 * (stageschool, schoolbestuur, zorginstelling, moskee, samenwerkingspartner).
 *
 * Zichtbaarheid is opleidinggebonden: de relatiebeheerder, de stagecoördinator
 * en de Directie zien uitsluitend de organisaties van hun eigen opleiding(en);
 * Bestuur en Beheer zien alle organisaties.
 */
class Organisatie extends Model
{
    protected $table = 'organisaties';

    protected $fillable = [
        'relatienummer', 'naam', 'kvk_nummer', 'brin_nummer', 'organisatie_type_id',
        'adres', 'postcode', 'plaats', 'provincie', 'website', 'telefoon', 'email',
        'actief', 'opmerkingen',
    ];

    protected function casts(): array
    {
        return ['actief' => 'boolean'];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(OrganisatieType::class, 'organisatie_type_id');
    }

    public function opleidingen(): BelongsToMany
    {
        return $this->belongsToMany(Opleiding::class, 'organisatie_opleidingen');
    }

    public function contactpersonen(): HasMany
    {
        return $this->hasMany(Contactpersoon::class);
    }

    /**
     * Beperk een query tot de organisaties die deze gebruiker mag zien. Een
     * opleidinggebonden gebruiker ziet uitsluitend de organisaties die aan (een
     * van) zijn opleiding(en) zijn gekoppeld; overige rollen zien alles.
     */
    public function scopeZichtbaarVoor($query, User $gebruiker)
    {
        if (! $gebruiker->isRelatieBeperkt()) {
            return $query;
        }

        $ids = $gebruiker->opleidingIds();

        return $query->whereHas('opleidingen', fn ($q) => $q->whereIn('opleidingen.id', $ids));
    }

    /** Mag deze gebruiker deze organisatie inzien? */
    public function zichtbaarVoor(User $gebruiker): bool
    {
        if (! $gebruiker->isRelatieBeperkt()) {
            return true;
        }

        return $this->opleidingen()->whereIn('opleidingen.id', $gebruiker->opleidingIds())->exists();
    }

    /** Mag deze gebruiker deze organisatie beheren (wijzigen)? */
    public function beheerbaarVoor(User $gebruiker): bool
    {
        return $gebruiker->magRelatiebeheer() && $this->zichtbaarVoor($gebruiker);
    }
}
