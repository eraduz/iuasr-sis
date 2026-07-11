<?php

namespace App\Models;

use App\Casts\VersleuteldGevoeligVeld;
use App\Enums\MedewerkerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Medewerker — personeelsmaster (module HR / Personeelszaken).
 *
 * Surrogaatsleutel; het leesbare `personeelsnummer` is een uniek VELD. BSN is een
 * apart beschermd, versleuteld en toegangsgelogd veld (alleen bevoegde rollen,
 * standaard uit via config).
 *
 * @property string|null $bsn
 */
class Medewerker extends Model
{
    protected $table = 'medewerkers';

    protected $fillable = [
        'personeelsnummer', 'user_id', 'docent_id', 'manager_id', 'afdeling_id', 'functie_id',
        'aanhef', 'voornaam', 'tussenvoegsel', 'achternaam', 'geboortedatum',
        'bsn', 'bsn_hash', 'adres', 'postcode', 'woonplaats', 'telefoon',
        'email', 'email_prive', 'status', 'actief', 'opmerkingen',
    ];

    protected $hidden = ['bsn', 'bsn_hash'];

    protected function casts(): array
    {
        return [
            'geboortedatum' => 'date',
            'status' => MedewerkerStatus::class,
            'actief' => 'boolean',
            'bsn' => VersleuteldGevoeligVeld::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function docent(): BelongsTo
    {
        return $this->belongsTo(Docent::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Medewerker::class, 'manager_id');
    }

    /** De medewerkers waarvan deze medewerker de leidinggevende is (team). */
    public function teamleden(): HasMany
    {
        return $this->hasMany(Medewerker::class, 'manager_id');
    }

    public function afdeling(): BelongsTo
    {
        return $this->belongsTo(Afdeling::class);
    }

    public function functie(): BelongsTo
    {
        return $this->belongsTo(Functie::class);
    }

    public function dienstverbanden(): HasMany
    {
        return $this->hasMany(Dienstverband::class)->orderByDesc('startdatum');
    }

    public function documenten(): HasMany
    {
        return $this->hasMany(HrDocument::class)->latest();
    }

    public function verlofaanvragen(): HasMany
    {
        return $this->hasMany(Verlofaanvraag::class)->orderByDesc('van');
    }

    public function verlofsaldi(): HasMany
    {
        return $this->hasMany(Verlofsaldo::class);
    }

    public function ziekmeldingen(): HasMany
    {
        return $this->hasMany(Ziekmelding::class)->orderByDesc('ziek_van');
    }

    public function gesprekken(): HasMany
    {
        return $this->hasMany(Gesprek::class)->orderByDesc('datum');
    }

    public function checklisttaken(): HasMany
    {
        return $this->hasMany(HrChecklisttaak::class)->orderBy('volgorde')->orderBy('id');
    }

    public function volledigeNaam(): string
    {
        return trim(implode(' ', array_filter([$this->voornaam, $this->tussenvoegsel, $this->achternaam])));
    }

    /** Het lopende dienstverband (geen einddatum of einddatum in de toekomst), nieuwste eerst. */
    public function huidigDienstverband(): ?Dienstverband
    {
        return $this->dienstverbanden->first(fn (Dienstverband $d) => $d->isLopend())
            ?? $this->dienstverbanden->first();
    }

    /** Actuele FTE uit het lopende dienstverband, of null. */
    public function fte(): ?float
    {
        return $this->huidigDienstverband()?->fte();
    }

    /**
     * Beperk tot de medewerkers die deze gebruiker mag zien. HR, Beheer en Bestuur
     * zien iedereen; een Manager ziet uitsluitend het eigen team (plus zichzelf).
     */
    public function scopeZichtbaarVoor($query, User $gebruiker)
    {
        if (! $gebruiker->isHrTeamBeperkt()) {
            return $query;
        }

        $eigen = $gebruiker->medewerker;
        $ids = $eigen
            ? static::where('manager_id', $eigen->id)->pluck('id')->push($eigen->id)
            : collect();

        return $query->whereIn('id', $ids);
    }

    public function zichtbaarVoor(User $gebruiker): bool
    {
        if (! $gebruiker->isHrTeamBeperkt()) {
            return true;
        }

        $eigen = $gebruiker->medewerker;

        return $eigen !== null && ($this->id === $eigen->id || $this->manager_id === $eigen->id);
    }

    /** Mag deze gebruiker dit medewerkerrecord muteren? HR en Beheer (niet de Manager). */
    public function beheerbaarVoor(User $gebruiker): bool
    {
        return $gebruiker->magHrBeheer();
    }
}
