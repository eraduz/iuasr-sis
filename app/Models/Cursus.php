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
}
