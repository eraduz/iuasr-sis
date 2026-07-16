<?php

namespace App\Models;

use App\Enums\Bestuursorgaan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een vergadering van het Stichtingsbestuur of de Raad van Toezicht, met de
 * besproken onderwerpen, de besluiten en de aanwezigheid per lid.
 */
class Bestuursvergadering extends Model
{
    protected $table = 'bestuursvergaderingen';

    protected $fillable = [
        'datum', 'orgaan', 'locatie', 'onderwerpen', 'besluiten', 'opmerking', 'genotuleerd_door_id',
    ];

    protected function casts(): array
    {
        return [
            'datum' => 'date',
            'orgaan' => Bestuursorgaan::class,
        ];
    }

    public function aanwezigheden(): HasMany
    {
        return $this->hasMany(BestuursvergaderingAanwezigheid::class);
    }

    public function genotuleerdDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'genotuleerd_door_id');
    }

    /** Het aantal leden dat fysiek of online aanwezig was (uit de geladen relatie). */
    public function aantalAanwezig(): int
    {
        return $this->aanwezigheden->filter(fn ($a) => $a->aanwezigheid?->isAanwezig())->count();
    }

    public function scopeChronologisch(Builder $query): Builder
    {
        return $query->orderByDesc('datum')->orderByDesc('id');
    }

    public function zichtbaarVoor(User $gebruiker): bool
    {
        return $gebruiker->magStichtingsbestuurInzien();
    }
}
