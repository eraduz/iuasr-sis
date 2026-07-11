<?php

namespace App\Models;

use App\Enums\OvereenkomstStatus;
use App\Enums\OvereenkomstType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Overeenkomst met een organisatie. De verloopdatum stuurt de signalering
 * 'contracten die verlopen'. Een getekende PDF wordt via de ondertekenmodule
 * gewaarmerkt en hier gekoppeld.
 */
class Overeenkomst extends Model
{
    protected $table = 'overeenkomsten';

    protected $fillable = [
        'organisatie_id', 'type', 'titel', 'startdatum', 'verloopdatum',
        'status', 'ondertekend_document_id', 'opmerking',
    ];

    protected function casts(): array
    {
        return [
            'type' => OvereenkomstType::class,
            'status' => OvereenkomstStatus::class,
            'startdatum' => 'date',
            'verloopdatum' => 'date',
        ];
    }

    public function organisatie(): BelongsTo
    {
        return $this->belongsTo(Organisatie::class);
    }

    public function ondertekendDocument(): BelongsTo
    {
        return $this->belongsTo(OndertekendDocument::class, 'ondertekend_document_id');
    }

    /** Is de overeenkomst verlopen (verloopdatum voorbij en niet opgezegd)? */
    public function isVerlopen(): bool
    {
        return $this->verloopdatum !== null
            && $this->status !== OvereenkomstStatus::Opgezegd
            && $this->verloopdatum->isPast();
    }

    /** Aantal dagen tot de verloopdatum (negatief = verstreken), of null. */
    public function dagenTotVerloop(): ?int
    {
        if ($this->verloopdatum === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->verloopdatum, false);
    }

    /** Beperk tot overeenkomsten van organisaties die deze gebruiker mag zien. */
    public function scopeZichtbaarVoor($query, User $gebruiker)
    {
        if (! $gebruiker->isRelatieBeperkt()) {
            return $query;
        }

        return $query->whereHas('organisatie', fn ($q) => $q->whereHas('opleidingen',
            fn ($o) => $o->whereIn('opleidingen.id', $gebruiker->opleidingIds())));
    }
}
