<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Vrije notitie bij een organisatie (module Relatiebeheer & Stagebeheer). */
class RelatieNotitie extends Model
{
    protected $table = 'relatie_notities';

    protected $fillable = ['organisatie_id', 'auteur_id', 'categorie', 'tags', 'tekst'];

    public function organisatie(): BelongsTo
    {
        return $this->belongsTo(Organisatie::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
