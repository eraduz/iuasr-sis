<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Interne notitie bij een medewerker (module HR / Personeelszaken). Doorlopend
 * logboek van contactmomenten — e-mails, telefoongesprekken, gespreksverslagen.
 * Werkinformatie; bevat geen BSN.
 */
class MedewerkerNotitie extends Model
{
    protected $table = 'medewerker_notities';

    protected $fillable = ['medewerker_id', 'gebruiker_id', 'tekst'];

    public function medewerker(): BelongsTo
    {
        return $this->belongsTo(Medewerker::class);
    }

    public function gebruiker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gebruiker_id');
    }
}
