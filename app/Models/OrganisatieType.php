<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Soort organisatie (opzoektabel). Configureerbaar per opleiding: een type met
 * `opleiding_id = null` geldt voor alle opleidingen; een gevulde waarde beperkt
 * het tot die opleiding. Beheerd via Opzoektabellen.
 */
class OrganisatieType extends Model
{
    protected $table = 'organisatie_types';

    protected $fillable = ['code', 'naam', 'opleiding_id', 'actief'];

    protected function casts(): array
    {
        return ['actief' => 'boolean'];
    }

    public function opleiding(): BelongsTo
    {
        return $this->belongsTo(Opleiding::class);
    }

    public function organisaties(): HasMany
    {
        return $this->hasMany(Organisatie::class);
    }

    public function scopeActief($query)
    {
        return $query->where('actief', true);
    }
}
