<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een van de student ontvangen document (op de private schijf opgeslagen).
 */
class StudentDocument extends Model
{
    protected $table = 'student_documenten';

    protected $fillable = [
        'student_id', 'soort', 'bestandsnaam', 'pad', 'mime', 'grootte', 'geupload_door_id',
    ];

    /** De vaste documentsoorten uit het aanmeldportaal (+ overig). */
    public const SOORTEN = [
        'id_voor' => 'Identiteitsbewijs (voorzijde)',
        'id_achter' => 'Identiteitsbewijs (achterzijde)',
        'diploma' => 'Diploma',
        'cijferlijst' => 'Cijferlijst',
        'pasfoto' => 'Pasfoto',
        'overig' => 'Overig document',
    ];

    public function soortLabel(): string
    {
        return self::SOORTEN[$this->soort] ?? ucfirst((string) $this->soort);
    }

    /** Toont het bestand inline (afbeelding/pdf) in plaats van downloaden. */
    public function isAfbeelding(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function geuploadDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'geupload_door_id');
    }
}
