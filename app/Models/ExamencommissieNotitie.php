<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een notitie van de examencommissie bij een student. Eigen werkaantekeningen van
 * de commissie; uitsluitend voor de examencommissie zichtbaar. Geen BSN/cijfers.
 */
class ExamencommissieNotitie extends Model
{
    protected $table = 'examencommissie_notities';

    protected $fillable = ['student_id', 'gebruiker_id', 'tekst'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function gebruiker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gebruiker_id');
    }
}
