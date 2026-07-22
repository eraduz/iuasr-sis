<?php

namespace App\Models;

use App\Casts\VersleuteldGevoeligVeld;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Student — masterrecord.
 *
 * Principe: de PK `id` is een betekenisloze surrogaatsleutel. Het leesbare
 * `studentnummer` is een uniek VELD, nooit een koppelsleutel. BSN is een
 * apart beschermd, versleuteld en toegangsgelogd veld (alleen bevoegde rollen).
 *
 * @property string|null $bsn  Ontsleuteld bij uitlezen; inzage wordt gelogd.
 */
class Student extends Model
{
    protected $table = 'studenten';

    protected $fillable = [
        'studentnummer',
        'aanhef',
        'voornaam',
        'roepnaam',
        'tussenvoegsel',
        'achternaam',
        'geboortedatum',
        'geboorteplaats',
        'geslacht',
        'nationaliteit_id',
        'land_id',
        'adres',
        'postcode',
        'woonplaats',
        'telefoon',
        'email',
        'email_prive',
        'vooropleiding',
        'diploma',
        'huisnummer',
        'provincie',
        'vorige_instelling',
        'afstudeerjaar',
        'documenten_later',
        'taal_nederlands',
        'taal_arabisch',
        'nt2_examen_vereist',
        'nt2_behaald_op',
        'bsn',
        'bsn_hash',
        'rekeningnummer',
        'opmerkingen',
    ];

    protected function casts(): array
    {
        return [
            'geboortedatum' => 'date',
            'taal_nederlands' => \App\Enums\TaalNiveau::class,
            'taal_arabisch' => \App\Enums\TaalNiveau::class,
            'nt2_examen_vereist' => 'boolean',
            'nt2_behaald_op' => 'date',
            'documenten_later' => 'boolean',
            // Gevoelige velden: versleuteld opgeslagen (AVG).
            'bsn' => VersleuteldGevoeligVeld::class,
            'rekeningnummer' => VersleuteldGevoeligVeld::class,
        ];
    }

    // BSN en rekeningnummer nooit per ongeluk in arrays/JSON tonen.
    protected $hidden = [
        'bsn',
        'bsn_hash',
        'rekeningnummer',
    ];

    public function afstudeerprocessen(): HasMany
    {
        return $this->hasMany(Afstudeerproces::class);
    }

    /** Notities van de examencommissie (hun eigen werkaantekeningen); nieuwste eerst. */
    public function examencommissieNotities(): HasMany
    {
        return $this->hasMany(ExamencommissieNotitie::class)->latest();
    }

    public function inschrijvingen(): HasMany
    {
        return $this->hasMany(Inschrijving::class);
    }

    /**
     * Beperk een studentenquery tot wat een gebruiker mag zien. Voor
     * opleidinggebonden rollen (Directie) blijven alleen studenten over met een
     * ACTIEVE inschrijving in een toegewezen opleiding. Overige rollen zien
     * alles (binnen hun rolrechten). Dubbele inschrijving: een student die óók
     * een andere opleiding volgt, is zichtbaar voor beide directies.
     */
    public function scopeZichtbaarVoor($query, \App\Models\User $gebruiker)
    {
        if (! $gebruiker->isOpleidingBeperkt()) {
            return $query;
        }

        $ids = $gebruiker->opleidingIds();

        return $query->whereHas('inschrijvingen', fn ($iq) => $iq
            ->where('status', 'actief')
            ->whereIn('opleiding_id', $ids));
    }

    /** Mag deze gebruiker dit specifieke studentdossier openen? */
    public function zichtbaarVoor(\App\Models\User $gebruiker): bool
    {
        if (! $gebruiker->isOpleidingBeperkt()) {
            return true;
        }

        return $this->inschrijvingen
            ->where('status', \App\Enums\InschrijvingStatus::Actief)
            ->pluck('opleiding_id')
            ->intersect($gebruiker->opleidingIds())
            ->isNotEmpty();
    }

    /** Interne notities (Studentenzaken), nieuwste eerst. */
    public function notities(): HasMany
    {
        return $this->hasMany(StudentNotitie::class)->latest();
    }

    /** Ontvangen documenten (identiteitsbewijs, diploma, cijferlijst, pasfoto, ...). */
    public function documenten(): HasMany
    {
        return $this->hasMany(StudentDocument::class)->latest();
    }

    public function nationaliteit(): BelongsTo
    {
        return $this->belongsTo(Nationaliteit::class);
    }

