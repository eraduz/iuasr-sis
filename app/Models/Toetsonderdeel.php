<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Toetsonderdeel — genormaliseerd deelresultaat van een vak (werkstuk,
 * tentamen, mondeling, presentatie, portfolio, ...). Vervangt de starre
 * blok-kolommen (BL1–BL4) van het oude systeem. Weging bepaalt het aandeel
 * in het eindcijfer; `telt_mee` bepaalt of het onderdeel meetelt voor EC.
 */
class Toetsonderdeel extends Model
{
    protected $table = 'toetsonderdelen';

    protected $fillable = [
        'vak_id',
        'code',
        'naam',
        'type',        // werkstuk | tentamen | mondeling | presentatie | portfolio | ...
        'weging',      // aandeel in eindcijfer (0..1 of percentage)
        'telt_mee',    // telt mee voor EC-toekenning
        'volgorde',
    ];

    protected function casts(): array
    {
        return [
            'weging' => 'decimal:2',
            'telt_mee' => 'boolean',
            'volgorde' => 'integer',
        ];
    }

    public function vak(): BelongsTo
    {
        return $this->belongsTo(Vak::class);
    }

    public function resultaten(): HasMany
    {
        return $this->hasMany(Resultaat::class);
    }
}
