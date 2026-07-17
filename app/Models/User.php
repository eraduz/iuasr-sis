<?php

namespace App\Models;

use App\Enums\Rol;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

/**
 * Medewerker-account. Authenticatie verloopt via Microsoft Entra ID (SSO/OIDC).
 * Het systeem beheert zelf GEEN wachtwoorden, met ÉÉN uitzondering: de maximaal
 * twee noodaccounts (break-glass, `noodaccount_slot` 1 of 2) mogen met
 * gebruikersnaam+wachtwoord inloggen als Entra ID onbereikbaar is. De rol volgt
 * bij voorkeur uit de Entra-groep en wordt bij elke actie server-side gecontroleerd.
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
        // LET OP: 'password' en 'noodaccount_slot' staan hier BEWUST niet.
        // Beide worden uitsluitend via forceFill() gezet, zodat een onbedoelde
        // mass-assignment nooit een noodaccount kan aanmaken of een wachtwoord
        // kan zetten. Zie NoodaccountController.
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'rol' => Rol::class,
            'actief' => 'boolean',
            'password' => 'hashed',
            'laatst_ingelogd_op' => 'datetime',
            'wachtwoord_gewijzigd_op' => 'datetime',
        ];
    }

    // --- Toegang ---

    /** Alleen actieve accounts. Een ingetrokken account mag nergens meer in. */
    public function scopeActief(Builder $query): Builder
    {
        return $query->where('actief', true);
    }

    /** De maximaal twee noodaccounts (break-glass). */
    public function scopeNoodaccount(Builder $query): Builder
    {
        return $query->whereNotNull('noodaccount_slot');
    }

    /** Is dit een noodaccount (break-glass) met wachtwoordtoegang? */
    public function isNoodaccount(): bool
    {
        return $this->noodaccount_slot !== null;
    }

    /**
     * Mag dit account daadwerkelijk met wachtwoord inloggen? Drie eisen tegelijk:
     * het is een noodaccount, het is actief, en het heeft de rol Beheerder. De
     * controle staat hier zodat het login-pad, het beheerscherm en het
     * artisan-commando dezelfde definitie delen.
     */
    public function magNoodloginGebruiken(): bool
    {
        return $this->isNoodaccount() && $this->actief && $this->heeftRol(Rol::Beheerder);
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

    /** Module Bibliotheek: catalogus muteren, uitlenen en innemen (Bibliotheek, Beheer). */
    public function magBibliotheekBeheren(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magBibliotheekBeheren());
    }

    /** Module Bibliotheek: catalogus, dashboard en rapportage inzien (+ Schoolbestuur). */
    public function magBibliotheekInzien(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magBibliotheekInzien());
    }

    /** Te-late uitleningen van studenten zien (Studentenzaken-dashboard, opdracht §9). */
    public function magBibliotheekSignaalZien(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magBibliotheekSignaalZien());
    }

    /** E-mailsjablonen van de bibliotheek beheren (Beheerder). */
    public function magBibliotheekSjablonenBeheren(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magBibliotheekSjablonenBeheren());
    }

    /** Noodaccounts (break-glass) beheren: aanwijzen, wachtwoord zetten, intrekken (Beheerder). */
    public function magNoodaccountsBeheren(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magNoodaccountsBeheren());
    }

    /** Module Balie/Receptie: registraties aanmaken/wijzigen (Balie, Beheer). */
    public function magBalieBeheren(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magBalieBeheren());
    }

    /** Module Balie/Receptie: logboek inzien (Balie, Beheer, Schoolbestuur). */
    public function magBalieInzien(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magBalieInzien());
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

    public function magVervroegdAfstuderenVrijgeven(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magVervroegdAfstuderenVrijgeven());
    }

    public function magExamencommissieNotities(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magExamencommissieNotities());
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

    // --- Module Scriptie Coördinatie ---

    /** Scriptietrajecten regisseren: starten, kern beheren, kandidaten inschrijven. */
    public function magScriptieBeheren(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magScriptieBeheren());
    }

    /** De module Scriptie Coördinatie inzien (dashboard, kandidaten, trajecten). */
    public function magScriptieInzien(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magScriptieInzien());
    }

    /**
     * Is de zichtbaarheid binnen Scriptie Coördinatie opleidinggebonden
     * (coördinator/Directie)? Bestuur, Examencommissie en Beheer verruimen de scope
     * naar alle trajecten. De docent-begeleider is niet opleidinggebonden maar
     * begeleider-gebonden — die scoping zit in Scriptie::scopeZichtbaarVoor.
     */
    public function isScriptieBeperkt(): bool
    {
        $heeftBeperkteRol = $this->magVolgensRol(fn (Rol $r) => $r->isScriptieBeperkt());
        if (! $heeftBeperkteRol) {
            return false;
        }

        return ! $this->magVolgensRol(
            fn (Rol $r) => in_array($r, [Rol::Bestuur, Rol::Examencommissie, Rol::Beheerder], true)
        );
    }

    /**
     * Is deze gebruiker uitsluitend scriptiebegeleider (docent zonder bredere
     * scriptie-inzage)? Dan ziet hij alleen de trajecten waarvan hij begeleider is.
     */
    public function isScriptieBegeleider(): bool
    {
        if (! $this->heeftRol(Rol::Docent)) {
            return false;
        }

        return ! $this->magVolgensRol(fn (Rol $r) => in_array($r, [
            Rol::Scriptiecoordinator, Rol::Examencommissie, Rol::Directie,
            Rol::Bestuur, Rol::Beheerder,
        ], true));
    }

    // --- Module Stichtingsbestuur ---

    /** Bestuursleden en vergaderingen muteren (Stichtingsbestuur, Beheer). */
    public function magStichtingsbestuurBeheren(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magStichtingsbestuurBeheren());
    }

    /** De module Stichtingsbestuur inzien. */
    public function magStichtingsbestuurInzien(): bool
    {
        return $this->magVolgensRol(fn (Rol $r) => $r->magStichtingsbestuurInzien());
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
