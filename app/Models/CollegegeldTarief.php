<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Collegegeldtarief voor een studiejaar (periode). opleiding_id null = het
 * standaardtarief voor dat jaar; een specifieke opleiding overschrijft dat.
 */
class CollegegeldTarief extends Model
{
    protected $table = 'collegegeld_tarieven';

    protected $fillable = [
        'periode_id',
        'opleiding_id',
        'bedrag',
        'aantal_termijnen',
        'ingesteld_door_id',
    ];

    protected function casts(): array
    {
        return [
            'bedrag' => 'decimal:2',
            'aantal_termijnen' => 'integer',
        ];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function opleiding(): BelongsTo
    {
        return $this->belongsTo(Opleiding::class);
    }

    public function ingesteldDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ingesteld_door_id');
    }
}
