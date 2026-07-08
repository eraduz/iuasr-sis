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

    /** Vergelijk op rolsleutel (handig in Blade): $user->rolIs('docent'). */
    public function rolIs(string ...$rollen): bool
    {
        return in_array($this->rol->value, $rollen, true);
    }

    // Doorverwijzingen naar de rol-regels, zodat views en policies hetzelfde
    // vocabulaire delen als de UI (design system).
    public function magCijfersInzien(): bool
    {
        return $this->rol->magCijfersInzien();
    }

    public function magCijfersInvoeren(): bool
    {
        return $this->rol->magCijfersInvoeren();
    }

    public function magInschrijvingBeheren(): bool
    {
        return $this->rol->magInschrijvingBeheren();
    }

    public function magBsnInzien(): bool
    {
        return $this->rol->magBsnInzien();
    }

    public function magFinancieelInzien(): bool
    {
        return $this->rol->magFinancieelInzien();
    }

    public function magCollegegeldBeheren(): bool
    {
        return $this->rol->magCollegegeldBeheren();
    }

    public function magBetalingenRegistreren(): bool
    {
        return $this->rol->magBetalingenRegistreren();
    }

    public function magAlleOndertekendeDocumentenZien(): bool
    {
        return $this->rol->magAlleOndertekendeDocumentenZien();
    }
}
