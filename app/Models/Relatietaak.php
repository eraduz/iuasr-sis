<?php

namespace App\Models;

use App\Enums\TaakPrioriteit;
use App\Enums\TaakStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Taak bij een organisatie (module Relatiebeheer & Stagebeheer). 'Te laat' wordt
 * afgeleid uit de vervaldatum en de status — geen opgeslagen veld.
 */
class Relatietaak extends Model
{
    protected $table = 'relatie_taken';

    protected $fillable = [
        'organisatie_id', 'stage_id', 'titel', 'omschrijving',
        'toegewezen_aan_id', 'aangemaakt_door_id', 'startdatum', 'vervaldatum',
        'prioriteit', 'status', 'afgerond_op', 'afgerond_door_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaakStatus::class,
            'prioriteit' => TaakPrioriteit::class,
            'startdatum' => 'date',
            'vervaldatum' => 'date',
            'afgerond_op' => 'datetime',
        ];
    }

    public function organisatie(): BelongsTo
    {
        return $this->belongsTo(Organisatie::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function toegewezenAan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'toegewezen_aan_id');
    }

    public function afgerondDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'afgerond_door_id');
    }

    /** Verstreken vervaldatum én niet afgerond. */
    public function isTeLaat(): bool
    {
        return $this->vervaldatum !== null
            && $this->status !== TaakStatus::Afgerond
            && $this->vervaldatum->isPast();
    }

    /** Beperk tot taken van organisaties die deze gebruiker mag zien. */
    public function scopeZichtbaarVoor($query, User $gebruiker)
    {
        if (! $gebruiker->isRelatieBeperkt()) {
            return $query;
        }

        return $query->whereHas('organisatie', fn ($q) => $q->whereHas('opleidingen',
            fn ($o) => $o->whereIn('opleidingen.id', $gebruiker->opleidingIds())));
    }
}
