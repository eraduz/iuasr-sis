<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Een door de Financiële Administratie geregistreerde betaling. */
class Betaling extends Model
{
    protected $table = 'betalingen';

    protected $fillable = [
        'inschrijving_id',
        'student_id',
        'bedrag',
        'termijn',
        'datum',
        'betaalwijze',
        'opmerking',
        'geregistreerd_door_id',
    ];

    protected function casts(): array
    {
        return [
            'bedrag' => 'decimal:2',
            'termijn' => 'integer',
            'datum' => 'date',
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

    public function geregistreerdDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'geregistreerd_door_id');
    }
}
