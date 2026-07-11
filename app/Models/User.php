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
 * Multi-rol: `rol` is de PRIMAIRE rol (startdashboard, standaard-scoping,
 * weergave). Aanvullende rollen staan in {@see Roltoewijzing}. De rechten worden
 * als UNIE over {@see self::alleRollen()} bepaald: een gebruiker mag iets zodra
 * één van zijn rollen dat toestaat. De scoping (opleiding/cursus/relatie) volgt
 * de ruimste rol: beperkt blijft men alleen als géén rol brede inzage geeft.
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

    // --- Rollen (multi-rol) ---

    /** De aanvullende (niet-primaire) rollen van deze gebruiker. */
    public function rolToewijzingen(): HasMany
    {
        return $this->hasMany(Roltoewijzing::class);
    }

    /**
     * Alle rollen van deze gebruiker: de primaire rol plus de extra rollen,
     * ontdubbeld. Dit is de bron voor élke rechtenbeslissing (unie).
     *
     * @return Collection<int, Rol>
     */
    public function alleRollen(): Collection
    {
        return collect([$this->rol])
            ->merge($this->rolToewijzingen->pluck('rol'))
            ->unique(fn (Rol $r) => $r->value)
            ->values();
    }

    /** Uitsluitend de extra rollen (zonder de primaire). @return Collection<int, Rol> */
    public function extraRollen(): Collection
    {
        return $this->alleRollen()->reject(fn (Rol $r) => $r === $this->rol)->values();
    }

    /** @return array<int, string> de rolsleutels van alle rollen. */
    public function rolSleutels(): array
    {
        return $this->alleRollen()->map(fn (Rol $r) => $r->value)->all();
    }

    /** Waar zodra één van de rollen aan de regel voldoet (unie over de rolset). */
    private function magVolgensRol(callable $regel): bool
    {
        return $this->alleRollen()->contains(fn (Rol $r) => $regel($r));
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
     * Alleen de rol Directie is opleidinggebonden. Bij multi-rol geldt de ruimste
     * rol: heeft de gebruiker naast Directie óók een rol die alle opleidingen ziet
     * (bijv. Studentenzaken of Beheerder), dan is hij niet beperkt.
     */
    public function isOpleidingBeperkt(): bool
    {
        if (! $this->heeftRol(Rol::Directie)) {
            return false;
        }

        return ! $this->magVolgensRol(fn (Rol $r) => $r !== Rol::Directie && $r->zietAlleOpleidingen());
    }

    /** Het eigen personeelsrecord (module HR), voor self-service en team-scoping. */
    public function medewerker(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Medewerker::class);
    }

    // --- Module HR / Personeelszaken ---

    public function magHrBeheer(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magHrBeheer());
    }

    public function magHrInzien(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magHrInzien());
    }

    public function isHrTeamBeperkt(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->isHrTeamBeperkt());
    }

    public function magVerlofBeoordelen(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magVerlofBeoordelen());
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
        if (! $this->heeftRol(Rol::Cursusadministratie)) {
            return false;
        }

        // Financiën, Beheer en Bestuur zien alle cursussen; die verruimen de scope.
        return ! $this->magVolgensRol(
            fn (Rol $r) => in_array($r, [Rol::Financien, Rol::Beheerder, Rol::Bestuur], true)
        );
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

    /** Heeft de gebruiker deze rol (primair óf als extra rol)? */
    public function heeftRol(Rol $rol): bool
    {
        return $this->alleRollen()->contains(fn (Rol $r) => $r === $rol);
    }

    /** Vergelijk op rolsleutel (handig in Blade): $user->rolIs('docent'). */
    public function rolIs(string ...$rollen): bool
    {
        return count(array_intersect($this->rolSleutels(), $rollen)) > 0;
    }

    /** Mag de gebruiker de opgegeven module benaderen (unie over alle rollen)? */
    public function magModule(string $sleutel): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magModule($sleutel));
    }

    // Doorverwijzingen naar de rol-regels, zodat views en policies hetzelfde
    // vocabulaire delen als de UI (design system).
    public function magCijfersInzien(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magCijfersInzien());
    }

    public function magCijfersInvoeren(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magCijfersInvoeren());
    }

    public function magInschrijvingBeheren(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magInschrijvingBeheren());
    }

    public function magBsnInzien(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magBsnInzien());
    }

    public function magPresentieRegistreren(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magPresentieRegistreren());
    }

    public function magPresentieInzien(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magPresentieInzien());
    }

    public function magAanwezigheidsregelingZien(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magAanwezigheidsregelingZien());
    }

    public function magAanwezigheidsregelingBeheren(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magAanwezigheidsregelingBeheren());
    }

    public function magTakenBeheren(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magTakenBeheren());
    }

    public function magCursusBeheer(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magCursusBeheer());
    }

    public function magCursusFinancien(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magCursusFinancien());
    }

    public function magCursusInzien(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magCursusInzien());
    }

    // --- Module Relatiebeheer & Stagebeheer ---

    public function magRelatiebeheer(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magRelatiebeheer());
    }

    public function magStagebeheer(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magStagebeheer());
    }

    public function magRelatieInzien(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magRelatieInzien());
    }

    /**
     * Is de zichtbaarheid binnen Relatiebeheer beperkt tot de eigen opleiding(en)?
     * De koppeling loopt via dezelfde opleiding-toewijzing als de Directie
     * (`opleidingen()` → directie_opleidingen).
     */
    public function isRelatieBeperkt(): bool
    {
        $heeftBeperkteRol = $this->magVolgensRol(fn (Rol $r) => $r->isRelatieBeperkt());
        if (! $heeftBeperkteRol) {
            return false;
        }

        // Bestuur en Beheer zien alle relaties; die verruimen de scope.
        return ! $this->magVolgensRol(
            fn (Rol $r) => in_array($r, [Rol::Bestuur, Rol::Beheerder], true)
        );
    }

    public function magFinancieelInzien(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magFinancieelInzien());
    }

    public function magCollegegeldBeheren(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magCollegegeldBeheren());
    }

    public function magBetalingenRegistreren(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magBetalingenRegistreren());
    }

    public function magAlleOndertekendeDocumentenZien(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magAlleOndertekendeDocumentenZien());
    }
}
