<?php

namespace App\Models;

use App\Enums\Scriptiestap;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén ja/nee-checklistpunt binnen een stap van een scriptietraject. De tekst wordt
 * per traject bewaard (uit {@see Scriptiestap::checklistpunten()}). `waarde`
 * null = onbeantwoord, true = ja/akkoord, false = nee.
 */
class ScriptieChecklistpunt extends Model
{
    protected $table = 'scriptie_checklistpunten';

    protected $fillable = [
        'scriptie_id', 'stap', 'sleutel', 'label', 'volgorde',
        'waarde', 'toelichting', 'beoordelaar_id', 'beoordeeld_op',
    ];

    protected function casts(): array
    {
        return [
            'stap' => Scriptiestap::class,
            'waarde' => 'boolean',
            'beoordeeld_op' => 'datetime',
        ];
    }

    public function scriptie(): BelongsTo
    {
        return $this->belongsTo(Scriptie::class);
    }

    public function beoordelaar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beoordelaar_id');
    }
}
