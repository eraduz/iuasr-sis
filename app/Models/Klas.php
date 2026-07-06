<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Opzoektabel Klas — opleiding + leerjaar (bv. IT-1). In het oude systeem
 * was dit een tekstuele KlassenID; hier een echte relatie op surrogaatsleutel.
 */
class Klas extends Model
{
    protected $table = 'klassen';

    protected $fillable = ['opleiding_id', 'code', 'naam', 'leerjaar', 'groep'];

    protected function casts(): array
    {
        return [
            'leerjaar' => 'integer',
        ];
    }

    public function opleiding(): BelongsTo
    {
        return $this->belongsTo(Opleiding::class);
    }

    public function inschrijvingen(): HasMany
    {
        return $this->hasMany(Inschrijving::class);
    }
}
