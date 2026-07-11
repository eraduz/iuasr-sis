<?php

namespace App\Models;

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
     * Mag deze gebruiker deze aanvraag beoordelen? De HR-medewerker (tevens
     * leidinggevende) en Beheer, en uitsluitend zolang de aanvraag nog openstaat.
     */
    public function beoordeelbaarVoor(User $gebruiker): bool
    {
        return $this->status === Verlofstatus::Aangevraagd
            && $gebruiker->magVerlofBeoordelen();
    }
}
