<?php

namespace App\Models;

use App\Enums\Betaalregeling;
use App\Enums\InschrijvingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            'leerjaar' => 'integer',
            'inschrijfdatum' => 'date',
            'invoerdatum' => 'date',
            'uitschrijfdatum' => 'date',
            'afstudeerdatum' => 'date',
        ];
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
}
