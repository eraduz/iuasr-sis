<?php

namespace App\Models;

use App\Enums\Quotesoort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Een quote in de zijbalk: een Schone Naam van Allah of een eigen spreuk.
 *
 * @property Quotesoort $soort
 */
class Quote extends Model
{
    /** Cachesleutel voor de actieve quotes; de zijbalk rendert op ELKE pagina. */
    public const CACHE_SLEUTEL = 'quotes.actief';

    protected $table = 'quotes';

    protected $fillable = [
        'soort',
        'titel',
        'arabisch',
        'betekenis',
        'bron',
        'afbeelding_pad',
        'volgorde',
        'actief',
    ];

    protected function casts(): array
    {
        return [
            'soort' => Quotesoort::class,
            'actief' => 'boolean',
        ];
    }

    /**
     * De zijbalk staat op elke pagina, dus de lijst wordt gecachet. Elke mutatie
     * leegt die cache meteen — anders zou een nieuwe spreuk pas na de TTL
     * verschijnen en zou de Beheerder denken dat opslaan niet werkte.
     */
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_SLEUTEL));
        static::deleted(fn () => Cache::forget(self::CACHE_SLEUTEL));
    }

    public function scopeActief(Builder $query): Builder
    {
        return $query->where('actief', true);
    }

    /** Vaste volgorde: zonder tie-break op id zou de roulatie kunnen verspringen. */
    public function scopeGeordend(Builder $query): Builder
    {
        return $query->orderBy('volgorde')->orderBy('id');
    }

    public function heeftAfbeelding(): bool
    {
        return $this->afbeelding_pad !== null;
    }

    /** Wat er in de zijbalk boven de betekenis staat: transliteratie of kop. */
    public function kop(): ?string
    {
        return $this->titel;
    }
}
