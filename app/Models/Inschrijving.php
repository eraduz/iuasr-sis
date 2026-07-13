<?php

namespace App\Models;

use App\Enums\Betaalregeling;
use App\Enums\InschrijvingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Inschrijving — de lifecycle per periode/leerjaar. Eén student kan meerdere
 * inschrijvingen hebben; bij herinschrijving blijft dezelfde interne
 * studentsleutel behouden en ontstaat een nieuwe inschrijving.
 *
 * Lifecycle-velden: inschrijfdatum (start collegegeldplicht), uitschrijfdatum,
 * afstudeerdatum, status.
 */
class Inschrijving extends Model
{
    protected $table = 'inschrijvingen';

    protected $fillable = [
        'student_id',
        'opleiding_id',
        'klas_id',
        'periode_id',
        'leerjaar',
        'status',              // aangemeld | actief | uitgeschreven | afgestudeerd
        'inschrijfdatum',
        'invoerdatum',
        'uitschrijfdatum',
        'afstudeerdatum',
        'vervroegd_afstuderen', // examencommissie: afstuderen vrijgegeven buiten laatste leerjaar
        'betaalwijze',         // VERVALLEN: mengde regeling en betaalwijze; zie betaalregeling
        'betaalregeling',      // termijnen (5 facturen) | volledig (1 factuur)
        'korting_percentage',  // korting op het jaartarief van DEZE opleiding
        'korting_reden',
        'aanwezigheidsregeling_50',
        'opmerkingen',
    ];

    protected function casts(): array
    {
        return [
            'status' => InschrijvingStatus::class,
            'betaalregeling' => Betaalregeling::class,
            'korting_percentage' => 'float',
            'aanwezigheidsregeling_50' => 'boolean',
            'vervroegd_afstuderen' => 'boolean',
            'leerjaar' => 'integer',
            'inschrijfdatum' => 'date',
            'invoerdatum' => 'date',
            'uitschrijfdatum' => 'date',
            'afstudeerdatum' => 'date',
        ];
    }

    /**
     * Afgestudeerd = terminale eindstatus: de opleiding is afgerond. De
     * inschrijving wordt daarmee bevroren (alleen-lezen historie) — geen korting,
     * betaalregeling, aanwezigheidsregeling, vaktoewijziging, schorsen of
     * uitschrijven meer. Zie de guards in de betreffende controllers.
     */
    public function isAfgestudeerd(): bool
    {
        return $this->status === InschrijvingStatus::Afgestudeerd;
    }

    /**
     * Lopend = de student volgt de opleiding nú (actief of geschorst). Alleen dan
     * zijn lifecycle-acties (schorsen, uitschrijven, afstuderen) zinvol.
     */
    public function isLopend(): bool
    {
        return in_array($this->status, [InschrijvingStatus::Actief, InschrijvingStatus::Geschorst], true);
    }

    /**
     * Zit deze inschrijving in het LAATSTE leerjaar van de opleiding? Bepaald uit
     * `opleidingen.nominale_jaren`. Is dat niet vastgelegd, dan is het laatste jaar
     * onbekend en telt dit als false (afstuderen dan niet toegestaan).
     */
    public function isLaatsteLeerjaar(): bool
    {
        $nominaal = $this->opleiding?->nominale_jaren;

        return $nominaal !== null && (int) $this->leerjaar === (int) $nominaal;
    }

    /**
     * Afstuderen kan uitsluitend vanuit een lopende inschrijving, in het laatste
     * leerjaar — OF eerder wanneer de examencommissie vervroegd afstuderen heeft
     * vrijgegeven (zeldzaam: bij vrijstellingen/eerder behaalde EC).
     */
    public function magAfstuderen(): bool
    {
        return $this->isLopend() && ($this->isLaatsteLeerjaar() || (bool) $this->vervroegd_afstuderen);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function opleiding(): BelongsTo
    {
        return $this->belongsTo(Opleiding::class);
    }

    public function klas(): BelongsTo
    {
        return $this->belongsTo(Klas::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function resultaten(): HasMany
    {
        return $this->hasMany(Resultaat::class);
    }

    public function betalingen(): HasMany
    {
        return $this->hasMany(Betaling::class);
    }

    public function vaktoewijzingen(): HasMany
    {
        return $this->hasMany(Vaktoewijzing::class);
    }

    public function presenties(): HasMany
    {
        return $this->hasMany(Presentie::class);
    }

    public function afstudeerproces(): HasOne
    {
        return $this->hasOne(Afstudeerproces::class);
    }
}
