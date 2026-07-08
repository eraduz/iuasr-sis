<?php

namespace App\Support;

use App\Models\Kennistoets;
use App\Models\Kennistoetsresultaat;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Bewaking van de landelijke kennistoetsen (bv. PABO: RWT + LKT taal/rekenen).
 * De student moet de toetsen van de opleiding binnen de termijn
 * (config sis.kennistoetsen.termijn_jaren, standaard 2 jaar vanaf de
 * inschrijfdatum) halen. Werkt zoals de NT2-bewaking, maar voor meerdere toetsen.
 */
class Kennistoetsbewaking
{
    /** De verplichte, actieve kennistoetsen voor de (actieve) opleiding(en) van de student. */
    public static function toetsenVoor(Student $student): Collection
    {
        $opleidingIds = $student->inschrijvingen->where('status', 'actief')->pluck('opleiding_id')->unique();
        if ($opleidingIds->isEmpty()) {
            return collect();
        }

        return Kennistoets::whereIn('opleiding_id', $opleidingIds)->where('actief', true)
            ->orderBy('volgorde')->orderBy('code')->get();
    }

    public static function vereist(Student $student): bool
    {
        return self::toetsenVoor($student)->isNotEmpty();
    }

    /** Deadline: eerste inschrijfdatum bij de verplichtende opleiding + termijn. */
    public static function deadline(Student $student): ?Carbon
    {
        $toetsen = self::toetsenVoor($student);
        if ($toetsen->isEmpty()) {
            return null;
        }

        $eerste = $student->inschrijvingen
            ->where('status', 'actief')
            ->whereIn('opleiding_id', $toetsen->pluck('opleiding_id')->unique())
            ->min('inschrijfdatum');
        if (! $eerste) {
            return null;
        }

        return Carbon::parse($eerste)->addYears((int) config('sis.kennistoetsen.termijn_jaren', 2))->startOfDay();
    }

    /**
     * Volledige status.
     *
     * @return array{vereist: bool, status: string, toetsen: Collection, deadline: ?Carbon, dagen: ?int, behaald: int, totaal: int}
     *   status: niet_vereist | afgerond | open | verlopen
     */
    public static function voor(Student $student): array
    {
        $toetsen = self::toetsenVoor($student);
        if ($toetsen->isEmpty()) {
            return ['vereist' => false, 'status' => 'niet_vereist', 'toetsen' => collect(),
                'deadline' => null, 'dagen' => null, 'behaald' => 0, 'totaal' => 0];
        }

        $resultaten = Kennistoetsresultaat::where('student_id', $student->id)
            ->whereIn('kennistoets_id', $toetsen->pluck('id'))->get()->keyBy('kennistoets_id');

        $deadline = self::deadline($student);
        $verlopen = $deadline && now()->startOfDay()->gt($deadline);

        $rijen = $toetsen->map(function ($t) use ($resultaten, $verlopen) {
            $behaaldOp = $resultaten->get($t->id)?->behaald_op;

            return [
                'toets' => $t,
                'behaald_op' => $behaaldOp,
                'status' => $behaaldOp ? 'behaald' : ($verlopen ? 'verlopen' : 'open'),
            ];
        });

        $behaald = $rijen->where('status', 'behaald')->count();
        $totaal = $rijen->count();
        $status = $behaald === $totaal ? 'afgerond' : ($verlopen ? 'verlopen' : 'open');

        return [
            'vereist' => true,
            'status' => $status,
            'toetsen' => $rijen,
            'deadline' => $deadline,
            'dagen' => $deadline ? (int) now()->startOfDay()->diffInDays($deadline, false) : null,
            'behaald' => $behaald,
            'totaal' => $totaal,
        ];
    }
}
