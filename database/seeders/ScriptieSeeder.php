<?php

namespace Database\Seeders;

use App\Enums\InschrijvingStatus;
use App\Models\Inschrijving;
use App\Models\Opleiding;
use App\Models\Scriptie;
use App\Models\User;
use App\Support\Scriptietoelating;
use App\Support\Scriptietraject;
use Illuminate\Database\Seeder;

/**
 * Module Scriptie Coördinatie — synthetische seed. Koppelt het coördinator-account
 * (uit de migratie) aan de opleidingen met een scriptie, zodat de opleidinggebonden
 * scoping werkt, en start één voorbeeldtraject. Idempotent en AVG-veilig.
 */
class ScriptieSeeder extends Seeder
{
    public function run(): void
    {
        $coordinator = User::where('email', 'scriptie@iuasr.nl')->first();
        if ($coordinator === null) {
            return;
        }

        // Coördinator koppelen aan de opleidingen met een scriptie (ISLTH, MGV).
        $oplIds = Opleiding::whereIn('code', Scriptietoelating::ondersteundeOpleidingcodes())->pluck('id');
        $coordinator->opleidingen()->syncWithoutDetaching($oplIds);

        // Eén voorbeeldtraject, alleen als er nog geen enkel traject bestaat.
        if (Scriptie::query()->exists()) {
            return;
        }

        $inschrijving = Inschrijving::where('status', InschrijvingStatus::Actief->value)
            ->whereIn('opleiding_id', $oplIds)
            ->whereDoesntHave('scriptie')
            ->with(['student', 'opleiding'])
            ->first();

        if ($inschrijving === null || $inschrijving->student === null) {
            return;
        }

        $scriptie = Scriptietraject::start($inschrijving, $coordinator);
        $scriptie->update([
            'titel_voorlopig' => 'De rol van waqf in hedendaags islamitisch financieel beheer',
            'taal' => 'Nederlands',
            'voorstel_omschrijving' => 'Een verkennend onderzoek naar moderne toepassingen van het waqf-instituut binnen de Nederlandse context.',
        ]);
    }
}
