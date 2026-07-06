<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Opzoektabel Land. */
class Land extends Model
{
    protected $table = 'landen';

    protected $fillable = ['code', 'naam'];
}
