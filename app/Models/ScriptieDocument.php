<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een document binnen een scriptietraject, op de private schijf, met versiebeheer
 * via {@see self::vorigeVersie()}. De laatste versie is degene waar geen andere
 * versie naar terugverwijst.
 */
class ScriptieDocument extends Model
{
    protected $table = 'scriptie_documenten';

    /** De categorieën (sleutel => label). */
    public const CATEGORIEEN = [
        'plan_van_aanpak' => 'Plan van Aanpak',
        'eindversie' => 'Eindversie scriptie',
        'plagiaatrapport' => 'Plagiaatrapport',
        'presentatie' => 'Presentatie',
        'beoordelingsformulier' => 'Beoordelingsformulier',
        'overig' => 'Overig',
    ];

    protected $fillable = [
        'scriptie_id', 'categorie', 'titel', 'bestandsnaam', 'pad',
        'mime', 'grootte', 'versie', 'vorige_versie_id', 'geupload_door_id',
    ];

    protected function casts(): array
    {
        return [
            'versie' => 'integer',
            'grootte' => 'integer',
        ];
    }

    public function scriptie(): BelongsTo
    {
        return $this->belongsTo(Scriptie::class);
    }

    public function geuploadDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'geupload_door_id');
    }

    public function vorigeVersie(): BelongsTo
    {
        return $this->belongsTo(ScriptieDocument::class, 'vorige_versie_id');
    }

    public function categorieLabel(): string
    {
        return self::CATEGORIEEN[$this->categorie] ?? $this->categorie;
    }

    /** Is dit de huidige (laatste) versie? Dan verwijst niets ernaar terug. */
    public function isHuidigeVersie(): bool
    {
        return ! static::where('vorige_versie_id', $this->id)->exists();
    }

    public function isAfbeelding(): bool
    {
        return in_array($this->mime, ['image/jpeg', 'image/png', 'image/webp'], true);
    }
}
