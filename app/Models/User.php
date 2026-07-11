<?php

namespace App\Models;

use App\Enums\Rol;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

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

    /**
     * Opleidingen waaraan een directielid is toegewezen. Een directeur ziet
     * uitsluitend studenten/cijfers/rapporten van deze opleiding(en).
     */
    public function opleidingen(): BelongsToMany
    {
        return $this->belongsToMany(Opleiding::class, 'directie_opleidingen');
    }

    /**
     * Is de zichtbaarheid van deze gebruiker beperkt tot bepaalde opleidingen?
     * Alleen de rol Directie is opleidinggebonden; overige rollen zien alles
     * (binnen hun eigen rolrechten).
     */
    public function isOpleidingBeperkt(): bool
    {
        return $this->rol === Rol::Directie;
    }

    /** Het eigen personeelsrecord (module HR), voor self-service en team-scoping. */
    public function medewerker(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Medewerker::class);
    }

    // --- Module HR / Personeelszaken ---

    public function magHrBeheer(): bool
    {
        return $this->rol->magHrBeheer();
    }

    public function magHrInzien(): bool
    {
        return $this->rol->magHrInzien();
    }

    public function isHrTeamBeperkt(): bool
    {
        return $this->rol->isHrTeamBeperkt();
    }

    public function magVerlofBeoordelen(): bool
    {
        return $this->rol->magVerlofBeoordelen();
    }

    /**
     * De opleiding-ids die deze gebruiker mag zien.
     *
     * @return \Illuminate\Support\Collection<int,int>
     */
    public function opleidingIds(): Collection
    {
        return $this->opleidingen->pluck('id');
    }

    /** Resultaten die deze gebruiker heeft ingevoerd (auditspoor). */
    public function ingevoerdeResultaten(): HasMany
    {
        return $this->hasMany(Resultaat::class, 'ingevoerd_door_id');
    }

    /**
     * Cursussen waarvan deze gebruiker de directeur is (module Cursussen). Een
     * cursusdirecteur ziet en beheert uitsluitend deze cursus(sen).
     */
    public function gedirigeerdeCursussen(): HasMany
    {
        return $this->hasMany(Cursus::class, 'directeur_id');
    }

    /**
     * Is de zichtbaarheid van deze gebruiker in de Cursussen-module beperkt tot
     * de eigen cursus(sen)? Alleen de cursusadministratie (cursusdirecteur).
     */
    public function isCursusBeperkt(): bool
    {
        return $this->rol->isCursusBeperkt();
    }

    /**
     * De cursus-ids die deze gebruiker mag zien/beheren.
     *
     * @return \Illuminate\Support\Collection<int,int>
     */
    public function cursusIds(): Collection
    {
        return $this->gedirigeerdeCursussen()->pluck('id');
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

    public function magPresentieRegistreren(): bool
    {
        return $this->rol->magPresentieRegistreren();
    }

    public function magPresentieInzien(): bool
    {
        return $this->rol->magPresentieInzien();
    }

    public function magAanwezigheidsregelingZien(): bool
    {
        return $this->rol->magAanwezigheidsregelingZien();
    }

    public function magAanwezigheidsregelingBeheren(): bool
    {
        return $this->rol->magAanwezigheidsregelingBeheren();
    }

    public function magTakenBeheren(): bool
    {
        return $this->rol->magTakenBeheren();
    }

    public function magCursusBeheer(): bool
    {
        return $this->rol->magCursusBeheer();
    }

    public function magCursusFinancien(): bool
    {
        return $this->rol->magCursusFinancien();
    }

    public function magCursusInzien(): bool
    {
        return $this->rol->magCursusInzien();
    }

    // --- Module Relatiebeheer & Stagebeheer ---

    public function magRelatiebeheer(): bool
    {
        return $this->rol->magRelatiebeheer();
    }

    public function magStagebeheer(): bool
    {
        return $this->rol->magStagebeheer();
    }

    public function magRelatieInzien(): bool
    {
        return $this->rol->magRelatieInzien();
    }

    /**
     * Is de zichtbaarheid binnen Relatiebeheer beperkt tot de eigen opleiding(en)?
     * De koppeling loopt via dezelfde opleiding-toewijzing als de Directie
     * (`opleidingen()` → directie_opleidingen).
     */
    public function isRelatieBeperkt(): bool
    {
        return $this->rol->isRelatieBeperkt();
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
