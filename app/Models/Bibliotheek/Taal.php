<?php

namespace App\Models\Bibliotheek;

use Illuminate\Database\Eloquent\Model;

/** Taal van een publicatie (Arabisch, Turks, Engels, Nederlands; uitbreidbaar). */
class Taal extends Model
{
    protected $table = 'bibliotheek_talen';

    protected $fillable = ['code', 'naam', 'actief'];

    protected function casts(): array
    {
        return ['actief' => 'boolean'];
    }
}
