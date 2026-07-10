<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Afspraak dat de student zijn achterstand vóór `geldig_tot` betaalt. Zolang de
 * afspraak loopt vervallen de blokkades op verklaringen en herinschrijven.
 *
 * 'Lopend' wordt altijd AFGELEID uit de einddatum en het intrekmoment; het is
 * geen opgeslagen status. Zo kan een verlopen of ingetrokken afspraak nooit
 * blijven doorwerken.
 */
class Betalingsafspraak extends Model
{
    protected $table = 'betalingsafspraken';

    protected $fillable = [
        'student_id',
        'geldig_tot',
        'reden',
        'vastgelegd_door_id',
        'ingetrokken_op',
        'ingetrokken_door_id',
    ];

    protected function casts(): array
    {
        return [
            'geldig_tot' => 'date',
            'ingetrokken_op' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function vastgelegdDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vastgelegd_door_id');
    }

    public function ingetrokkenDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ingetrokken_door_id');
    }

    public function isIngetrokken(): bool
    {
        return $this->ingetrokken_op !== null;
    }

    public function isVerlopen(?Carbon $peildatum = null): bool
    {
        return $this->geldig_tot->lt(($peildatum ?? Carbon::now())->startOfDay());
    }

    /** Loopt de afspraak nog: niet ingetrokken en de einddatum is niet verstreken. */
    public function isLopend(?Carbon $peildatum = null): bool
    {
        return ! $this->isIngetrokken() && ! $this->isVerlopen($peildatum);
    }

    /** Dagen tot de einddatum; negatief betekent verlopen. */
    public function dagenResterend(?Carbon $peildatum = null): int
    {
        return ($peildatum ?? Carbon::now())->startOfDay()
            ->diffInDays($this->geldig_tot->copy()->startOfDay(), false);
    }

    /** Alleen de afspraken die vandaag nog gelden. */
    public function scopeLopend(Builder $query, ?Carbon $peildatum = null): Builder
    {
        return $query->whereNull('ingetrokken_op')
            ->whereDate('geldig_tot', '>=', ($peildatum ?? Carbon::now())->toDateString());
    }

    /** De lopende afspraak van een student, of null. */
    public static function lopendVoor(Student $student, ?Carbon $peildatum = null): ?self
    {
        return static::where('student_id', $student->id)
            ->lopend($peildatum)
            ->orderByDesc('geldig_tot')
            ->first();
    }
}
