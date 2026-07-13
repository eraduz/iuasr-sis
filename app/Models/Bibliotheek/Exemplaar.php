<?php

namespace App\Models\Bibliotheek;

use App\Enums\ExemplaarStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eén FYSIEK exemplaar van een titel, met een eigen serienummer, plek in de kast
 * en status. Dit is wat wordt uitgeleend — niet de titel. Drie exemplaren van
 * hetzelfde boek zijn dus drie rijen hier, en één rij in `publicaties`.
 */
class Exemplaar extends Model
{
    protected $table = 'bibliotheek_exemplaren';

    protected $fillable = ['publicatie_id', 'serienummer', 'kast_id', 'status', 'opmerking'];

    protected function casts(): array
    {
        return ['status' => ExemplaarStatus::class];
    }

    public function publicatie(): BelongsTo
    {
        return $this->belongsTo(Publicatie::class, 'publicatie_id');
    }

    public function kast(): BelongsTo
    {
        return $this->belongsTo(Kast::class, 'kast_id');
    }

    public function uitleningen(): HasMany
    {
        return $this->hasMany(Uitlening::class, 'exemplaar_id')->orderByDesc('uitgeleend_op')->orderByDesc('id');
    }

    /** De lopende uitlening (nog niet retour), of null. */
    public function lopendeUitlening(): ?Uitlening
    {
        return $this->uitleningen()->whereNull('retour_op')->first();
    }

    /** Alleen exemplaren die nu uitleenbaar zijn. */
    public function scopeUitleenbaar(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ExemplaarStatus::Beschikbaar->value,
            ExemplaarStatus::Gereserveerd->value,
        ]);
    }

    /**
     * Kan dit exemplaar nu worden uitgeleend? Twee voorwaarden, allebei nodig:
     * de status laat het toe, én er loopt geen uitlening. De tweede check is de
     * echte waarheid — de status is een weergave daarvan.
     */
    public function isUitleenbaar(): bool
    {
        return $this->status->isUitleenbaar() && $this->lopendeUitlening() === null;
    }

    public function omschrijving(): string
    {
        return $this->serienummer.' — '.($this->publicatie?->volledigeTitel() ?? '');
    }
}
