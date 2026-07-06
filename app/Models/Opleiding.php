<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Opzoektabel Opleiding (bv. Bachelor Islamitische Theologie, PABO, cursussen).
 *
 * De normen zijn data-gedreven per opleiding: `voldoende_grens` en
 * `ec_overgang_drempel` zijn nullable zolang ze niet zijn bevestigd
 * (openstaande parameters — niet zelf invullen).
 *
 * @property float|null $voldoende_grens
 * @property int|null   $ec_overgang_drempel
 */
class Opleiding extends Model
{
    protected $table = 'opleidingen';

    protected $fillable = [
        'faculteit_id',
        'code',
        'naam',
        'soort',            // bachelor | master | premaster | cursus | ...
        'nominale_jaren',
        'ec_totaal',
        'voldoende_grens',       // TE BEVESTIGEN per opleiding
        'ec_overgang_drempel',   // TE BEVESTIGEN per opleiding
        'actief',
    ];

    protected function casts(): array
    {
        return [
            'nominale_jaren' => 'integer',
            'ec_totaal' => 'integer',
            'voldoende_grens' => 'decimal:1',
            'ec_overgang_drempel' => 'integer',
            'actief' => 'boolean',
        ];
    }

    public function faculteit(): BelongsTo
    {
        return $this->belongsTo(Faculteit::class);
    }

    public function klassen(): HasMany
    {
        return $this->hasMany(Klas::class);
    }

    public function vakken(): HasMany
    {
        return $this->hasMany(Vak::class);
    }
}
