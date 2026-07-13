<?php

namespace App\Models\Bibliotheek;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Wetenschapsgebied (Tafsir, Hadith, Fiqh, Aqidah, Geschiedenis, ...). */
class Vakgebied extends Model
{
    protected $table = 'bibliotheek_vakgebieden';

    // `rekletter` koppelt het vakgebied aan de rekcode uit de oude Excel-bibliotheek
    // (A = Tafsir, F = Fiqh, ...); dat is de betrouwbare bron voor het vakgebied.
    protected $fillable = ['naam', 'rekletter', 'omschrijving', 'actief', 'volgorde'];

    protected function casts(): array
    {
        return ['actief' => 'boolean', 'volgorde' => 'integer'];
    }

    public function publicaties(): HasMany
    {
        return $this->hasMany(Publicatie::class, 'vakgebied_id');
    }
}
