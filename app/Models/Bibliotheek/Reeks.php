<?php

namespace App\Models\Bibliotheek;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Boekreeks (bv. Tafsir Ibn Kathir). De delen zijn gewone publicaties met een
 * verwijzing naar deze reeks en een deelnummer — zo blijft elk deel apart
 * vindbaar en uitleenbaar, terwijl de samenhang zichtbaar is.
 */
class Reeks extends Model
{
    protected $table = 'bibliotheek_reeksen';

    protected $fillable = ['titel', 'opmerking'];

    /** De delen, op deelnummer. */
    public function delen(): HasMany
    {
        return $this->hasMany(Publicatie::class, 'reeks_id')->orderBy('deelnummer');
    }
}
