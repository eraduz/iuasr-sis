<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Opzoektabel Periode — het schooljaar (bv. 2026-2027). Logica is
 * geparametriseerd op periode, niet hardcoded per schooljaar.
 */
class Periode extends Model
{
    protected $table = 'perioden';

    protected $fillable = ['code', 'naam', 'startdatum', 'einddatum', 'actief'];

    protected function casts(): array
    {
        return [
            'startdatum' => 'date',
            'einddatum' => 'date',
            'actief' => 'boolean',
        ];
    }

    /**
     * Er is altijd hooguit één actief studiejaar. Zodra een periode op actief
     * wordt gezet, worden alle andere automatisch gedeactiveerd. Zo blijft
     * `Periode::where('actief', true)->first()` eenduidig, ook na het aanmaken
     * van een nieuw studiejaar via de opzoektabellen.
     */
    protected static function booted(): void
    {
        static::saved(function (Periode $periode) {
            if ($periode->actief) {
                static::where('id', '!=', $periode->id)
                    ->where('actief', true)
                    ->update(['actief' => false]);
            }
        });
    }

    public function inschrijvingen(): HasMany
    {
        return $this->hasMany(Inschrijving::class);
    }
}
