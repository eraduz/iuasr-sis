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

    public function inschrijvingen(): HasMany
    {
        return $this->hasMany(Inschrijving::class);
    }
}
