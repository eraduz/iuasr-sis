<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Verlofrecht per medewerker, jaar en verloftype (module HR). */
class Verlofsaldo extends Model
{
    protected $table = 'verlofsaldi';

    protected $fillable = ['medewerker_id', 'jaar', 'verloftype', 'recht_uren'];

    protected function casts(): array
    {
        return ['jaar' => 'integer', 'recht_uren' => 'decimal:1'];
    }

    public function medewerker(): BelongsTo
    {
        return $this->belongsTo(Medewerker::class);
    }
}
