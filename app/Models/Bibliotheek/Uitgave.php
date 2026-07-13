<?php

namespace App\Models\Bibliotheek;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Eén aflevering van een tijdschrift, met de artikelen die erin staan. */
class Uitgave extends Model
{
    protected $table = 'bibliotheek_uitgaven';

    protected $fillable = ['publicatie_id', 'uitgavenummer', 'publicatiedatum', 'jaar', 'locatie', 'opmerking'];

    protected function casts(): array
    {
        return [
            'publicatiedatum' => 'date',
            'jaar' => 'integer',
        ];
    }

    /** Het tijdschrift waartoe deze uitgave behoort. */
    public function tijdschrift(): BelongsTo
    {
        return $this->belongsTo(Publicatie::class, 'publicatie_id');
    }

    public function artikelen(): HasMany
    {
        return $this->hasMany(Artikel::class, 'uitgave_id')->orderBy('paginas')->orderBy('titel');
    }

    public function omschrijving(): string
    {
        return ($this->tijdschrift?->titel ?? '').' '.$this->uitgavenummer;
    }
}
