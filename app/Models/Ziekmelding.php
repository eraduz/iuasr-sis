<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ziek-/herstelmelding (module HR). Open melding = medewerker is ziek. */
class Ziekmelding extends Model
{
    protected $table = 'ziekmeldingen';

    protected $fillable = ['medewerker_id', 'ziek_van', 'hersteld_op', 'percentage', 'opmerking', 'gemeld_door_id'];

    protected function casts(): array
    {
        return [
            'ziek_van' => 'date',
            'hersteld_op' => 'date',
            'percentage' => 'integer',
        ];
    }

    public function medewerker(): BelongsTo
    {
        return $this->belongsTo(Medewerker::class);
    }

    public function gemeldDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gemeld_door_id');
    }

    public function isOpen(): bool
    {
        return $this->hersteld_op === null;
    }

    /** Verzuimduur in dagen (t/m herstel of vandaag). */
    public function dagen(): int
    {
        $eind = $this->hersteld_op ?? now();

        return (int) $this->ziek_van->diffInDays($eind) + 1;
    }
}
