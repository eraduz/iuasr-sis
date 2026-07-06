<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Opzoektabel Faculteit (bv. FIW). */
class Faculteit extends Model
{
    protected $table = 'faculteiten';

    protected $fillable = ['code', 'naam'];

    public function opleidingen(): HasMany
    {
        return $this->hasMany(Opleiding::class);
    }
}
