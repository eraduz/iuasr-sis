<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resultaat — één rij per behaald deelresultaat (genormaliseerd). Legt vast:
 * het toetsonderdeel, de poging (tentamen/herkansing/extra kans), een eventuele
 * vrijstelling, het cijfer, de toetsdatum en WIE het heeft ingevoerd.
 *
 * Elke invoer/mutatie wordt gelogd (audit). Cijferinzage is voorbehouden aan
 * docent (eigen vak), examencommissie en directie — nooit Studentenzaken.
 */
class Resultaat extends Model
{
    protected $table = 'resultaten';

    protected $fillable = [
        'inschrijving_id',
        'student_id',
        'toetsonderdeel_id',
        'poging',          // tentamen | herkansing | extra_kans
        'poging_nr',
        'vrijstelling',
        'cijfer',
        'voldoende',
        'toetsdatum',
        'ingevoerd_door_id',
        'definitief',      // vastgesteld door examencommissie
        'opmerking',
    ];

    protected function casts(): array
    {
        return [
            'poging_nr' => 'integer',
            'vrijstelling' => 'boolean',
            'cijfer' => 'decimal:1',
            'voldoende' => 'boolean',
            'toetsdatum' => 'date',
            'definitief' => 'boolean',
        ];
    }

    public function inschrijving(): BelongsTo
    {
        return $this->belongsTo(Inschrijving::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function toetsonderdeel(): BelongsTo
    {
        return $this->belongsTo(Toetsonderdeel::class);
    }

    public function ingevoerdDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ingevoerd_door_id');
    }
}
