<?php

namespace App\Models;

use App\Enums\Contracttype;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dienstverband (contract) van een medewerker. De FTE is afgeleid uit de uren per
 * week ÷ de voltijdsnorm (config `sis.hr.voltijd_uren`).
 */
class Dienstverband extends Model
{
    protected $table = 'dienstverbanden';

    protected $fillable = [
        'medewerker_id', 'contracttype', 'startdatum', 'einddatum',
        'uren_per_week', 'functie_id', 'afdeling_id', 'opmerking',
    ];

    protected function casts(): array
    {
        return [
            'contracttype' => Contracttype::class,
            'startdatum' => 'date',
            'einddatum' => 'date',
            'uren_per_week' => 'decimal:1',
        ];
    }

    public function medewerker(): BelongsTo
    {
        return $this->belongsTo(Medewerker::class);
    }

    public function functie(): BelongsTo
    {
        return $this->belongsTo(Functie::class);
    }

    public function afdeling(): BelongsTo
    {
        return $this->belongsTo(Afdeling::class);
    }

    /** FTE = uren per week ÷ de voltijdsnorm (afgeleid). */
    public function fte(): float
    {
        $voltijd = (float) config('sis.hr.voltijd_uren', 40);

        return $voltijd > 0 ? round(((float) $this->uren_per_week) / $voltijd, 2) : 0.0;
    }

    /** Loopt dit dienstverband nu (geen einddatum, of einddatum in de toekomst)? */
    public function isLopend(): bool
    {
        return $this->einddatum === null || ! $this->einddatum->isPast();
    }
}
