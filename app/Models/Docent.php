<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Opzoektabel Docent (+ docentcode). Een docent kan aan meerdere vakken
 * gekoppeld zijn; de rol Docent ziet/voert alleen het EIGEN vak in.
 */
class Docent extends Model
{
    protected $table = 'docenten';

    protected $fillable = ['code', 'aanhef', 'voornaam', 'achternaam', 'email', 'actief'];

    protected function casts(): array
    {
        return ['actief' => 'boolean'];
    }

    public function vakken(): HasMany
    {
        return $this->hasMany(Vak::class);
    }

    public function volledigeNaam(): string
    {
        return trim(implode(' ', array_filter([$this->aanhef, $this->voornaam, $this->achternaam])));
    }
}
