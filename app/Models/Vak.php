<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Vak — een onderwijseenheid met een EC-waarde. Een vak bestaat uit één of
 * meer toetsonderdelen met een weging; EC worden pas toegekend als alle
 * meetellende onderdelen voldoende zijn.
 */
class Vak extends Model
{
    protected $table = 'vakken';

    protected $fillable = [
        'opleiding_id',
        'docent_id',
        'code',
        'naam',
        'ec',
        'leerjaar',
        'blok',
        'actief',
    ];

    protected function casts(): array
    {
        return [
            'ec' => 'integer',
            'leerjaar' => 'integer',
            'blok' => 'integer',
            'actief' => 'boolean',
        ];
    }

    public function opleiding(): BelongsTo
    {
        return $this->belongsTo(Opleiding::class);
    }

    public function docent(): BelongsTo
    {
        return $this->belongsTo(Docent::class);
    }

    public function toetsonderdelen(): HasMany
    {
        return $this->hasMany(Toetsonderdeel::class)->orderBy('volgorde');
    }

    /** Alle resultaten van dit vak (via de toetsonderdelen). */
    public function resultaten(): HasManyThrough
    {
        return $this->hasManyThrough(Resultaat::class, Toetsonderdeel::class);
    }

    /**
     * Deelnemers: actieve inschrijvingen in dezelfde opleiding (en, bij een
     * leerjaargebonden vak, hetzelfde leerjaar) in de actieve periode.
     */
    public function deelnemers(): \Illuminate\Database\Eloquent\Builder
    {
        $periodeId = Periode::where('actief', true)->value('id');

        return Inschrijving::query()
            ->where('opleiding_id', $this->opleiding_id)
            ->where('status', 'actief')
            ->when($this->leerjaar, fn ($q) => $q->where('leerjaar', $this->leerjaar))
            ->when($periodeId, fn ($q) => $q->where('periode_id', $periodeId))
            ->with('student')
            ->join('studenten', 'studenten.id', '=', 'inschrijvingen.student_id')
            ->orderBy('studenten.achternaam')
            ->select('inschrijvingen.*');
    }
}
