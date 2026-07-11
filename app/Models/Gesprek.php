<?php

namespace App\Models;

use App\Enums\Gespreksstatus;
use App\Enums\Gesprekstype;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * HR-gesprek (beoordeling/functionering/exit) met doelen en competentiescores.
 */
class Gesprek extends Model
{
    protected $table = 'gesprekken';

    protected $fillable = [
        'medewerker_id', 'type', 'datum', 'gespreksvoerder_id', 'status', 'samenvatting', 'feedback',
    ];

    protected function casts(): array
    {
        return [
            'type' => Gesprekstype::class,
            'status' => Gespreksstatus::class,
            'datum' => 'date',
        ];
    }

    public function medewerker(): BelongsTo
    {
        return $this->belongsTo(Medewerker::class);
    }

    public function gespreksvoerder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gespreksvoerder_id');
    }

    public function doelen(): HasMany
    {
        return $this->hasMany(Gespreksdoel::class);
    }

    public function competentiescores(): HasMany
    {
        return $this->hasMany(Competentiescore::class);
    }

    /** Mag deze gebruiker dit gesprek beheren? Volgt de zichtbaarheid van de medewerker. */
    public function beheerbaarVoor(User $gebruiker): bool
    {
        return $gebruiker->magHrInzien() && $this->medewerker->zichtbaarVoor($gebruiker);
    }
}
