<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een begeleidingsgesprek binnen een scriptietraject (stap 6). De begeleider en de
 * student leggen per gesprek de afspraken, feedback en actiepunten vast.
 */
class ScriptieGesprek extends Model
{
    protected $table = 'scriptie_gesprekken';

    /** De toegestane statussen (sleutel => label). */
    public const STATUSSEN = [
        'aangevraagd' => 'Aangevraagd',
        'gepland' => 'Gepland',
        'bevestigd' => 'Bevestigd',
        'verplaatst' => 'Verplaatst',
        'geannuleerd' => 'Geannuleerd',
        'uitgevoerd' => 'Uitgevoerd',
        'verslag_goedgekeurd' => 'Verslag goedgekeurd',
    ];

    protected $fillable = [
        'scriptie_id', 'datum', 'begintijd', 'eindtijd', 'locatie', 'online',
        'onderwerp', 'besproken', 'feedback', 'afspraken',
        'actiepunten_student', 'actiepunten_begeleider', 'actiepunten_deadline',
        'status', 'bevestigd_student', 'bevestigd_begeleider', 'geregistreerd_door_id',
    ];

    protected function casts(): array
    {
        return [
            'datum' => 'date',
            'online' => 'boolean',
            'actiepunten_deadline' => 'date',
            'bevestigd_student' => 'boolean',
            'bevestigd_begeleider' => 'boolean',
        ];
    }

    public function scriptie(): BelongsTo
    {
        return $this->belongsTo(Scriptie::class);
    }

    public function geregistreerdDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'geregistreerd_door_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSSEN[$this->status] ?? $this->status;
    }

    public function badge(): string
    {
        return match ($this->status) {
            'uitgevoerd', 'verslag_goedgekeurd', 'bevestigd' => 's-approved',
            'geannuleerd' => 's-rejected',
            'verplaatst' => 's-docs',
            default => 's-submitted',
        };
    }
}
