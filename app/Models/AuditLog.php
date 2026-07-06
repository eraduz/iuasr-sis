<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AuditLog — onwijzigbaar spoor van inzage en mutatie van gevoelige gegevens
 * (cijfers en BSN). Legt vast: wie, wat, wanneer, welk record.
 *
 * Regels: records worden alleen toegevoegd, nooit gewijzigd of verwijderd via
 * de applicatie (append-only). De timestamp `gelogd_op` is leidend.
 */
class AuditLog extends Model
{
    // Append-only: geen created_at/updated_at; de timestamp is `gelogd_op`.
    public $timestamps = false;

    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id',
        'rol',
        'actie',           // inzage | aanmaak | wijziging | verwijdering
        'onderwerp_type',  // bv. Resultaat, Student (BSN)
        'onderwerp_id',
        'veld',            // bv. bsn, cijfer
        'ip_adres',
        'context',
        'gelogd_op',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'gelogd_op' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
