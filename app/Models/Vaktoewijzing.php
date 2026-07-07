<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Toewijzing van een vak aan een student (via de inschrijving van dat studiejaar). */
class Vaktoewijzing extends Model
{
    protected $table = 'vaktoewijzingen';

    protected $fillable = ['inschrijving_id', 'vak_id', 'automatisch'];

    protected function casts(): array
    {
        return ['automatisch' => 'boolean'];
    }

    public function inschrijving(): BelongsTo
    {
        return $this->belongsTo(Inschrijving::class);
    }

    public function vak(): BelongsTo
    {
        return $this->belongsTo(Vak::class);
    }
}
