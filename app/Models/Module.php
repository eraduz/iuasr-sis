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

    /**
     * De startroute voor deze gebruiker. Doorgaans het moduledashboard, maar wie
     * de module uitsluitend via de zelfservice mag openen (een docent met een
     * personeelsdossier), komt op zijn eigen scherm uit — niet op een dashboard
     * dat hem een 403 geeft.
     */
    public function startRoute(?User $gebruiker = null): ?string
    {
        $naam = self::START_ROUTES[$this->sleutel] ?? null;

        if ($this->sleutel === 'hr' && $gebruiker !== null && ! $gebruiker->magHrInzien()) {
            $naam = 'hr.mijn';
        }

        return $naam !== null && \Illuminate\Support\Facades\Route::has($naam) ? $naam : null;
    }

    /**
     * Opent deze gebruiker de module uitsluitend als zelfservice (eigen dossier),
     * zonder beheer- of inzagerechten op de module zelf? Nu alleen HR: elke
     * medewerker heeft een personeelsdossier, ook zonder HR-rol.
     */
    public function isZelfserviceVoor(User $gebruiker): bool
    {
        return $this->sleutel === 'hr'
            && ! $gebruiker->magHrInzien()
            && $gebruiker->medewerker !== null;
    }

    public function scopeGeordend(Builder $query): Builder
    {
        return $query->orderBy('volgorde')->orderBy('naam');
    }

    /** Mag de gebruiker deze module benaderen (los van of hij al gebouwd is)? */
    public function toegankelijkVoor(User $gebruiker): bool
    {
        // HR is óók toegankelijk voor iedere medewerker met een personeelsdossier,
        // ongeacht rol: de zelfservice ("Mijn HR", "Mijn verlof") is er voor het
        // eigen dossier. De beheer- en inzageschermen blijven achter `magHrInzien()`
        // en hun eigen rol-middleware; dit ontsluit alleen de module zelf.
        if ($this->sleutel === 'hr' && $gebruiker->medewerker !== null) {
            return true;
        }

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
