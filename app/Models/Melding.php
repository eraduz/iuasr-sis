<?php

namespace App\Models;

use App\Enums\Meldingniveau;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Een systeemmelding in de balk bovenaan elke pagina.
 *
 * @property Meldingniveau $niveau
 * @property Carbon $van
 * @property Carbon $tot
 * @property array<int, string>|null $rollen
 */
class Melding extends Model
{
    protected $table = 'meldingen';

    protected $fillable = [
        'niveau',
        'titel',
        'tekst',
        'van',
        'tot',
        'rollen',
        'afsluitbaar',
    ];

    protected function casts(): array
    {
        return [
            'niveau' => Meldingniveau::class,
            'van' => 'datetime',
            'tot' => 'datetime',
            'rollen' => 'array',
            'afsluitbaar' => 'boolean',
        ];
    }

    public function aangemaaktDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aangemaakt_door_id');
    }

    /**
     * De meldingen die NU lopen. Geen status-kolom: het venster van/tot is de
     * waarheid, dus een melding kan nooit blijven hangen doordat een geplande
     * taak niet draaide.
     */
    public function scopeLopend(Builder $query, ?Carbon $moment = null): Builder
    {
        $moment ??= now();

        return $query->where('van', '<=', $moment)->where('tot', '>', $moment);
    }

    /**
     * Alleen meldingen die voor deze gebruiker bedoeld zijn: zonder rollen is de
     * melding voor iedereen, anders moet één van zijn rollen erin voorkomen
     * (multi-rol, dus de unie — net als de rest van de rechten).
     *
     * @param  array<int, string>  $rolSleutels
     */
    public function scopeVoorRollen(Builder $query, array $rolSleutels): Builder
    {
        return $query->where(function (Builder $q) use ($rolSleutels) {
            $q->whereNull('rollen')->orWhereJsonLength('rollen', 0);

            foreach ($rolSleutels as $sleutel) {
                $q->orWhereJsonContains('rollen', $sleutel);
            }
        });
    }

    public function isLopend(?Carbon $moment = null): bool
    {
        $moment ??= now();

        return $this->van <= $moment && $this->tot > $moment;
    }

    public function isVerlopen(?Carbon $moment = null): bool
    {
        return $this->tot <= ($moment ?? now());
    }

    public function isGepland(?Carbon $moment = null): bool
    {
        return $this->van > ($moment ?? now());
    }

    /** Voor het overzicht: waar staat deze melding nu in zijn leven? */
    public function status(): string
    {
        return match (true) {
            $this->isGepland() => 'Gepland',
            $this->isLopend() => 'Loopt nu',
            default => 'Verlopen',
        };
    }

    public function voorIedereen(): bool
    {
        return empty($this->rollen);
    }

    /**
     * Sleutel waarmee de browser onthoudt dat iemand deze melding heeft
     * weggeklikt. `updated_at` zit erin, zodat een GEWIJZIGDE melding opnieuw
     * verschijnt — anders zou een correctie ("toch pas 20:00") ongezien blijven
     * bij precies de mensen die de eerste versie al hadden weggeklikt.
     */
    public function sluitSleutel(): string
    {
        return 'sis-melding-'.$this->id.'-'.$this->updated_at?->timestamp;
    }
}
