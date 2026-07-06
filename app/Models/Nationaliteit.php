<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Opzoektabel Nationaliteit. */
class Nationaliteit extends Model
{
    protected $table = 'nationaliteiten';

    protected $fillable = ['code', 'naam'];
}
