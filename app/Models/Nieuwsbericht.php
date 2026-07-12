<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén opgehaald of handmatig toegevoegd nieuwsbericht. `link_hash` (sha256 van de
 * link) is de unieke sleutel zodat hetzelfde bericht niet dubbel binnenkomt.
 */
class Nieuwsbericht extends Model
{
    protected $table = 'nieuwsberichten';

    protected $fillable = [
        'nieuwsbron_id', 'titel', 'samenvatting', 'link', 'link_hash',
        'gepubliceerd_op', 'opgehaald_op',
    ];

    protected function casts(): array
    {
        return [
            'gepubliceerd_op' => 'datetime',
            'opgehaald_op' => 'datetime',
        ];
    }

    public function bron(): BelongsTo
    {
        return $this->belongsTo(Nieuwsbron::class, 'nieuwsbron_id');
    }

    public static function hashVoor(string $link): string
    {
        return hash('sha256', trim($link));
    }
}
