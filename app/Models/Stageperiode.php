<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een stageperiode: een in het curriculum vastgelegde stage van een opleiding,
 * met een urennorm. Per opleiding datagedreven te beheren (Opzoektabellen). De
 * concrete plaatsing van een student verwijst hiernaar via {@see Stage}.
 *
 * @property int      $opleiding_id
 * @property string   $naam
 * @property int|null $leerjaar
 * @property int      $verplichte_uren
 * @property int      $volgorde
 * @property bool     $actief
 */
class Stageperiode extends Model
{
    protected $table = 'stageperioden';

    protected $fillable = [
        'opleiding_id', 'naam', 'code', 'leerjaar', 'verplichte_uren', 'volgorde', 'actief',
    ];

    protected function casts(): array
    {
        return [
            'leerjaar' => 'integer',
            'verplichte_uren' => 'integer',
            'volgorde' => 'integer',
            'actief' => 'boolean',
        ];
    }

    public function opleiding(): BelongsTo
    {
        return $this->belongsTo(Opleiding::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class);
    }

    /** Alleen actieve perioden, in curriculumvolgorde (volgorde, dan leerjaar, dan naam). */
    public function scopeActief(Builder $query): Builder
    {
        return $query->where('actief', true);
    }

    public function scopeGeordend(Builder $query): Builder
    {
        return $query->orderBy('volgorde')->orderByRaw('leerjaar is null, leerjaar')->orderBy('naam');
    }

    /** Volledige omschrijving voor lijsten: "Verkennende stage · jaar 2 · 140 uur". */
    public function omschrijving(): string
    {
        $delen = [$this->naam];
        if ($this->leerjaar !== null) {
            $delen[] = 'jaar '.$this->leerjaar;
        }
        $delen[] = $this->verplichte_uren.' uur';

        return implode(' · ', $delen);
    }

    /** Korte vorm voor een keuzelijst: "Verkennende stage (140 u)". */
    public function keuzelabel(): string
    {
        $jaar = $this->leerjaar !== null ? 'jaar '.$this->leerjaar.' · ' : '';

        return $this->naam.' ('.$jaar.$this->verplichte_uren.' u)';
    }
}
