<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Presentie — de aanwezigheid van één student bij één college van één vak.
 * `aanwezig` = true (1) of false (0). Geen regel = niet geregistreerd.
 */
class Presentie extends Model
{
    protected $table = 'presenties';

    protected $fillable = [
        'inschrijving_id',
        'vak_id',
        'week',
        'aanwezig',
        'geregistreerd_door_id',
    ];

    protected function casts(): array
    {
        return [
            'week' => 'integer',
            'aanwezig' => 'boolean',
        ];
    }

    public function inschrijving(): BelongsTo
    {
        return $this->belongsTo(Inschrijving::class);
    }

    public function vak(): BelongsTo
    {
        return $this->belongsTo(Vak::class);
    }

    public function geregistreerdDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'geregistreerd_door_id');
    }
}
