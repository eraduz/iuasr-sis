<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Doel / KPI onder een HR-gesprek. */
class Gespreksdoel extends Model
{
    protected $table = 'gespreksdoelen';

    protected $fillable = ['gesprek_id', 'omschrijving', 'status'];

    public const STATUSSEN = ['open' => 'Open', 'behaald' => 'Behaald', 'niet_behaald' => 'Niet behaald'];

    public function statusLabel(): string
    {
        return self::STATUSSEN[$this->status] ?? ucfirst((string) $this->status);
    }

    public function gesprek(): BelongsTo
    {
        return $this->belongsTo(Gesprek::class);
    }
}
