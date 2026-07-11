<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Competentiebeoordeling onder een HR-gesprek. */
class Competentiescore extends Model
{
    protected $table = 'competentiescores';

    protected $fillable = ['gesprek_id', 'competentie', 'score', 'toelichting'];

    public const SCORES = [
        'onvoldoende' => 'Onvoldoende',
        'voldoende' => 'Voldoende',
        'goed' => 'Goed',
        'uitstekend' => 'Uitstekend',
    ];

    public function scoreLabel(): string
    {
        return self::SCORES[$this->score] ?? ucfirst((string) $this->score);
    }

    public function gesprek(): BelongsTo
    {
        return $this->belongsTo(Gesprek::class);
    }
}
