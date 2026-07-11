<?php

namespace App\Models;

use App\Enums\Stagestatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een stageplaats: het aanbod/de capaciteit bij een organisatie voor een
 * opleiding. De bezetting wordt afgeleid uit de lopende stages op de plaats.
 */
class Stageplaats extends Model
{
    protected $table = 'stageplaatsen';

    protected $fillable = [
        'organisatie_id', 'opleiding_id', 'periode_id', 'leerjaar',
        'aantal_plaatsen', 'max_studenten', 'eisen', 'specialisaties', 'werkdagen', 'actief',
    ];

    protected function casts(): array
    {
        return [
            'leerjaar' => 'integer',
            'aantal_plaatsen' => 'integer',
            'max_studenten' => 'integer',
            'actief' => 'boolean',
        ];
    }

    public function organisatie(): BelongsTo
    {
        return $this->belongsTo(Organisatie::class);
    }

    public function opleiding(): BelongsTo
    {
        return $this->belongsTo(Opleiding::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class);
    }

    /** Aantal plaatsingen die meetellen voor de bezetting (aangevraagd + lopend). */
    public function bezetting(): int
    {
        return $this->stages
            ->filter(fn (Stage $s) => $s->status instanceof Stagestatus && $s->status->teltVoorBezetting())
            ->count();
    }

    /** Vrije plaatsen t.o.v. het maximum, of null als er geen maximum is. */
    public function vrijePlaatsen(): ?int
    {
        if ($this->max_studenten === null) {
            return null;
        }

        return max(0, $this->max_studenten - $this->bezetting());
    }
}
