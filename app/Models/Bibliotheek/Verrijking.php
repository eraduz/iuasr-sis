<?php

namespace App\Models\Bibliotheek;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uitkomst van één bevraging van een externe bibliografische bron voor één titel.
 * Zie de create-migratie voor de motivering. Kern: alleen een ZEKERE match leidt
 * tot een wijziging; twijfel wordt vastgelegd maar nooit toegepast.
 */
class Verrijking extends Model
{
    protected $table = 'bibliotheek_verrijkingen';

    public $timestamps = false;

    public const TOEGEPAST = 'toegepast';
    public const ONZEKER = 'onzeker';
    public const GEEN_TREFFER = 'geen_treffer';
    public const FOUT = 'fout';

    protected $fillable = [
        'publicatie_id', 'bron', 'status', 'gevonden_titel', 'gevonden_auteur',
        'isbn', 'jaar', 'score', 'oude_titel', 'oude_auteur', 'toelichting', 'opgehaald_op',
    ];

    protected function casts(): array
    {
        return [
            'jaar' => 'integer',
            'score' => 'float',
            'opgehaald_op' => 'datetime',
        ];
    }

    public function publicatie(): BelongsTo
    {
        return $this->belongsTo(Publicatie::class, 'publicatie_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::TOEGEPAST => 'Toegepast',
            self::ONZEKER => 'Onzeker — niet toegepast',
            self::GEEN_TREFFER => 'Geen treffer',
            self::FOUT => 'Fout bij het ophalen',
            default => $this->status,
        };
    }
}
