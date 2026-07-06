<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/*
|--------------------------------------------------------------------------
| VersleuteldGevoeligVeld
|--------------------------------------------------------------------------
|
| Cast voor gevoelige velden (BSN, rekeningnummer). De waarde wordt met de
| applicatiesleutel versleuteld opgeslagen (Laravel Crypt / AES-256) en bij
| uitlezen ontsleuteld. Zo verlaat de leesbare waarde nooit de database in
| platte vorm. Inzage wordt elders (policy + observer) gelogd.
|
| Let op: versleutelde waarden zijn niet doorzoekbaar. Voor het opsporen van
| duplicaten wordt een aparte, niet-omkeerbare hash-kolom gebruikt.
*/
class VersleuteldGevoeligVeld implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            // Beschadigde of niet-versleutelde waarde nooit als platte tekst lekken.
            return null;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Crypt::encryptString((string) $value);
    }
}
