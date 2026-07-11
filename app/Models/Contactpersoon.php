<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contactpersoon bij een organisatie (module Relatiebeheer & Stagebeheer).
 * Persoonsgegevens van een externe; mutaties gelogd, inactiveren i.p.v. wissen.
 */
class Contactpersoon extends Model
{
    protected $table = 'contactpersonen';

    protected $fillable = [
        'organisatie_id', 'voornaam', 'achternaam', 'functie', 'email',
        'mobiel', 'telefoon', 'afdeling', 'voorkeur_communicatie', 'linkedin', 'actief',
    ];

    protected function casts(): array
    {
        return ['actief' => 'boolean'];
    }

    public function organisatie(): BelongsTo
    {
        return $this->belongsTo(Organisatie::class);
    }

    public function volledigeNaam(): string
    {
        return trim($this->voornaam.' '.$this->achternaam);
    }

    public function scopeActief($query)
    {
        return $query->where('actief', true);
    }
}
