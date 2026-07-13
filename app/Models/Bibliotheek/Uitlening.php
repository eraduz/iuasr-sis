<?php

namespace App\Models\Bibliotheek;

use App\Enums\ExemplaarStatus;
use App\Enums\Materiaalstaat;
use App\Models\Medewerker;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Eén uitlening van één exemplaar aan één lener (student OF medewerker).
 *
 * Alles wat afgeleid kan worden, IS afgeleid en staat niet in de database:
 *   - 'te laat'          → verwachte_retour_op < vandaag én nog niet retour;
 *   - 'op tijd retour'   → retour_op <= verwachte_retour_op;
 *   - 'aantal dagen te laat'.
 * Zou je die opslaan, dan lopen ze onvermijdelijk uit de pas met de datums.
 *
 * De boete is bewust niet berekend: de boeteregels zijn nog niet vastgesteld
 * door de opdrachtgever (zie PROGRESS.md, openstaande parameters).
 */
class Uitlening extends Model
{
    protected $table = 'bibliotheek_uitleningen';

    protected $fillable = [
        'exemplaar_id', 'student_id', 'medewerker_id',
        'uitgeleend_op', 'verwachte_retour_op', 'retour_op',
        'staat', 'retour_opmerking', 'boete_bedrag',
        'uitgeleend_door_user_id', 'ingenomen_door_user_id',
    ];

    protected function casts(): array
    {
        return [
            'uitgeleend_op' => 'date',
            'verwachte_retour_op' => 'date',
            'retour_op' => 'date',
            'staat' => Materiaalstaat::class,
            'boete_bedrag' => 'decimal:2',
        ];
    }

    /* --------------------------------------------------------------------
     | Relaties
     |------------------------------------------------------------------- */

    public function exemplaar(): BelongsTo
    {
        return $this->belongsTo(Exemplaar::class, 'exemplaar_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function medewerker(): BelongsTo
    {
        return $this->belongsTo(Medewerker::class, 'medewerker_id');
    }

    public function uitgeleendDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uitgeleend_door_user_id');
    }

    public function ingenomenDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ingenomen_door_user_id');
    }

    public function emaillogs(): HasMany
    {
        return $this->hasMany(Emaillog::class, 'uitlening_id')->orderByDesc('verzonden_op');
    }

    /* --------------------------------------------------------------------
     | De lener — precies één van beide
     |------------------------------------------------------------------- */

    public function isStudentlening(): bool
    {
        return $this->student_id !== null;
    }

    public function lenerNaam(): string
    {
        return $this->student?->volledigeNaam()
            ?? $this->medewerker?->volledigeNaam()
            ?? '—';
    }

    public function lenerEmail(): ?string
    {
        return $this->student?->email ?? $this->medewerker?->email;
    }

    public function lenerTelefoon(): ?string
    {
        return $this->student?->telefoon ?? $this->medewerker?->telefoon;
    }

    /* --------------------------------------------------------------------
     | Afleidingen — nooit opslaan
     |------------------------------------------------------------------- */

    public function isRetour(): bool
    {
        return $this->retour_op !== null;
    }

    /** Loopt nog én de retourdatum is verstreken. */
    public function isTeLaat(): bool
    {
        return ! $this->isRetour() && $this->verwachte_retour_op->isBefore(Carbon::today());
    }

    /** Aantal dagen te laat: bij een lopende uitlening t.o.v. vandaag, anders t.o.v. de retourdatum. */
    public function dagenTeLaat(): int
    {
        $peildatum = $this->retour_op ?? Carbon::today();

        return max(0, $this->verwachte_retour_op->diffInDays($peildatum, false));
    }

    /** Was het exemplaar op tijd terug? Alleen zinvol als het retour is. */
    public function isOpTijdIngeleverd(): bool
    {
        return $this->isRetour() && $this->retour_op->lessThanOrEqualTo($this->verwachte_retour_op);
    }

    /** Aantal dagen tot de vervaldatum (negatief als die verstreken is). */
    public function dagenTotVervaldatum(): int
    {
        return Carbon::today()->diffInDays($this->verwachte_retour_op, false);
    }

    /* --------------------------------------------------------------------
     | Scopes
     |------------------------------------------------------------------- */

    /** Nog niet retour. */
    public function scopeLopend(Builder $query): Builder
    {
        return $query->whereNull('retour_op');
    }

    /** Loopt nog en de retourdatum is verstreken. */
    public function scopeTeLaat(Builder $query): Builder
    {
        return $query->lopend()->whereDate('verwachte_retour_op', '<', Carbon::today());
    }

    /** Loopt nog en moet binnen $dagen terug (het waarschuwingsvenster). */
    public function scopeBinnenkortRetour(Builder $query, int $dagen = 3): Builder
    {
        return $query->lopend()
            ->whereDate('verwachte_retour_op', '>=', Carbon::today())
            ->whereDate('verwachte_retour_op', '<=', Carbon::today()->addDays($dagen));
    }

    /* --------------------------------------------------------------------
     | Mutaties
     |------------------------------------------------------------------- */

    /**
     * Neem het exemplaar in. Zet de retourdatum, de staat en de opmerking, en
     * werkt de status van het exemplaar bij: beschadigd materiaal komt niet
     * terug in de uitleen, licht beschadigd of beter wel.
     */
    public function innemen(Materiaalstaat $staat, ?string $opmerking, User $door, ?Carbon $retourdatum = null): void
    {
        $this->update([
            'retour_op' => $retourdatum ?? Carbon::today(),
            'staat' => $staat,
            'retour_opmerking' => $opmerking,
            'ingenomen_door_user_id' => $door->id,
        ]);

        $this->exemplaar->update([
            'status' => $staat->isSchade() ? ExemplaarStatus::Beschadigd : ExemplaarStatus::Beschikbaar,
        ]);
    }
}
