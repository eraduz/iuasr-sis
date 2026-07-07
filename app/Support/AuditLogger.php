<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Schrijft audit-regels voor inzage en mutatie van gevoelige gegevens
 * (cijfers, BSN): wie, wat, wanneer, welk record. Append-only.
 */
class AuditLogger
{
    public const INZAGE = 'inzage';
    public const AANMAAK = 'aanmaak';
    public const WIJZIGING = 'wijziging';
    public const VERWIJDERING = 'verwijdering';
    public const UITGIFTE = 'uitgifte';

    public static function log(
        string $actie,
        Model|string $onderwerp,
        ?int $onderwerpId = null,
        ?string $veld = null,
        array $context = []
    ): void {
        $type = $onderwerp instanceof Model ? class_basename($onderwerp) : $onderwerp;
        $id = $onderwerp instanceof Model ? $onderwerp->getKey() : $onderwerpId;

        AuditLog::create([
            'user_id' => Auth::id(),
            'rol' => Auth::user()?->rol?->value,
            'actie' => $actie,
            'onderwerp_type' => $type,
            'onderwerp_id' => $id,
            'veld' => $veld,
            'ip_adres' => Request::ip(),
            'context' => $context ?: null,
            'gelogd_op' => now(),
        ]);
    }

    /** Kortere helper voor BSN-inzage. */
    public static function bsnInzage(Model $student): void
    {
        self::log(self::INZAGE, $student, veld: 'bsn');
    }
}
