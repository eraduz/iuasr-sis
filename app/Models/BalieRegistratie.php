<?php

namespace App\Models;

use App\Enums\BalieRichting;
use App\Enums\BalieSoort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén regel in het balielogboek: een telefoongesprek, een bezoek of een
 * poststuk. Zie de create-migratie voor de motivering van het datamodel.
 *
 * Autorisatie (server-side, nooit alleen in de UI):
 *   - beheren (aanmaken/wijzigen): rol Balie en Beheerder;
 *   - inzien: daarnaast uitsluitend het Schoolbestuur (alleen-lezen). De Directie
 *     niet: dit is een werkregister van de balie, geen opleidingsinformatie.
 * Registraties worden nooit verwijderd — het logboek is een chronologisch
 * verantwoordingsdocument.
 */
class BalieRegistratie extends Model
{
    protected $table = 'balie_registraties';

    protected $fillable = [
        'soort',
        'richting',
        'datum_tijd',
        'vertrokken_op',
        'onderwerp',
        'contact_naam',
        'contact_organisatie',
        'contact_telefoon',
        'medewerker_id',
        'afdeling',
        'toelichting',
        'geregistreerd_door_user_id',
    ];

    protected function casts(): array
    {
        return [
            'soort' => BalieSoort::class,
            'richting' => BalieRichting::class,
            'datum_tijd' => 'datetime',
            'vertrokken_op' => 'datetime',
        ];
    }

    /** Voor wie de registratie bestemd is (bestemd voor / afspraak met). */
    public function medewerker(): BelongsTo
    {
        return $this->belongsTo(Medewerker::class);
    }

    /** De baliemedewerker die de registratie vastlegde. */
    public function geregistreerdDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'geregistreerd_door_user_id');
    }

    /* --------------------------------------------------------------------
     | Autorisatie
     |------------------------------------------------------------------- */

    /** Mag deze gebruiker de registratie inzien? (Balie, Beheer, Schoolbestuur.) */
    public function zichtbaarVoor(User $gebruiker): bool
    {
        return $gebruiker->magBalieInzien();
    }

    /** Mag deze gebruiker de registratie aanmaken/wijzigen? (Balie, Beheer.) */
    public function beheerbaarVoor(User $gebruiker): bool
    {
        return $gebruiker->magBalieBeheren();
    }

    /* --------------------------------------------------------------------
     | Scopes — zoeken, filteren, chronologie
     |------------------------------------------------------------------- */

    /** Chronologisch: nieuwste bovenaan (het logboek leest terug in de tijd). */
    public function scopeChronologisch(Builder $query): Builder
    {
        return $query->orderByDesc('datum_tijd')->orderByDesc('id');
    }

    /**
     * Vrij zoeken over onderwerp, tegenpartij, afdeling en toelichting. Zo vindt
     * de balie een gesprek terug zonder te weten in welk veld iets is genoteerd.
     */
    public function scopeZoek(Builder $query, string $zoek): Builder
    {
        $zoek = trim($zoek);

        if ($zoek === '') {
            return $query;
        }

        return $query->where(function (Builder $sub) use ($zoek) {
            $sub->where('onderwerp', 'like', "%{$zoek}%")
                ->orWhere('contact_naam', 'like', "%{$zoek}%")
                ->orWhere('contact_organisatie', 'like', "%{$zoek}%")
                ->orWhere('afdeling', 'like', "%{$zoek}%")
                ->orWhere('toelichting', 'like', "%{$zoek}%")
                ->orWhereHas('medewerker', fn (Builder $m) => $m->where('voornaam', 'like', "%{$zoek}%")
                    ->orWhere('achternaam', 'like', "%{$zoek}%"));
        });
    }

    /** Bezoekers die zijn aangekomen maar (nog) niet zijn afgemeld. */
    public function scopeNogAanwezig(Builder $query): Builder
    {
        return $query->where('soort', BalieSoort::Bezoek)->whereNull('vertrokken_op');
    }

    /* --------------------------------------------------------------------
     | Presentatie
     |------------------------------------------------------------------- */

    /** Korte typering voor lijsten: "Telefoon (inkomend)", "Bezoek", "Post (uitgaand)". */
    public function soortLabel(): string
    {
        return $this->soort->heeftRichting()
            ? $this->soort->label().' ('.strtolower($this->richting->label()).')'
            : $this->soort->label();
    }

    /** Voor wie het bestemd is: de medewerker, anders de afdeling. */
    public function bestemdVoor(): string
    {
        return $this->medewerker?->volledigeNaam() ?? ($this->afdeling ?: '—');
    }

    /** Is dit een bezoeker die nog in het pand is volgens de registratie? */
    public function isNogAanwezig(): bool
    {
        return $this->soort === BalieSoort::Bezoek && $this->vertrokken_op === null;
    }
}
