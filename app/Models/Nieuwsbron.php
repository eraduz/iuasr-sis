<?php

namespace App\Models;

use App\Enums\Nieuwsbrontype;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een (whitelisted) nieuwsbron voor het bestuursdashboard. Zie {@see Nieuwsbrontype}
 * voor hoe het nieuws wordt opgehaald.
 *
 * @property Nieuwsbrontype $type
 */
class Nieuwsbron extends Model
{
    protected $table = 'nieuwsbronnen';

    protected $fillable = [
        'naam', 'url', 'type', 'categorie',
        'item_xpath', 'titel_xpath', 'link_xpath', 'datum_xpath',
        'actief', 'volgorde', 'laatst_opgehaald_op', 'laatste_fout',
    ];

    protected function casts(): array
    {
        return [
            'type' => Nieuwsbrontype::class,
            'actief' => 'boolean',
            'volgorde' => 'integer',
            'laatst_opgehaald_op' => 'datetime',
        ];
    }

    public function berichten(): HasMany
    {
        return $this->hasMany(Nieuwsbericht::class)->orderByDesc('gepubliceerd_op');
    }

    /** De host van de bron-URL (voor de whitelist-controle). */
    public function host(): ?string
    {
        return $this->url ? parse_url($this->url, PHP_URL_HOST) : null;
    }
}
