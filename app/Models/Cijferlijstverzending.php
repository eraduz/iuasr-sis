<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registreert dat de cijferlijst van een student voor een periode is gemaild
 * (of in de wachtrij staat / is mislukt). Uniek op (student, periode): dat is
 * de "al gemaild deze periode"-markering tegen dubbel versturen.
 */
class Cijferlijstverzending extends Model
{
    protected $table = 'cijferlijstverzendingen';

    protected $fillable = [
        'student_id', 'periode_id', 'opleiding_id', 'status',
        'ontvanger', 'foutmelding', 'verzonden_op', 'verzonden_door_id',
    ];

    protected function casts(): array
    {
        return ['verzonden_op' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function opleiding(): BelongsTo
    {
        return $this->belongsTo(Opleiding::class);
    }
}
