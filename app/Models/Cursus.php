<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Een cursus binnen de module Cursussen Administratie. */
class Cursus extends Model
{
    protected $table = 'cursussen';

    protected $fillable = [
        'code', 'naam', 'omschrijving', 'cursusgeld',
        'startdatum', 'einddatum', 'directeur_id', 'actief',
    ];

    protected function casts(): array
    {
        return [
            'cursusgeld' => 'decimal:2',
            'startdatum' => 'date',
            'einddatum' => 'date',
            'actief' => 'boolean',
        ];
    }

    public function directeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'directeur_id');
    }

    public function inschrijvingen(): HasMany
    {
        return $this->hasMany(Cursusinschrijving::class);
    }

    /**
     * Beperk een query tot de cursussen die deze gebruiker mag zien. Een
     * cursusdirecteur (cursusadministratie) ziet uitsluitend de eigen cursus(sen);
     * Financiën, Beheer en Bestuur zien alle cursussen.
     */
    public function scopeZichtbaarVoor($query, User $gebruiker)
    {
        if (! $gebruiker->isCursusBeperkt()) {
            return $query;
        }

        return $query->whereIn('id', $gebruiker->cursusIds());
    }

    /** Mag deze gebruiker deze cursus inzien? */
    public function zichtbaarVoor(User $gebruiker): bool
    {
        return ! $gebruiker->isCursusBeperkt() || $this->directeur_id === $gebruiker->id;
    }

    /** Mag deze gebruiker deze cursus beheren (details wijzigen)? */
    public function beheerbaarVoor(User $gebruiker): bool
    {
        return $gebruiker->magCursusBeheer() && $this->zichtbaarVoor($gebruiker);
    }
}
