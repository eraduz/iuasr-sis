<?php

namespace App\Models\Bibliotheek;

use App\Enums\ExemplaarStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * De TITEL (het bibliografische record): een boek, een tijdschrift of een
 * digitaal document. De fysieke boeken hangen eronder als exemplaren; een
 * tijdschrift heeft in plaats daarvan uitgaven met artikelen.
 *
 * Autorisatie: beheren mag de Bibliotheekmedewerker (en Beheer); inzien mag
 * daarnaast het Schoolbestuur (alleen-lezen).
 */
class Publicatie extends Model
{
    protected $table = 'bibliotheek_publicaties';

    protected $fillable = [
        'soort_id', 'isbn', 'titel', 'uitgavejaar', 'druknummer',
        'vakgebied_id', 'reeks_id', 'deelnummer', 'opmerking',
        // Herkomst uit het Excel-bestand (rekcode). Maakt de import idempotent.
        'bron_rekcode',
    ];

    protected function casts(): array
    {
        return [
            'uitgavejaar' => 'integer',
            'deelnummer' => 'integer',
        ];
    }

    /* --------------------------------------------------------------------
     | Relaties
     |------------------------------------------------------------------- */

    /**
     * Het soort: boek, tijdschrift, digitaal document, cd, dvd — en wat de
     * bibliotheek er zelf aan toevoegt. Een OPZOEKTABEL, geen vaste lijst in de
     * code. De soort draagt de twee vlaggen die het gedrag bepalen:
     * heeftExemplaren() en heeftUitgaven().
     */
    public function soort(): BelongsTo
    {
        return $this->belongsTo(Publicatiesoort::class, 'soort_id');
    }

    public function vakgebied(): BelongsTo
    {
        return $this->belongsTo(Vakgebied::class, 'vakgebied_id');
    }

    public function reeks(): BelongsTo
    {
        return $this->belongsTo(Reeks::class, 'reeks_id');
    }

    public function auteurs(): BelongsToMany
    {
        return $this->belongsToMany(Auteur::class, 'bibliotheek_publicatie_auteur', 'publicatie_id', 'auteur_id');
    }

    public function talen(): BelongsToMany
    {
        return $this->belongsToMany(Taal::class, 'bibliotheek_publicatie_taal', 'publicatie_id', 'taal_id');
    }

    public function exemplaren(): HasMany
    {
        return $this->hasMany(Exemplaar::class, 'publicatie_id')->orderBy('serienummer');
    }

    /** Alleen bij een tijdschrift: de afleveringen, nieuwste eerst. */
    public function uitgaven(): HasMany
    {
        return $this->hasMany(Uitgave::class, 'publicatie_id')
            ->orderByDesc('jaar')->orderByDesc('publicatiedatum')->orderByDesc('id');
    }

    /** Wat een externe bibliografische bron over deze titel zei (ISBN, jaar, schrijfwijze). */
    public function verrijkingen(): HasMany
    {
        return $this->hasMany(Verrijking::class, 'publicatie_id');
    }

    /* --------------------------------------------------------------------
     | Autorisatie
     |------------------------------------------------------------------- */

    public function zichtbaarVoor(User $gebruiker): bool
    {
        return $gebruiker->magBibliotheekInzien();
    }

    public function beheerbaarVoor(User $gebruiker): bool
    {
        return $gebruiker->magBibliotheekBeheren();
    }

    /* --------------------------------------------------------------------
     | Scopes
     |------------------------------------------------------------------- */

    /**
     * Vrij zoeken over titel, ISBN, auteursnaam, reekstitel en trefwoorden van de
     * artikelen. Zo vindt de medewerker een titel terug zonder te weten waar hij
     * genoteerd is. Werkt met Arabisch en Turks schrift (utf8mb4_unicode_ci).
     */
    public function scopeZoek(Builder $query, string $zoek): Builder
    {
        $zoek = trim($zoek);

        if ($zoek === '') {
            return $query;
        }

        return $query->where(function (Builder $sub) use ($zoek) {
            $sub->where('titel', 'like', "%{$zoek}%")
                ->orWhere('isbn', 'like', "%{$zoek}%")
                // Ook op de rekplaats ("F. 1070"): zo vindt de bibliotheek terug
                // welk boek op een bepaalde plek in de kast hoort te liggen.
                ->orWhere('bron_rekcode', 'like', "%{$zoek}%")
                ->orWhereHas('auteurs', fn (Builder $a) => $a->where('naam', 'like', "%{$zoek}%"))
                ->orWhereHas('reeks', fn (Builder $r) => $r->where('titel', 'like', "%{$zoek}%"));
        });
    }

    /* --------------------------------------------------------------------
     | Afleidingen
     |------------------------------------------------------------------- */

    /** Is dit een publicatie van de opgegeven soort (op code, bijv. 'boek')? */
    public function isSoort(string $code): bool
    {
        return $this->soort?->code === $code;
    }

    /** Kent deze titel fysieke exemplaren? Leest de vlag van de soort. */
    public function heeftExemplaren(): bool
    {
        return (bool) $this->soort?->heeftExemplaren();
    }

    /** Kent deze titel uitgaven met artikelen? Leest de vlag van de soort. */
    public function heeftUitgaven(): bool
    {
        return (bool) $this->soort?->heeftUitgaven();
    }

    /** Filteren op soortcode: ->vanSoort('tijdschrift'). */
    public function scopeVanSoort(Builder $query, string $code): Builder
    {
        return $query->whereHas('soort', fn (Builder $s) => $s->where('code', $code));
    }

    /**
     * De REKPLAATS: waar het boek fysiek ligt, zoals de bibliotheek het altijd al
     * noteerde ("F. 1070"). Komt uit de oude Excel-bibliotheek (`bron_rekcode`) en
     * wordt bij nieuwe titels met de hand ingevuld. De letter is tevens de kast.
     */
    public function rekplaats(): ?string
    {
        return $this->bron_rekcode;
    }

    /** Titel inclusief deelaanduiding: "Tafsir Ibn Kathir — Deel 2". */
    public function volledigeTitel(): string
    {
        if ($this->reeks_id === null) {
            return $this->titel;
        }

        $deel = $this->deelnummer !== null ? ' — Deel '.$this->deelnummer : '';

        return ($this->reeks?->titel ?? $this->titel).$deel;
    }

    /** Aantal exemplaren dat nu uitleenbaar is. */
    public function aantalBeschikbaar(): int
    {
        return $this->exemplaren
            ->filter(fn (Exemplaar $e) => $e->status === ExemplaarStatus::Beschikbaar)
            ->count();
    }

    public function auteursTekst(): string
    {
        return $this->auteurs->pluck('naam')->implode(', ') ?: '—';
    }

    public function talenTekst(): string
    {
        return $this->talen->pluck('naam')->implode(', ') ?: '—';
    }
}
