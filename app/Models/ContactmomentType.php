<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Soort contactmoment (opzoektabel). Beheerd via Opzoektabellen. */
class ContactmomentType extends Model
{
    protected $table = 'contactmoment_types';

    protected $fillable = ['code', 'naam', 'volgorde', 'actief'];

    protected function casts(): array
    {
        return ['volgorde' => 'integer', 'actief' => 'boolean'];
    }

    public function contactmomenten(): HasMany
    {
        return $this->hasMany(Contactmoment::class);
    }

    public function scopeActief($query)
    {
        return $query->where('actief', true);
    }
}
