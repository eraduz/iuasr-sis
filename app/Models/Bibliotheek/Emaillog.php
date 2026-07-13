<?php

namespace App\Models\Bibliotheek;

use App\Enums\BibliotheekMailsoort;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Logboek van de verzonden bibliotheek-e-mails. Append-only: een verzonden mail
 * wordt nooit gewijzigd. Hierdoor is per uitlening en per lener zichtbaar wat er
 * is verstuurd, aan wie, met welke CC, en of het gelukt is.
 */
class Emaillog extends Model
{
    protected $table = 'bibliotheek_emaillogs';

    public $timestamps = false;

    protected $fillable = ['uitlening_id', 'soort', 'ontvanger', 'cc', 'gelukt', 'foutmelding', 'verzonden_op'];

    protected function casts(): array
    {
        return [
            'soort' => BibliotheekMailsoort::class,
            'gelukt' => 'boolean',
            'verzonden_op' => 'datetime',
        ];
    }

    public function uitlening(): BelongsTo
    {
        return $this->belongsTo(Uitlening::class, 'uitlening_id');
    }
}
