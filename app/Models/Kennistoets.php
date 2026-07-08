<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Landelijke kennistoets die bij een opleiding hoort (bv. PABO: RWT, LKT). */
class Kennistoets extends Model
{
    protected $table = 'kennistoetsen';

    protected $fillable = ['opleiding_id', 'code', 'naam', 'volgorde', 'actief'];

    protected function casts(): array
    {
        return ['actief' => 'boolean'];
    }

    public function opleiding(): BelongsTo
    {
        return $this->belongsTo(Opleiding::class);
    }

    public function resultaten(): HasMany
    {
        return $this->hasMany(Kennistoetsresultaat::class);
    }
}
