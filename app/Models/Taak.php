<?php

namespace App\Models;

use App\Enums\TaakPrioriteit;
use App\Enums\TaakStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Taak op de gedeelde takenlijst van Studentenzaken.
 *
 * 'Te laat' wordt altijd AFGELEID uit de vervaldatum en de status; het is geen
 * opgeslagen veld. Zo kan een taak nooit als 'te laat' in de database staan
 * terwijl zij inmiddels is afgerond.
 */
class Taak extends Model
{
    protected $table = 'taken';

    protected $fillable = [
        'titel',
        'omschrijving',
        'student_id',
        'toegewezen_aan_id',
        'aangemaakt_door_id',
        'startdatum',
        'vervaldatum',
        'status',
        'prioriteit',
        'afgerond_op',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaakStatus::class,
            'prioriteit' => TaakPrioriteit::class,
            'startdatum' => 'date',
            'vervaldatum' => 'date',
            'afgerond_op' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function toegewezenAan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'toegewezen_aan_id');
    }

    public function aangemaaktDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aangemaakt_door_id');
    }

    public function isAfgerond(): bool
    {
        return $this->status === TaakStatus::Afgerond;
    }

    /** Een openstaande taak waarvan de vervaldatum verstreken is. */
    public function isTeLaat(?Carbon $peildatum = null): bool
    {
        if ($this->isAfgerond() || $this->vervaldatum === null) {
            return false;
        }

        return $this->vervaldatum->lt(($peildatum ?? Carbon::now())->startOfDay());
    }

    /**
     * Dagen tot de vervaldatum: negatief = te laat, 0 = vandaag, null = geen
     * vervaldatum of al afgerond.
     */
    public function dagenResterend(?Carbon $peildatum = null): ?int
    {
        if ($this->isAfgerond() || $this->vervaldatum === null) {
            return null;
        }

        return ($peildatum ?? Carbon::now())->startOfDay()
            ->diffInDays($this->vervaldatum->copy()->startOfDay(), false);
    }

    /** Leesbare urgentie voor de UI: te laat | vandaag | morgen | over N dagen | afgerond. */
    public function urgentie(?Carbon $peildatum = null): string
    {
        if ($this->isAfgerond()) {
            return 'afgerond';
        }

        $dagen = $this->dagenResterend($peildatum);
        if ($dagen === null) {
            return 'geen datum';
        }

        return match (true) {
            $dagen < 0 => abs($dagen) === 1 ? '1 dag te laat' : abs($dagen).' dagen te laat',
            $dagen === 0 => 'vandaag',
            $dagen === 1 => 'morgen',
            default => 'over '.$dagen.' dagen',
        };
    }

    /** Nog niet afgeronde taken. */
    public function scopeOpenstaand(Builder $query): Builder
    {
        return $query->where('status', '!=', TaakStatus::Afgerond->value);
    }

    /** Taken van deze gebruiker, plus de taken die aan niemand zijn toegewezen. */
    public function scopeVoorGebruiker(Builder $query, User $gebruiker): Builder
    {
        return $query->where(fn ($q) => $q->where('toegewezen_aan_id', $gebruiker->id)
            ->orWhereNull('toegewezen_aan_id'));
    }

    /**
     * Sorteervolgorde voor de lijst: eerst wat het snelst moet, dan prioriteit.
     * Taken zonder vervaldatum komen achteraan.
     */
    public function scopeOpUrgentie(Builder $query): Builder
    {
        return $query->orderByRaw('vervaldatum is null')
            ->orderBy('vervaldatum')
            ->orderByRaw("field(prioriteit, 'hoog', 'normaal', 'laag')")
            ->orderBy('id');
    }
}
