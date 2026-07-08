<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een digitaal ondertekend PDF-document met verificatiecode en echtheidskenmerk.
 */
class OndertekendDocument extends Model
{
    protected $table = 'ondertekende_documenten';

    protected $fillable = [
        'code', 'type', 'titel', 'student_id', 'ontvanger',
        'uitgegeven_door_id', 'sha256', 'bestandsnaam', 'pad',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function uitgegevenDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uitgegeven_door_id');
    }
}
