<?php

namespace App\Models\Bibliotheek;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Auteur. Eén record per auteur, gedeeld door publicaties én tijdschriftartikelen,
 * zodat "alle publicaties van deze auteur" één query is en de naam maar op één
 * plek staat (ook in Arabisch schrift).
 */
class Auteur extends Model
{
    protected $table = 'bibliotheek_auteurs';

    protected $fillable = ['naam', 'opmerking'];

    public function publicaties(): BelongsToMany
    {
        return $this->belongsToMany(Publicatie::class, 'bibliotheek_publicatie_auteur', 'auteur_id', 'publicatie_id');
    }

    public function artikelen(): BelongsToMany
    {
        return $this->belongsToMany(Artikel::class, 'bibliotheek_artikel_auteur', 'auteur_id', 'artikel_id');
    }

    /**
     * Zoekt de auteur op naam of maakt hem aan. Zo kan de bibliotheekmedewerker
     * auteurs als vrije tekst intypen zonder dubbele records te krijgen.
     *
     * @param  array<int,string>  $namen
     * @return array<int,int>
     */
    public static function idsVoorNamen(array $namen): array
    {
        return collect($namen)
            ->map(fn ($naam) => trim((string) $naam))
            ->filter()
            ->unique()
            ->map(fn ($naam) => static::firstOrCreate(['naam' => $naam])->id)
            ->values()
            ->all();
    }
}