    public function land(): BelongsTo
    {
        return $this->belongsTo(Land::class);
    }

    /** Resultaten lopen via de inschrijving; hier een snelkoppeling. */
    public function resultaten(): HasMany
    {
        return $this->hasMany(Resultaat::class);
    }

    /** Stages (plaatsingen) van deze student — het stageverleden. */
    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class);
    }

    /** Scriptietrajecten van deze student (module Scriptie Coördinatie). */
    public function scripties(): HasMany
    {
        return $this->hasMany(Scriptie::class);
    }

    public function betalingen(): HasMany
    {
        return $this->hasMany(Betaling::class);
    }

    /**
     * NT2-deadline: de student heeft 1 jaar vanaf de (eerste) inschrijfdatum om
     * het NT2-examen succesvol af te ronden. Null als NT2 niet vereist is of er
     * geen inschrijfdatum bekend is.
     */
    public function nt2Deadline(): ?\Carbon\Carbon
    {
        if (! $this->nt2_examen_vereist) {
            return null;
        }
        $eerste = $this->inschrijvingen->min('inschrijfdatum');
        if (! $eerste) {
            return null;
        }

        return \Carbon\Carbon::parse($eerste)->addYear()->startOfDay();
    }

    /** niet_vereist | behaald | open | verlopen */
    public function nt2Status(): string
    {
        if (! $this->nt2_examen_vereist) {
            return 'niet_vereist';
        }
        if ($this->nt2_behaald_op) {
            return 'behaald';
        }
        $deadline = $this->nt2Deadline();
        if (! $deadline) {
            return 'open';
        }

        return now()->startOfDay()->gt($deadline) ? 'verlopen' : 'open';
    }

    /** Aantal dagen tot de NT2-deadline (negatief = termijn verstreken). */
    public function nt2DagenResterend(): ?int
    {
        $deadline = $this->nt2Deadline();
        if (! $deadline) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($deadline, false);
    }

    public function volledigeNaam(): string
    {
        return trim(implode(' ', array_filter([
            $this->voornaam,
            $this->tussenvoegsel,
            $this->achternaam,
        ])));
    }

    /**
     * Actieve inschrijvingen, ontdubbeld op opleiding. Volgt de student twee
     * opleidingen tegelijk (dubbele inschrijving), dan staan hier twee regels.
     * Vereist dat de relatie `inschrijvingen.opleiding` geladen is.
     *
     * @return \Illuminate\Support\Collection<int,\App\Models\Inschrijving>
     */
    public function actieveInschrijvingen(): \Illuminate\Support\Collection
    {
        return $this->inschrijvingen
            ->where('status', \App\Enums\InschrijvingStatus::Actief)
            ->unique('opleiding_id')
            ->sortBy(fn ($i) => $i->opleiding?->naam)
            ->values();
    }

    /**
     * Unieke actieve opleidingen van de student.
     *
     * @return \Illuminate\Support\Collection<int,\App\Models\Opleiding>
     */
    public function actieveOpleidingen(): \Illuminate\Support\Collection
    {
        return $this->actieveInschrijvingen()
            ->map(fn ($i) => $i->opleiding)
            ->filter()
            ->values();
    }

    /**
     * Alumnus: de student is van minstens één opleiding afgestudeerd. Afgeleid
     * (geen kolom): blijft kloppen ook wanneer de student later een andere
     * opleiding volgt met hetzelfde studentnummer.
     */
    public function isAlumnus(): bool
    {
        return $this->inschrijvingen
            ->contains(fn ($i) => $i->status === \App\Enums\InschrijvingStatus::Afgestudeerd);
    }

    /**
     * De opleidingen waarvan de student is afgestudeerd (voor informatie op het
     * dossier; de inschrijvingen zelf blijven bewaard).
     *
     * @return \Illuminate\Support\Collection<int,\App\Models\Opleiding>
     */
    public function afgerondeOpleidingen(): \Illuminate\Support\Collection
    {
        return $this->inschrijvingen
            ->where('status', \App\Enums\InschrijvingStatus::Afgestudeerd)
            ->map(fn ($i) => $i->opleiding)
            ->filter()
            ->unique('id')
            ->sortBy('naam')
            ->values();
    }

    /** Volgt de student op dit moment meer dan één opleiding tegelijk? */
    public function heeftDubbeleInschrijving(): bool
    {
        return $this->actieveInschrijvingen()->count() > 1;
    }
}
