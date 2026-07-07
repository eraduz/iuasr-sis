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
        'taal_nederlands',
        'taal_arabisch',
        'nt2_examen_vereist',
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

    public function inschrijvingen(): HasMany
    {
        return $this->hasMany(Inschrijving::class);
    }

    /** Interne notities (Studentenzaken), nieuwste eerst. */
    public function notities(): HasMany
    {
        return $this->hasMany(StudentNotitie::class)->latest();
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

    public function volledigeNaam(): string
    {
        return trim(implode(' ', array_filter([
            $this->voornaam,
            $this->tussenvoegsel,
            $this->achternaam,
        ])));
    }
}
