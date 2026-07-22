<?php

namespace App\Models;

use App\Enums\Stagestatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een stage: de plaatsing van een student op een organisatie/stageplaats, met
 * begeleiders vanuit de opleiding en op de locatie. Opleidinggebonden gescoped
 * (zoals de organisaties); de beoordeling is gevoelig en wordt gelogd.
 */
class Stage extends Model
{
    protected $table = 'stages';

    protected $fillable = [
        'stagenummer', 'student_id', 'organisatie_id', 'stageplaats_id', 'opleiding_id',
        'stageperiode_id', 'stagebegeleider_id', 'werkplekbegeleider_id',
        'startdatum', 'einddatum', 'uren',
        'status', 'beoordeling', 'beoordeling_toelichting',
    ];

    protected function casts(): array
    {
        return [
            'status' => Stagestatus::class,
            'startdatum' => 'date',
            'einddatum' => 'date',
            'uren' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function organisatie(): BelongsTo
    {
        return $this->belongsTo(Organisatie::class);
    }

    public function stageplaats(): BelongsTo
    {
        return $this->belongsTo(Stageplaats::class);
    }

    public function opleiding(): BelongsTo
    {
        return $this->belongsTo(Opleiding::class);
    }

    public function stageperiode(): BelongsTo
    {
        return $this->belongsTo(Stageperiode::class);
    }

    public function stagebegeleider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'stagebegeleider_id');
    }

    public function werkplekbegeleider(): BelongsTo
    {
        return $this->belongsTo(Contactpersoon::class, 'werkplekbegeleider_id');
    }

    /**
     * Beperk een query tot de stages die deze gebruiker mag zien. Opleidinggebonden
     * rollen (relatiebeheerder/stagecoördinator/directie) zien uitsluitend de stages
     * van hun eigen opleiding(en); Bestuur en Beheer zien alles.
     */
    public function scopeZichtbaarVoor($query, User $gebruiker)
    {
        if (! $gebruiker->isRelatieBeperkt()) {
            return $query;
        }

        return $query->whereIn('opleiding_id', $gebruiker->opleidingIds());
    }

    public function zichtbaarVoor(User $gebruiker): bool
    {
        return ! $gebruiker->isRelatieBeperkt()
            || $gebruiker->opleidingIds()->contains($this->opleiding_id);
    }

    /** Mag deze gebruiker deze stage beheren (muteren)? Stagecoördinator + Beheer. */
    public function beheerbaarVoor(User $gebruiker): bool
    {
        return $gebruiker->magStagebeheer() && $this->zichtbaarVoor($gebruiker);
    }
}
