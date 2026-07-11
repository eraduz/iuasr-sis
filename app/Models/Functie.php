<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Functie (opzoektabel, module HR). Beheerd via Opzoektabellen. */
class Functie extends Model
{
    protected $table = 'functies';

    protected $fillable = ['code', 'naam', 'categorie', 'actief'];

    protected function casts(): array
    {
        return ['actief' => 'boolean'];
    }

    public const CATEGORIEEN = ['docent' => 'Docent', 'staf' => 'Staf', 'management' => 'Management'];

    public function medewerkers(): HasMany
    {
        return $this->hasMany(Medewerker::class);
    }

    public function scopeActief($query)
    {
        return $query->where('actief', true);
    }
}
