<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Verzendlog voor automatische HR-e-mails (idempotentie). */
class HrNotificatie extends Model
{
    protected $table = 'hr_notificaties';

    protected $fillable = ['type', 'sleutel', 'medewerker_id', 'ontvanger', 'verzonden_op'];

    protected function casts(): array
    {
        return ['verzonden_op' => 'datetime'];
    }

    /**
     * Registreert een verzending idempotent. Geeft true als dit een NIEUWE
     * verzending is (dus mag versturen), false als hij al bestond (overslaan).
     */
    public static function eersteKeer(string $type, string $sleutel, ?int $medewerkerId = null, ?string $ontvanger = null): bool
    {
        $bestond = static::where('type', $type)->where('sleutel', $sleutel)->exists();
        if ($bestond) {
            return false;
        }

        static::create([
            'type' => $type,
            'sleutel' => $sleutel,
            'medewerker_id' => $medewerkerId,
            'ontvanger' => $ontvanger,
        ]);

        return true;
    }
}
