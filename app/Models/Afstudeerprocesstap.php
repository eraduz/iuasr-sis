<?php

namespace App\Models;

use App\Enums\Afstudeerstap;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén stap van een afstudeerproces. De verantwoordelijke rol en de volgorde
 * komen uit {@see Afstudeerstap}; hier staat de voortgang (gereed + wie/wanneer).
 */
class Afstudeerprocesstap extends Model
{
    protected $table = 'afstudeerprocesstappen';

    protected $fillable = [
        'afstudeerproces_id', 'stap', 'volgorde', 'gereed', 'gereed_op', 'gereed_door_id', 'opmerking',
    ];

    protected function casts(): array
    {
        return [
            'stap' => Afstudeerstap::class,
            'gereed' => 'boolean',
            'gereed_op' => 'datetime',
            'volgorde' => 'integer',
        ];
    }

    public function proces(): BelongsTo
    {
        return $this->belongsTo(Afstudeerproces::class, 'afstudeerproces_id');
    }

    public function gereedDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gereed_door_id');
    }
}
