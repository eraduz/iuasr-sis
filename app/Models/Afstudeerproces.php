<?php

namespace App\Models;

use App\Enums\Afstudeerstap;
use App\Enums\Rol;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Het afstudeerproces van één inschrijving (examencommissie-gedreven). Bevat de
 * vijf stappen; is 'afgerond' zodra de laatste stap (diploma uitgereikt) is
 * afgevinkt. De statuswijziging van de inschrijving naar 'afgestudeerd' gebeurt
 * op dat moment (zie AfstudeerprocesController).
 */
class Afstudeerproces extends Model
{
    protected $table = 'afstudeerprocessen';

    public const LOPEND = 'lopend';
    public const AFGEROND = 'afgerond';
    public const AFGEBROKEN = 'afgebroken';

    protected $fillable = [
        'inschrijving_id', 'student_id', 'gestart_door_id', 'gestart_op', 'status', 'afgerond_op',
    ];

    protected function casts(): array
    {
        return [
            'gestart_op' => 'datetime',
            'afgerond_op' => 'datetime',
        ];
    }

    public function inschrijving(): BelongsTo
    {
        return $this->belongsTo(Inschrijving::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function gestartDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gestart_door_id');
    }

    public function stappen(): HasMany
    {
        return $this->hasMany(Afstudeerprocesstap::class)->orderBy('volgorde');
    }

    public function isAfgerond(): bool
    {
        return $this->status === self::AFGEROND;
    }

    public function isLopend(): bool
    {
        return $this->status === self::LOPEND;
    }

    /** Aantal afgevinkte stappen. */
    public function aantalGereed(): int
    {
        return $this->stappen->where('gereed', true)->count();
    }

    /** Voortgang in procenten (0..100). */
    public function voortgang(): int
    {
        $totaal = $this->stappen->count() ?: 1;

        return (int) round($this->aantalGereed() / $totaal * 100);
    }

    /** De eerstvolgende nog niet afgevinkte stap (of null als alles af is). */
    public function huidigeStap(): ?Afstudeerstap
    {
        return $this->stappen->sortBy('volgorde')->firstWhere('gereed', false)?->stap;
    }

    /** Bij welke rol ligt de eerstvolgende openstaande stap? Stuurt de dashboardsignalering. */
    public function wachtOpRol(): ?Rol
    {
        return $this->huidigeStap()?->verantwoordelijke();
    }
}
