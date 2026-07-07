<?php

namespace App\Models;

use App\Enums\CijferlijstStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cijferlijst (vak × periode) met de vaststellingsstatus. Bepaalt of de docent
 * nog mag invoeren (concept) en of de examencommissie mag vaststellen/corrigeren.
 */
class Cijferlijst extends Model
{
    protected $table = 'cijferlijsten';

    protected $fillable = [
        'vak_id', 'periode_id', 'status',
        'ingediend_op', 'ingediend_door_id',
        'vastgesteld_op', 'vastgesteld_door_id',
        'opmerking',
    ];

    protected function casts(): array
    {
        return [
            'status' => CijferlijstStatus::class,
            'ingediend_op' => 'datetime',
            'vastgesteld_op' => 'datetime',
        ];
    }

    public function vak(): BelongsTo
    {
        return $this->belongsTo(Vak::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    /** Haal (of maak) de cijferlijst voor een vak en periode. */
    public static function voor(Vak $vak, Periode $periode): self
    {
        return static::firstOrCreate(
            ['vak_id' => $vak->id, 'periode_id' => $periode->id],
            ['status' => CijferlijstStatus::Concept],
        );
    }
}
