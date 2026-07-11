<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Afdeling / team (module HR). Een team is een afdeling met een bovenliggende afdeling. */
class Afdeling extends Model
{
    protected $table = 'afdelingen';

    protected $fillable = ['code', 'naam', 'bovenliggende_afdeling_id', 'manager_id', 'actief'];

    protected function casts(): array
    {
        return ['actief' => 'boolean'];
    }

    public function bovenliggende(): BelongsTo
    {
        return $this->belongsTo(Afdeling::class, 'bovenliggende_afdeling_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Medewerker::class, 'manager_id');
    }

    public function medewerkers(): HasMany
    {
        return $this->hasMany(Medewerker::class);
    }

    public function scopeActief($query)
    {
        return $query->where('actief', true);
    }
}
