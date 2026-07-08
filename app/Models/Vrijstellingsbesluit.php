<?php

namespace App\Models;

use App\Enums\VrijstellingGrondslag;
use App\Enums\VrijstellingsbesluitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Besluit van de examencommissie om een student vrijstelling te verlenen voor
 * een vak, gericht aan Studentenzaken ter verwerking. Zie de migratie voor de
 * workflow.
 */
class Vrijstellingsbesluit extends Model
{
    protected $table = 'vrijstellingsbesluiten';

    protected $fillable = [
        'student_id', 'vak_id', 'grondslag', 'besluit', 'besluit_datum',
        'toelichting', 'status', 'aangemaakt_door_id', 'verwerkt_door_id',
        'verwerkt_op', 'vaktoewijzing_id',
    ];

    protected function casts(): array
    {
        return [
            'grondslag' => VrijstellingGrondslag::class,
            'status' => VrijstellingsbesluitStatus::class,
            'besluit_datum' => 'date',
            'verwerkt_op' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function vak(): BelongsTo
    {
        return $this->belongsTo(Vak::class);
    }

    public function aangemaaktDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aangemaakt_door_id');
    }

    public function verwerktDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verwerkt_door_id');
    }
}
