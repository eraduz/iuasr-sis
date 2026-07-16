<?php

namespace App\Models;

use App\Enums\Scriptiestap;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * De stand van één stap binnen een scriptietraject: status, gereed-markering en
 * wie/wanneer. De stap-definitie (label, volgorde, verantwoordelijke, toegestane
 * statussen) leeft in de enum {@see Scriptiestap}.
 */
class ScriptieStapstand extends Model
{
    protected $table = 'scriptie_stapstanden';

    protected $fillable = [
        'scriptie_id', 'stap', 'volgorde', 'status',
        'gereed', 'gereed_op', 'gereed_door_id', 'opmerking',
    ];

    protected function casts(): array
    {
        return [
            'stap' => Scriptiestap::class,
            'gereed' => 'boolean',
            'gereed_op' => 'datetime',
        ];
    }

    public function scriptie(): BelongsTo
    {
        return $this->belongsTo(Scriptie::class);
    }

    public function gereedDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gereed_door_id');
    }

    /** Het leesbare label van de huidige status van deze stap. */
    public function statusLabel(): string
    {
        return $this->stap->statusLabel($this->status);
    }

    /** De CSS-badgeklasse (design system) bij de huidige status. */
    public function badge(): string
    {
        return $this->stap->badge($this->status);
    }
}
