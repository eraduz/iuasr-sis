<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** HR-document bij een medewerker (private schijf, gelogd). */
class HrDocument extends Model
{
    protected $table = 'hr_documenten';

    protected $fillable = [
        'medewerker_id', 'categorie', 'titel', 'bestandsnaam', 'pad', 'mime', 'grootte', 'geupload_door_id',
    ];

    protected function casts(): array
    {
        return ['grootte' => 'integer'];
    }

    public const CATEGORIEEN = [
        'contract' => 'Arbeidscontract',
        'vrijwilligersovereenkomst' => 'Vrijwilligersovereenkomst',
        'zzp_overeenkomst' => 'ZZP-/opdrachtovereenkomst',
        'diploma' => 'Diploma',
        'identiteitsbewijs' => 'Identiteitsbewijs',
        'correspondentie' => 'Correspondentie',
        'overig' => 'Overig',
    ];

    public function categorieLabel(): string
    {
        return self::CATEGORIEEN[$this->categorie] ?? ucfirst((string) $this->categorie);
    }

    public function medewerker(): BelongsTo
    {
        return $this->belongsTo(Medewerker::class);
    }

    public function geuploadDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'geupload_door_id');
    }
}
