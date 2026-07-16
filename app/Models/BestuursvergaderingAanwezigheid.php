<?php

namespace App\Models;

use App\Enums\Aanwezigheid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * De aanwezigheid van één bestuurslid bij één vergadering.
 */
class BestuursvergaderingAanwezigheid extends Model
{
    protected $table = 'bestuursvergadering_aanwezigheden';

    protected $fillable = [
        'bestuursvergadering_id', 'bestuurslid_id', 'aanwezigheid',
    ];

    protected function casts(): array
    {
        return [
            'aanwezigheid' => Aanwezigheid::class,
        ];
    }

    public function vergadering(): BelongsTo
    {
        return $this->belongsTo(Bestuursvergadering::class, 'bestuursvergadering_id');
    }

    public function bestuurslid(): BelongsTo
    {
        return $this->belongsTo(Bestuurslid::class);
    }
}
