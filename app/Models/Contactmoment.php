<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een contactmoment (interactie) met een organisatie: telefoon, e-mail, bezoek,
 * stagebezoek, overleg, klacht, evaluatie, enz. Historisch record — niet wissen.
 */
class Contactmoment extends Model
{
    protected $table = 'contactmomenten';

    protected $fillable = [
        'organisatie_id', 'contactpersoon_id', 'contactmoment_type_id',
        'medewerker_id', 'datum', 'tijd', 'onderwerp', 'samenvatting', 'vervolgdatum',
    ];

    protected function casts(): array
    {
        return [
            'datum' => 'date',
            'vervolgdatum' => 'date',
        ];
    }

    public function organisatie(): BelongsTo
    {
        return $this->belongsTo(Organisatie::class);
    }

    public function contactpersoon(): BelongsTo
    {
        return $this->belongsTo(Contactpersoon::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ContactmomentType::class, 'contactmoment_type_id');
    }

    public function medewerker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'medewerker_id');
    }
}
