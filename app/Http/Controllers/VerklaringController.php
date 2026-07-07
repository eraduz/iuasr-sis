<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Genereert officiële studentverklaringen op IUASR-briefpapier (A4). Bevat
 * NOOIT cijfers of BSN. Uitgifte wordt gelogd. Alleen Studentenzaken/Beheerder.
 */
class VerklaringController extends Controller
{
    private const TYPES = ['studentbewijs', 'vertraging', 'afstudeerfase'];

    public function index(Request $request): View
    {
        $type = in_array($request->query('type'), self::TYPES, true) ? $request->query('type') : 'studentbewijs';
        $zoek = trim((string) $request->query('q', ''));

        /** @var Student|null $student */
        $student = $request->filled('student')
            ? Student::with(['inschrijvingen.opleiding', 'inschrijvingen.periode'])->find($request->query('student'))
            : null;

        // Zoekresultaten — op studentnummer (prefix) of naam. Beperkt tot 20.
        $resultaten = collect();
        if ($zoek !== '' && ! $student) {
            $resultaten = Student::query()
                ->where(function ($q) use ($zoek) {
                    $q->where('studentnummer', 'like', $zoek.'%')
                        ->orWhere('achternaam', 'like', '%'.$zoek.'%')
                        ->orWhere('voornaam', 'like', '%'.$zoek.'%');
                })
                ->orderBy('studentnummer')
                ->limit(20)
                ->get(['id', 'studentnummer', 'voornaam', 'tussenvoegsel', 'achternaam']);
        }

        $verklaring = null;
        $financieel = null;
        if ($student) {
            $financieel = \App\Support\Collegegeldstatus::voor($student);
            // Blokkade: geen officiële documenten bij een betalingsachterstand.
            if (! $financieel['achterstand']) {
                $verklaring = $this->bouw($student, $type);
                AuditLogger::log(AuditLogger::UITGIFTE, $student, veld: 'verklaring', context: ['type' => $type]);
            }
        }

        return view('verklaringen.index', compact('student', 'type', 'verklaring', 'zoek', 'resultaten', 'financieel'));
    }

    /** Bouwt de tekstblokken voor het gekozen verklaringstype. */
    private function bouw(Student $student, string $type): array
    {
        $insch = $student->inschrijvingen->sortByDesc('inschrijfdatum')->first();
        $opleiding = $insch?->opleiding?->naam ?? 'de opleiding';
        $studiejaar = $insch?->periode?->naam ?? 'het huidige studiejaar';
        $jaar = now()->format('Y');
        $codes = ['studentbewijs' => 'SB', 'vertraging' => 'SV', 'afstudeerfase' => 'AF'];

        $teksten = [
            'studentbewijs' => [
                'title' => 'Studentbewijs',
                'sub' => 'Bewijs van inschrijving · '.$studiejaar,
                'body' => 'staat ingeschreven als student aan '.$opleiding.' voor '.$studiejaar.'.',
                'body2' => 'Deze verklaring is op verzoek van de student afgegeven en bevat geen resultaatgegevens.',
            ],
            'vertraging' => [
                'title' => 'Verklaring studievertraging',
                'sub' => 'Ten behoeve van DUO / gemeente',
                'body' => 'studievertraging heeft opgelopen door erkende persoonlijke omstandigheden binnen '.$opleiding.'.',
                'body2' => 'Deze verklaring dient ter ondersteuning van een aanvraag bij DUO of de gemeente en bevat geen resultaatgegevens.',
            ],
            'afstudeerfase' => [
                'title' => 'Verklaring afstudeerfase',
                'sub' => 'Bevestiging afstudeerfase',
                'body' => 'zich bevindt in de afstudeerfase van '.$opleiding.'.',
                'body2' => 'Deze verklaring is op verzoek van de student afgegeven en bevat geen resultaatgegevens.',
            ],
        ];

        $data = $teksten[$type];
        $data['ref'] = 'VERKL-'.$jaar.'-'.$student->studentnummer.'-'.$codes[$type];
        $data['opleiding'] = $opleiding;

        return $data;
    }
}
