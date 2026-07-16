<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Een module van het platform. Zie de create-migratie voor de vijf modules.
 * `actief` = gebouwd en bruikbaar; nog niet gebouwde modules worden als
 * 'binnenkort' getoond.
 */
class Module extends Model
{
    protected $table = 'modules';

    protected $fillable = ['sleutel', 'naam', 'omschrijving', 'icoon', 'actief', 'volgorde'];

    protected function casts(): array
    {
        return [
            'actief' => 'boolean',
            'volgorde' => 'integer',
        ];
    }

    /**
     * De route waar een module opent. Alleen gebouwde (actieve) modules hebben er
     * een; zodra een module wordt gebouwd, komt haar startroute hier bij.
     */
    private const START_ROUTES = [
        'studentenzaken' => 'dashboard',
        'cursussen' => 'cursussen.dashboard',
        'relatiebeheer' => 'relatiebeheer.dashboard',
        'hr' => 'hr.dashboard',
        'balie' => 'balie.dashboard',
        'bibliotheek' => 'bibliotheek.dashboard',
        'scriptie' => 'scriptie.dashboard',
        'stichtingsbestuur' => 'stichtingsbestuur.dashboard',
    ];

    public function startRoute(): ?string
    {
        $naam = self::START_ROUTES[$this->sleutel] ?? null;

        return $naam !== null && \Illuminate\Support\Facades\Route::has($naam) ? $naam : null;
    }

    public function scopeGeordend(Builder $query): Builder
    {
        return $query->orderBy('volgorde')->orderBy('naam');
    }

    /** Mag de gebruiker deze module benaderen (los van of hij al gebouwd is)? */
    public function toegankelijkVoor(User $gebruiker): bool
    {
        // Unie over alle rollen: een extra rol kan een module ontsluiten.
        return $gebruiker->magModule($this->sleutel);
    }

    /** Kan de gebruiker deze module nu openen: hij is gebouwd én toegankelijk. */
    public function bruikbaarVoor(User $gebruiker): bool
    {
        return $this->actief && $this->toegankelijkVoor($gebruiker);
    }

    /**
     * De modules die op het keuzescherm van deze gebruiker horen: alle modules
     * waar zijn rol toegang toe geeft. De 'later te ontwikkelen' modules zijn ook
     * voor iedereen zichtbaar (informatief), maar niet toegankelijk.
     *
     * @return Collection<int, Module>
     */
    public static function voorKeuzescherm(User $gebruiker): Collection
    {
        return static::query()->geordend()->get()
            ->filter(fn (Module $m) => $m->toegankelijkVoor($gebruiker) || ! $m->actief)
            ->values();
    }
}
