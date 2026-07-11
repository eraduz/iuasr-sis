<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Document bij een organisatie (module Relatiebeheer & Stagebeheer), met
 * versiebeheer. Bestand op de private schijf; inzage/afgifte gelogd.
 */
class RelatieDocument extends Model
{
    protected $table = 'relatie_documenten';

    protected $fillable = [
        'organisatie_id', 'stage_id', 'categorie', 'titel', 'bestandsnaam',
        'pad', 'mime', 'grootte', 'versie', 'vorige_versie_id', 'geupload_door_id',
    ];

    protected function casts(): array
    {
        return ['versie' => 'integer', 'grootte' => 'integer'];
    }

    /** Documentcategorieën binnen de module. */
    public const CATEGORIEEN = [
        'stagecontract' => 'Stagecontract',
        'convenant' => 'Convenant',
        'beoordeling' => 'Beoordeling',
        'verslag' => 'Verslag',
        'correspondentie' => 'Correspondentie',
        'foto' => 'Foto',
        'certificaat' => 'Certificaat',
        'overig' => 'Overig',
    ];

    public function categorieLabel(): string
    {
        return self::CATEGORIEEN[$this->categorie] ?? ucfirst((string) $this->categorie);
    }

    public function organisatie(): BelongsTo
    {
        return $this->belongsTo(Organisatie::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function geuploadDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'geupload_door_id');
    }

    public function vorigeVersie(): BelongsTo
    {
        return $this->belongsTo(RelatieDocument::class, 'vorige_versie_id');
    }

    /** Is dit de meest recente versie (er verwijst geen nieuwer document naar dit)? */
    public function isHuidigeVersie(): bool
    {
        return ! static::where('vorige_versie_id', $this->id)->exists();
    }
}
