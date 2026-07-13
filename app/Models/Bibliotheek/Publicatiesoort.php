<?php

namespace App\Models\Bibliotheek;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Soort publicatie: boek, tijdschrift, digitaal document, cd, dvd — en wat de
 * bibliotheek er in de toekomst nog aan toevoegt. Een opzoektabel, geen vaste
 * lijst in de code, zodat de bibliotheekmedewerker zelf een soort kan aanmaken.
 *
 * De twee vlaggen bepalen hoe het systeem zich gedraagt; daarom zijn ze geen
 * cosmetiek maar de kern van dit model:
 *
 *   - heeft_exemplaren: fysieke exemplaren die uitgeleend worden (boek, cd, dvd);
 *     een digitaal document niet.
 *   - heeft_uitgaven: afleveringen met artikelen (alleen een tijdschrift).
 */
class Publicatiesoort extends Model
{
    protected $table = 'bibliotheek_soorten';

    protected $fillable = ['code', 'naam', 'heeft_exemplaren', 'heeft_uitgaven', 'actief', 'volgorde'];

    protected function casts(): array
    {
        return [
            'heeft_exemplaren' => 'boolean',
            'heeft_uitgaven' => 'boolean',
            'actief' => 'boolean',
            'volgorde' => 'integer',
        ];
    }

    public function publicaties(): HasMany
    {
        return $this->hasMany(Publicatie::class, 'soort_id');
    }

    public function scopeActief(Builder $query): Builder
    {
        return $query->where('actief', true);
    }

    public function scopeGeordend(Builder $query): Builder
    {
        return $query->orderBy('volgorde')->orderBy('naam');
    }

    /** Kent dit soort fysieke exemplaren die uitgeleend worden? */
    public function heeftExemplaren(): bool
    {
        return $this->heeft_exemplaren;
    }

    /** Kent dit soort uitgaven met artikelen (tijdschrift)? */
    public function heeftUitgaven(): bool
    {
        return $this->heeft_uitgaven;
    }

    /** Voor de UI: dezelfde naam als voorheen de enum-label. */
    public function label(): string
    {
        return $this->naam;
    }

    /** Kan deze soort worden verwijderd? Alleen als er geen titels aan hangen. */
    public function verwijderbaar(): bool
    {
        return $this->publicaties()->doesntExist();
    }

    /** De soort met deze code (bijv. 'boek'); handig in import en seeders. */
    public static function metCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }
}
