<?php

namespace App\Models;

use App\Enums\Rol;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Medewerker-account. Authenticatie verloopt via Microsoft Entra ID (SSO/OIDC);
 * het systeem beheert zelf GEEN wachtwoorden. De rol volgt bij voorkeur uit de
 * Entra-groep en wordt bij elke actie server-side gecontroleerd.
 *
 * @property Rol $rol
 */
class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'naam',
        'email',
        'entra_oid',
        'rol',
        'docent_id',
        'actief',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'rol' => Rol::class,
            'actief' => 'boolean',
            'laatst_ingelogd_op' => 'datetime',
        ];
    }

    /** Koppeling naar het docentprofiel (voor de rol Docent — eigen vak). */
    public function docent()
    {
        return $this->belongsTo(Docent::class);
    }

    /** Resultaten die deze gebruiker heeft ingevoerd (auditspoor). */
    public function ingevoerdeResultaten(): HasMany
    {
        return $this->hasMany(Resultaat::class, 'ingevoerd_door_id');
    }

    public function heeftRol(Rol $rol): bool
    {
        return $this->rol === $rol;
    }
}
