<?php

namespace App\Models\Bibliotheek;

use Illuminate\Database\Eloquent\Model;

/** Boekenkast / reknummer waar een exemplaar fysiek staat. */
class Kast extends Model
{
    protected $table = 'bibliotheek_kasten';

    protected $fillable = ['code', 'omschrijving', 'actief'];

    protected function casts(): array
    {
        return ['actief' => 'boolean'];
    }
}
