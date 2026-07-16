<?php

namespace App\Support;

use App\Enums\Rol;
use App\Enums\Scriptiestap;
use App\Models\Inschrijving;
use App\Models\Scriptie;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Start een scriptietraject voor een inschrijving: maakt het hoofdrecord aan,
 * legt de toelatingscontrole als momentopname vast en seedt de elf stapstanden
 * (met beginstatus) plus de checklistpunten uit de stap-sjablonen. Alles in één
 * transactie, zodat een half aangemaakt traject niet kan blijven staan.
 */
class Scriptietraject
{
    public static function start(Inschrijving $inschrijving, User $gebruiker): Scriptie
    {
        $inschrijving->loadMissing(['student', 'opleiding']);
        $student = $inschrijving->student;
        $toelating = Scriptietoelating::voor($student, $inschrijving);

        return DB::transaction(function () use ($inschrijving, $student, $gebruiker, $toelating) {
            $scriptie = Scriptie::create([
                'scriptienummer' => Scriptienummer::genereer(),
                'student_id' => $student->id,
                'inschrijving_id' => $inschrijving->id,
                'opleiding_id' => $inschrijving->opleiding_id,
                'coordinator_id' => $gebruiker->heeftRol(Rol::Scriptiecoordinator) ? $gebruiker->id : null,
                'status' => Scriptie::LOPEND,
                'gestart_door_id' => $gebruiker->id,
                'gestart_op' => now(),
                'toelating_ec' => $toelating['ec'],
                'toelating_mt1_behaald' => $toelating['mt1'],
                'toelating_mt2_behaald' => $toelating['mt2'],
            ]);

            foreach (Scriptiestap::inVolgorde() as $stap) {
                // Stap 1 (toelating) krijgt meteen de uitkomst van de controle.
                $status = $stap === Scriptiestap::Toelating
                    ? ($toelating['voldoet'] ? 'behaald' : $stap->standaardStatus())
                    : $stap->standaardStatus();

                $scriptie->stapstanden()->create([
                    'stap' => $stap->value,
                    'volgorde' => $stap->volgorde(),
                    'status' => $status,
                    'gereed' => false,
                ]);

                $volgnr = 0;
                foreach ($stap->checklistpunten() as $sleutel => $label) {
                    $scriptie->checklistpunten()->create([
                        'stap' => $stap->value,
                        'sleutel' => $sleutel,
                        'label' => $label,
                        'volgorde' => $volgnr++,
                        'waarde' => null,
                    ]);
                }
            }

            return $scriptie;
        });
    }
}
