<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Interne notitie bij een student. Administratieve informatie van
 * Studentenzaken; bevat geen cijfers of BSN.
 */
class StudentNotitie extends Model
{
    protected $table = 'student_notities';

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
