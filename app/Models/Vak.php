<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Vak — een onderwijseenheid met een EC-waarde. Een vak bestaat uit één of
 * meer toetsonderdelen met een weging; EC worden pas toegekend als alle
 * meetellende onderdelen voldoende zijn.
 */
class Vak extends Model
{
    protected $table = 'vakken';

    protected $fillable = [
        'opleiding_id',
        'docent_id',
        'code',
        'naam',
        'ec',
        'leerjaar',
        'blok',
        'actief',
    ];

    protected function casts(): array
    {
        return [
            'ec' => 'integer',
            'leerjaar' => 'integer',
            'blok' => 'integer',
            'actief' => 'boolean',
        ];
    }

    public function opleiding(): BelongsTo
    {
        return $this->belongsTo(Opleiding::class);
    }

    public function docent(): BelongsTo
    {
        return $this->belongsTo(Docent::class);
    }

    public function toetsonderdelen(): HasMany
    {
        return $this->hasMany(Toetsonderdeel::class);
    }
}
