<?php

namespace App\Models;

use App\Enums\Rol;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een extra rol die aan een gebruiker is toegekend (multi-rol). De primaire rol
 * staat op `users.rol`; deze tabel houdt de aanvullende rollen. Zie
 * {@see \App\Models\User::alleRollen()} voor de samengevoegde rolset.
 *
 * @property Rol $rol
 */
class Roltoewijzing extends Model
{
    protected $table = 'roltoewijzingen';

    protected $fillable = ['user_id', 'rol'];

    protected function casts(): array
    {
        return [
            'rol' => Rol::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
