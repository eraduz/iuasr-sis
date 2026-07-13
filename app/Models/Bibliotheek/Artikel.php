<?php

namespace App\Models\Bibliotheek;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Eén artikel in een tijdschriftuitgave. Hiermee is de vraag te beantwoorden
 * waar het in de opdracht om draait: "in welk tijdschrift staat dit artikel?"
 */
class Artikel extends Model
{
    protected $table = 'bibliotheek_artikelen';

    protected $fillable = ['uitgave_id', 'titel', 'paginas', 'trefwoorden', 'beschrijving'];

    public function uitgave(): BelongsTo
    {
        return $this->belongsTo(Uitgave::class, 'uitgave_id');
    }

    public function auteurs(): BelongsToMany
    {
        return $this->belongsToMany(Auteur::class, 'bibliotheek_artikel_auteur', 'artikel_id', 'auteur_id');
    }

    /**
     * Zoeken op artikeltitel, auteur, trefwoord én tijdschriftnaam — de vier
     * ingangen die de opdracht noemt.
     */
    public function scopeZoek(Builder $query, string $zoek): Builder
    {
        $zoek = trim($zoek);

        if ($zoek === '') {
            return $query;
        }

        return $query->where(function (Builder $sub) use ($zoek) {
            $sub->where('titel', 'like', "%{$zoek}%")
                ->orWhere('trefwoorden', 'like', "%{$zoek}%")
                ->orWhere('beschrijving', 'like', "%{$zoek}%")
                ->orWhereHas('auteurs', fn (Builder $a) => $a->where('naam', 'like', "%{$zoek}%"))
                ->orWhereHas('uitgave.tijdschrift', fn (Builder $t) => $t->where('titel', 'like', "%{$zoek}%"));
        });
    }

    /** @return array<int,string> */
    public function trefwoordenLijst(): array
    {
        return collect(explode(',', (string) $this->trefwoorden))
            ->map(fn ($t) => trim($t))->filter()->values()->all();
    }
}
