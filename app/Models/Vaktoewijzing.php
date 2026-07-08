<?php

namespace App\Models;

use App\Enums\VrijstellingGrondslag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Toewijzing van een vak aan een student (via de inschrijving van dat studiejaar). */
class Vaktoewijzing extends Model
{
    protected $table = 'vaktoewijzingen';

    protected $fillable = [
        'inschrijving_id', 'vak_id', 'automatisch',
        'vrijgesteld', 'vrijstelling_grondslag', 'vrijstelling_besluit',
        'vrijstelling_besluit_datum', 'vrijstelling_toelichting', 'vrijstelling_ec',
        'vrijgesteld_door_id', 'vrijgesteld_op',
    ];

    protected function casts(): array
    {
        return [
            'automatisch' => 'boolean',
            'vrijgesteld' => 'boolean',
            'vrijstelling_grondslag' => VrijstellingGrondslag::class,
            'vrijstelling_besluit_datum' => 'date',
            'vrijgesteld_op' => 'datetime',
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

    public function vrijgesteldDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vrijgesteld_door_id');
    }
}
