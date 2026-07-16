<?php

namespace App\Models;

use App\Enums\Scriptiestap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Een scriptietraject (module Scriptie Coördinatie). Eén traject per inschrijving.
 * Het hoofdrecord draagt alle 1:1-velden van de elf stappen; de stand per stap
 * staat in {@see ScriptieStapstand}, de checklists in {@see ScriptieChecklistpunt},
 * de begeleidingsgesprekken in {@see ScriptieGesprek} en de documenten in
 * {@see ScriptieDocument}. Het traject is 'afgerond' zodra de laatste stap
 * (Afronding) is afgevinkt.
 */
class Scriptie extends Model
{
    protected $table = 'scripties';

    public const LOPEND = 'lopend';
    public const AFGEROND = 'afgerond';
    public const AFGEBROKEN = 'afgebroken';

    /** Wijde 1:1-tabel; de controllers zetten uitsluitend gevalideerde whitelists. */
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'toelating_ec' => 'decimal:1',
            'toelating_mt1_behaald' => 'boolean',
            'toelating_mt2_behaald' => 'boolean',
            'voorstel_contact_begeleider' => 'boolean',
            'onderwerp_beoordeeld_op' => 'date',
            'onderwerp_herindiening_uiterlijk' => 'date',
            'begeleider_toegewezen_op' => 'date',
            'begeleiding_eerste_gesprek' => 'date',
            'overeenkomst_deadline_pva' => 'date',
            'overeenkomst_startdatum' => 'date',
            'overeenkomst_einddatum' => 'date',
            'goedkeuring_student' => 'boolean',
            'goedkeuring_student_op' => 'date',
            'goedkeuring_begeleider' => 'boolean',
            'goedkeuring_begeleider_op' => 'date',
            'goedkeuring_coordinator' => 'boolean',
            'goedkeuring_coordinator_op' => 'date',
            'goedkeuring_directeur' => 'boolean',
            'goedkeuring_directeur_op' => 'date',
            'definitief_ingeleverd_op' => 'date',
            'plagiaat_datum' => 'date',
            'plagiaat_similariteit' => 'decimal:2',
            'plagiaat_rapport_beschikbaar' => 'boolean',
            'beoordeling_datum' => 'date',
            'voorlopig_cijfer' => 'decimal:1',
            'definitief_cijfer' => 'decimal:1',
            'kalibratie_afgerond' => 'boolean',
            'verdediging_datum' => 'date',
            'gearchiveerd_op' => 'date',
            'gestart_op' => 'datetime',
            'afgerond_op' => 'datetime',
        ];
    }

    // --- Relaties ---

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function inschrijving(): BelongsTo
    {
        return $this->belongsTo(Inschrijving::class);
    }

    public function opleiding(): BelongsTo
    {
        return $this->belongsTo(Opleiding::class);
    }

    public function begeleider(): BelongsTo
    {
        return $this->belongsTo(Docent::class, 'begeleider_id');
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function gestartDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gestart_door_id');
    }

    public function overeenkomstDocument(): BelongsTo
    {
        return $this->belongsTo(OndertekendDocument::class, 'overeenkomst_document_id');
    }

    public function stapstanden(): HasMany
    {
        return $this->hasMany(ScriptieStapstand::class)->orderBy('volgorde');
    }

    public function checklistpunten(): HasMany
    {
        return $this->hasMany(ScriptieChecklistpunt::class)->orderBy('volgorde');
    }

    public function gesprekken(): HasMany
    {
        return $this->hasMany(ScriptieGesprek::class)->orderByDesc('datum')->orderByDesc('id');
    }

    public function documenten(): HasMany
    {
        return $this->hasMany(ScriptieDocument::class)->orderByDesc('id');
    }

    // --- Statushelpers ---

    public function isLopend(): bool
    {
        return $this->status === self::LOPEND;
    }

    public function isAfgerond(): bool
    {
        return $this->status === self::AFGEROND;
    }

    public function isAfgebroken(): bool
    {
        return $this->status === self::AFGEBROKEN;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::AFGEROND => 'Afgerond',
            self::AFGEBROKEN => 'Afgebroken',
            default => 'Lopend',
        };
    }

    public function titelWeergave(): string
    {
        return $this->titel_definitief ?: ($this->titel_voorlopig ?: '—');
    }

    // --- Stap-/voortganghelpers ---

    /** De stand-rij van een stap (uit de eager-geladen relatie, geen extra query). */
    public function stand(Scriptiestap $stap): ?ScriptieStapstand
    {
        return $this->stapstanden->firstWhere('stap', $stap);
    }

    /** Het aantal afgevinkte stappen. */
    public function aantalGereed(): int
    {
        return $this->stapstanden->where('gereed', true)->count();
    }

    /** Voortgang in procenten (0..100), afgerond op gehelen. */
    public function voortgang(): int
    {
        $totaal = max(1, $this->stapstanden->count());

        return (int) round($this->aantalGereed() / $totaal * 100);
    }

    /** De eerste nog niet afgevinkte stap (of null als alles gereed is). */
    public function huidigeStap(): ?Scriptiestap
    {
        return $this->stapstanden->firstWhere('gereed', false)?->stap;
    }

    /** De rol die nu aan zet is (verantwoordelijke van de huidige stap). */
    public function wachtOpRol(): ?\App\Enums\Rol
    {
        return $this->huidigeStap()?->verantwoordelijke();
    }

    /**
     * De checklistpunten van een stap (uit de eager-geladen relatie).
     *
     * @return Collection<int, ScriptieChecklistpunt>
     */
    public function checklistVoor(Scriptiestap $stap): Collection
    {
        return $this->checklistpunten->where('stap', $stap)->sortBy('volgorde')->values();
    }

    /**
     * De documenten van een categorie (uit de eager-geladen relatie).
     *
     * @return Collection<int, ScriptieDocument>
     */
    public function documentenVoor(string $categorie): Collection
    {
        return $this->documenten->where('categorie', $categorie)->values();
    }

    // --- Zichtbaarheid (rolscheiding) ---

    /**
     * Scope op zichtbaarheid: coördinator en Directie zien uitsluitend de trajecten
     * van hun eigen opleiding(en); een docent-begeleider alleen de trajecten waarvan
     * hij begeleider is; Examencommissie, Bestuur en Beheer zien alles.
     */
    public function scopeZichtbaarVoor(Builder $query, User $gebruiker): Builder
    {
        if ($gebruiker->isScriptieBeperkt()) {
            return $query->whereIn('opleiding_id', $gebruiker->opleidingIds());
        }

        if ($gebruiker->isScriptieBegeleider()) {
            return $query->where('begeleider_id', $gebruiker->docent_id);
        }

        return $query;
    }

    /** Mag deze gebruiker dit traject zien? */
    public function zichtbaarVoor(User $gebruiker): bool
    {
        if (! $gebruiker->magScriptieInzien()) {
            return false;
        }
        if ($gebruiker->isScriptieBeperkt()) {
            return $gebruiker->opleidingIds()->contains($this->opleiding_id);
        }
        if ($gebruiker->isScriptieBegeleider()) {
            return $this->begeleider_id !== null && $this->begeleider_id === $gebruiker->docent_id;
        }

        return true;
    }

    /**
     * Mag deze gebruiker het FORMULIER van deze stap invullen/wijzigen? Naast de
     * zichtbaarheid geldt strikte rolscheiding:
     *  - de Beheerder mag alles;
     *  - academische stappen (onderwerpbeoordeling, beoordeling, verdediging) alleen
     *    de Examencommissie (scriptiecommissie/examinator);
     *  - de begeleidingsstappen (plan van aanpak, inlevering) de Docent-begeleider
     *    of de coördinator;
     *  - de coördinerende stappen de scriptiecoördinator (magScriptieBeheren).
     * Een afgerond of afgebroken traject is niet meer te bewerken (behalve Beheer).
     */
    public function magStapBewerken(User $gebruiker, \App\Enums\Scriptiestap $stap): bool
    {
        if (! $this->zichtbaarVoor($gebruiker)) {
            return false;
        }
        if ($gebruiker->heeftRol(\App\Enums\Rol::Beheerder)) {
            return true;
        }
        if (! $this->isLopend()) {
            return false;
        }

        $academisch = [
            \App\Enums\Scriptiestap::Onderwerpbeoordeling,
            \App\Enums\Scriptiestap::Beoordeling,
            \App\Enums\Scriptiestap::Verdediging,
        ];
        if (in_array($stap, $academisch, true)) {
            return $gebruiker->heeftRol(\App\Enums\Rol::Examencommissie);
        }

        $begeleiding = [
            \App\Enums\Scriptiestap::PlanVanAanpak,
            \App\Enums\Scriptiestap::Inlevering,
        ];
        if (in_array($stap, $begeleiding, true)) {
            return $gebruiker->heeftRol(\App\Enums\Rol::Docent) || $gebruiker->magScriptieBeheren();
        }

        return $gebruiker->magScriptieBeheren();
    }
}
