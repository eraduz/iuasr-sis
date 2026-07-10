<?php

namespace App\Models;

use App\Enums\Betaalmethode;
use App\Enums\Cursusbetaalstatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Een geregistreerde cursusgeldbetaling. */
class Cursusbetaling extends Model
{
    protected $table = 'cursusbetalingen';

    protected $fillable = [
        'cursusinschrijving_id', 'betaalmethode', 'bedrag', 'betaaldatum',
        'betalingsstatus', 'referentienummer', 'opmerking', 'geregistreerd_door_id',
    ];

    protected function casts(): array
    {
        return [
            'betaalmethode' => Betaalmethode::class,
            'betalingsstatus' => Cursusbetaalstatus::class,
            'bedrag' => 'decimal:2',
            'betaaldatum' => 'date',
        ];
    }

    public function inschrijving(): BelongsTo
    {
        return $this->belongsTo(Cursusinschrijving::class, 'cursusinschrijving_id');
    }

    public function geregistreerdDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'geregistreerd_door_id');
    }
}
