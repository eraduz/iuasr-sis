<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Support\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Verwijdert studenten op studentnummer (los of als reeks, bijv. 261015-261026),
 * inclusief hun gekoppelde gegevens. Bedoeld voor het opschonen van synthetische
 * testdata; elke verwijdering wordt gelogd in het audit-logboek.
 *
 * Standaard toont het commando alleen een voorbeeld (dry-run). Voeg --force toe
 * om daadwerkelijk te verwijderen.
 *
 * Alle gekoppelde gegevens verdwijnen via de database-constraints: inschrijvingen,
 * betalingen, resultaten, presenties, vaktoewijzingen, notities, documenten,
 * vrijstellingsbesluiten, betalingsafspraken en kennistoetsresultaten staan op
 * ON DELETE CASCADE; aan een student gekoppelde TAKEN en ondertekende documenten
 * staan op SET NULL (die blijven bestaan, alleen de koppeling vervalt).
 */
class VerwijderStudenten extends Command
{
    protected $signature = 'sis:studenten-verwijderen {nummers* : Studentnummers of reeksen, bijv. 261015-261026 of 261015 261016} {--force : Daadwerkelijk verwijderen (anders alleen voorbeeld)}';

    protected $description = 'Verwijdert studenten (op studentnummer) en hun gekoppelde gegevens. Voor het opschonen van synthetische testdata.';

    public function handle(): int
    {
        $nummers = $this->parseNummers($this->argument('nummers'));
        if ($nummers === []) {
            $this->error('Geen geldige studentnummers opgegeven.');

            return self::FAILURE;
        }

        $studenten = Student::whereIn('studentnummer', $nummers)->orderBy('studentnummer')->get();
        $gevonden = $studenten->pluck('studentnummer')->all();
        $ontbreekt = array_values(array_diff($nummers, $gevonden));

        $this->info(count($gevonden).' van '.count($nummers).' studentnummers gevonden.');
        if ($ontbreekt !== []) {
            $this->warn('Niet gevonden (worden overgeslagen): '.implode(', ', $ontbreekt));
        }
        if ($studenten->isEmpty()) {
            return self::SUCCESS;
        }

        $rijen = $studenten->map(function (Student $s) {
            $insIds = DB::table('inschrijvingen')->where('student_id', $s->id)->pluck('id');

            return [
                $s->studentnummer,
                trim($s->voornaam.' '.$s->achternaam),
                $insIds->count(),
                DB::table('presenties')->whereIn('inschrijving_id', $insIds)->count(),
                DB::table('betalingen')->where('student_id', $s->id)->count(),
            ];
        })->all();
        $this->table(['Studentnr.', 'Naam', 'Inschr.', 'Presenties', 'Betalingen'], $rijen);

        if (! $this->option('force')) {
            $this->warn('Dit is een VOORBEELD (dry-run). Voeg --force toe om deze '.$studenten->count().' student(en) definitief te verwijderen.');

            return self::SUCCESS;
        }

        $aantal = 0;
        DB::transaction(function () use ($studenten, &$aantal) {
            foreach ($studenten as $student) {
                $this->verwijderStudent($student);
                $aantal++;
            }
        });

        $this->info($aantal.' student(en) definitief verwijderd en gelogd.');

        return self::SUCCESS;
    }

    private function verwijderStudent(Student $student): void
    {
        // Log vóór het verwijderen, zodat het spoor de gegevens nog bevat.
        AuditLogger::log(AuditLogger::VERWIJDERING, $student, veld: 'student', context: [
            'studentnummer' => $student->studentnummer,
            'naam' => trim($student->voornaam.' '.$student->achternaam),
            'reden' => 'Opschonen synthetische testdata',
        ]);

        // De database ruimt alle gekoppelde gegevens op via ON DELETE CASCADE /
        // SET NULL (zie de klasse-toelichting).
        $student->delete();
    }

    /**
     * Parseert losse nummers en reeksen ("261015-261026") naar een lijst
     * studentnummers.
     *
     * @param  array<int,string>  $argumenten
     * @return array<int,string>
     */
    private function parseNummers(array $argumenten): array
    {
        $nummers = [];
        foreach ($argumenten as $arg) {
            foreach (preg_split('/[,\s]+/', trim((string) $arg)) as $deel) {
                $deel = trim($deel);
                if ($deel === '') {
                    continue;
                }
                if (preg_match('/^(\d+)\s*(?:-|t\/m|tot en met|tm)\s*(\d+)$/i', $deel, $m)) {
                    $van = (int) $m[1];
                    $tot = (int) $m[2];
                    if ($tot >= $van && ($tot - $van) <= 10000) {
                        for ($n = $van; $n <= $tot; $n++) {
                            $nummers[] = (string) $n;
                        }
                    }
                } elseif (preg_match('/^\d+$/', $deel)) {
                    $nummers[] = $deel;
                }
            }
        }

        return array_values(array_unique($nummers));
    }
}
