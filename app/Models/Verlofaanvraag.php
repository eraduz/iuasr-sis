<?php

namespace App\Models;

use App\Enums\Rol;
use App\Enums\Verlofstatus;
use App\Enums\Verloftype;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Verlofaanvraag (module HR). Zelfservice-aanvraag met een goedkeuringsworkflow
 * (leidinggevende, HR als terugval). Alleen goedgekeurde aanvragen tellen mee.
 */
class Verlofaanvraag extends Model
{
    protected $table = 'verlofaanvragen';

    protected $fillable = [
        'medewerker_id', 'verloftype', 'van', 'tot', 'uren', 'status', 'reden',
        'aangevraagd_door_id', 'beoordelaar_id', 'beoordeeld_op', 'opmerking_beoordelaar',
    ];

    protected function casts(): array
    {
        return [
            'verloftype' => Verloftype::class,
            'status' => Verlofstatus::class,
            'van' => 'date',
            'tot' => 'date',
            'uren' => 'decimal:1',
            'beoordeeld_op' => 'datetime',
        ];
    }

    public function medewerker(): BelongsTo
    {
        return $this->belongsTo(Medewerker::class);
    }

    public function beoordelaar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beoordelaar_id');
    }

    /**
     * Mag deze gebruiker deze aanvraag beoordelen? HR/Beheer altijd; een Manager
     * uitsluitend voor het eigen team (dus nooit de eigen aanvraag — dan is HR de
     * terugval). Alleen zolang de aanvraag nog openstaat.
     */
    public function beoordeelbaarVoor(User $gebruiker): bool
    {
        if ($this->status !== Verlofstatus::Aangevraagd) {
            return false;
        }
        if ($gebruiker->magHrBeheer()) {
            return true;
        }

        return $gebruiker->rol === Rol::Manager
            && $gebruiker->medewerker !== null
            && $this->medewerker?->manager_id === $gebruiker->medewerker->id;
    }
}
