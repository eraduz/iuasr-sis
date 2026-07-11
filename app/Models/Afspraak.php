<?php

namespace App\Models;

use App\Enums\AfspraakType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agenda-afspraak bij een organisatie (module Relatiebeheer & Stagebeheer).
 */
class Afspraak extends Model
{
    protected $table = 'agenda_afspraken';

    protected $fillable = [
        'organisatie_id', 'stage_id', 'medewerker_id', 'type',
        'datum', 'tijd_van', 'tijd_tot', 'locatie', 'status', 'omschrijving',
    ];

    protected function casts(): array
    {
        return [
            'type' => AfspraakType::class,
            'datum' => 'date',
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

    public function medewerker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'medewerker_id');
    }

    /** Beperk tot afspraken van organisaties die deze gebruiker mag zien. */
    public function scopeZichtbaarVoor($query, User $gebruiker)
    {
        if (! $gebruiker->isRelatieBeperkt()) {
            return $query;
        }

        return $query->whereHas('organisatie', fn ($q) => $q->whereHas('opleidingen',
            fn ($o) => $o->whereIn('opleidingen.id', $gebruiker->opleidingIds())));
    }
}
