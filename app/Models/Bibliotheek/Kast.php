<?php

namespace App\Models\Bibliotheek;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Boekenkast / reknummer waar een exemplaar fysiek staat. In de oude Excel-
 * bibliotheek is dit de rekletter uit de rekcode (A-1 → kast A).
 */
class Kast extends Model
{
    protected $table = 'bibliotheek_kasten';

    protected $fillable = ['code', 'omschrijving', 'actief'];

    protected function casts(): array
    {
        return ['actief' => 'boolean'];
    }

    public function exemplaren(): HasMany
    {
        return $this->hasMany(Exemplaar::class, 'kast_id');
    }
}
