<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Behaald-registratie van een landelijke kennistoets door een student. */
class Kennistoetsresultaat extends Model
{
    protected $table = 'kennistoets_resultaten';

    protected $fillable = ['student_id', 'kennistoets_id', 'behaald_op', 'geregistreerd_door_id'];

    protected function casts(): array
    {
        return ['behaald_op' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function kennistoets(): BelongsTo
    {
        return $this->belongsTo(Kennistoets::class);
    }
}
