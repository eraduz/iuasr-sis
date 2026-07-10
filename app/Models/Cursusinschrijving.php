<?php

namespace App\Models;

use App\Enums\CursusinschrijvingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Inschrijving van een cursist op een cursus. */
class Cursusinschrijving extends Model
{
    protected $table = 'cursusinschrijvingen';

    protected $fillable = [
        'cursist_id', 'cursus_id', 'inschrijfdatum', 'status',
        'totaalbedrag', 'opmerking', 'ingeschreven_door_id',
    ];

    protected function casts(): array
    {
        return [
            'inschrijfdatum' => 'date',
            'status' => CursusinschrijvingStatus::class,
            'totaalbedrag' => 'decimal:2',
        ];
    }

    public function cursist(): BelongsTo
    {
        return $this->belongsTo(Cursist::class);
    }

    public function cursus(): BelongsTo
    {
        return $this->belongsTo(Cursus::class);
    }

    public function ingeschrevenDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ingeschreven_door_id');
    }

    public function betalingen(): HasMany
    {
        return $this->hasMany(Cursusbetaling::class, 'cursusinschrijving_id');
    }
}
