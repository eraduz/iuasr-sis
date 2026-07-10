<?php

namespace App\Http\Controllers\Cursus;

use App\Enums\CursusinschrijvingStatus;
use App\Http\Controllers\Controller;
use App\Models\Cursist;
use App\Models\Cursus;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\CursistnummerGenerator;
use App\Support\Tabellezer;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cursisten: handmatig invoeren, bekijken, wijzigen en in bulk importeren
 * (Excel/CSV). Elke inschrijving op een cursus krijgt het cursusgeld als
 * momentopname mee. Belangrijke acties worden gelogd.
 */
class CursistController extends Controller
{
    public function index(Request $request): View
    {
        $zoek = trim((string) $request->query('q', ''));
        $gebruiker = $request->user();
        $beperkt = $gebruiker->isCursusBeperkt();
        $eigenCursusIds = $beperkt ? $gebruiker->cursusIds() : null;

        $cursisten = Cursist::query()
            ->zichtbaarVoor($gebruiker)
            ->withCount(['inschrijvingen' => fn ($q) => $beperkt ? $q->whereIn('cursus_id', $eigenCursusIds) : $q])
            ->when($zoek !== '', function ($q) use ($zoek) {
                $q->where(fn ($w) => $w->where('cursistnummer', 'like', $zoek.'%')
                    ->orWhere('achternaam', 'like', '%'.$zoek.'%')
                    ->orWhere('voornaam', 'like', '%'.$zoek.'%')
                    ->orWhere('email', 'like', '%'.$zoek.'%'));
            })
            ->orderBy('achternaam')->orderBy('voornaam')
            ->paginate(20)->withQueryString();

        return view('cursisten.index', compact('cursisten', 'zoek'));
    }

    public function create(Request $request): View
    {
        return view('cursisten.form', ['cursist' => new Cursist(['status' => 'actief']), 'cursussen' => $this->actieveCursussen($request->user())]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->valideer($request);

        $cursist = null;
        // De unieke index op cursistnummer vangt gelijktijdige inserts af; bij een
        // botsing wordt opnieuw genummerd.
        for ($poging = 0; $poging < 5 && $cursist === null; $poging++) {
            try {
                $cursist = Cursist::create($data + ['cursistnummer' => CursistnummerGenerator::genereer()]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                $cursist = null;
            }
        }
        abort_if($cursist === null, 500, 'Kon geen uniek cursistnummer bepalen.');

        AuditLogger::log(AuditLogger::AANMAAK, $cursist, veld: 'cursist', context: ['cursistnummer' => $cursist->cursistnummer]);

        // Optioneel meteen inschrijven op een cursus. Een cursusdirecteur mag
        // uitsluitend op de eigen cursus(sen) inschrijven.
        if ($request->filled('cursus_id')) {
            $cursus = Cursus::find((int) $request->input('cursus_id'));
            abort_unless($cursus && $cursus->zichtbaarVoor($request->user()), 403, 'Deze cursus valt buiten uw beheer.');
            $this->schrijfIn($cursist, $cursus->id, $request->user()->id);
        }

        return redirect()->route('cursisten.show', $cursist)->with('status', 'Cursist toegevoegd.');
    }

    public function show(Request $request, Cursist $cursist): View
    {
        $gebruiker = $request->user();
        abort_unless($cursist->zichtbaarVoor($gebruiker), 403, 'Deze cursist valt buiten uw cursus(sen).');

        // Een cursusdirecteur ziet op het dossier alleen de inschrijvingen op de
        // eigen cursus(sen), niet die bij andere cursussen.
        $beperkt = $gebruiker->isCursusBeperkt();
        $eigenCursusIds = $beperkt ? $gebruiker->cursusIds() : null;
        $cursist->load([
            'inschrijvingen' => fn ($q) => $beperkt ? $q->whereIn('cursus_id', $eigenCursusIds) : $q,
            'inschrijvingen.cursus', 'inschrijvingen.ingeschrevenDoor', 'inschrijvingen.betalingen',
        ]);

        return view('cursisten.show', [
            'cursist' => $cursist,
            'cursussen' => $this->actieveCursussen($gebruiker),
            'statussen' => CursusinschrijvingStatus::opties(),
        ]);
    }

    public function edit(Request $request, Cursist $cursist): View
    {
        abort_unless($cursist->zichtbaarVoor($request->user()), 403, 'Deze cursist valt buiten uw cursus(sen).');

        return view('cursisten.form', ['cursist' => $cursist, 'cursussen' => $this->actieveCursussen($request->user())]);
    }

    public function update(Request $request, Cursist $cursist): RedirectResponse
    {
        abort_unless($cursist->zichtbaarVoor($request->user()), 403, 'Deze cursist valt buiten uw cursus(sen).');

        $data = $this->valideer($request);
        $data['status'] = $request->input('status', $cursist->status);
        $cursist->update($data);

        AuditLogger::log(AuditLogger::WIJZIGING, $cursist, veld: 'cursist', context: ['cursistnummer' => $cursist->cursistnummer]);

        return redirect()->route('cursisten.show', $cursist)->with('status', 'Cursist bijgewerkt.');
    }

    /* ---------------------------------------------------------------- import */

    public function importSjabloon(): StreamedResponse
    {
        $rijen = [
            ['voornaam', 'tussenvoegsel', 'achternaam', 'geboortedatum', 'email', 'telefoon', 'adres', 'postcode', 'woonplaats', 'cursuscode'],
            ['Ahmet', '', 'Yılmaz', '15-03-1998', 'a.yilmaz@example.com', '06 12 34 56 78', 'Voorbeeldstraat 1', '3011AB', 'Rotterdam', 'ARAB-TAAL'],
        ];

        return response()->streamDownload(function () use ($rijen) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rijen as $r) {
                fputcsv($out, $r, ';');
            }
            fclose($out);
        }, 'cursisten-sjabloon.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function importControle(Request $request): View|RedirectResponse
    {
        $request->validate(['bestand' => ['required', 'file', 'max:5120']]);
        $bestand = $request->file('bestand');
        $ext = strtolower($bestand->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'csv', 'txt'], true)) {
            return back()->withErrors(['bestand' => 'Upload een Excel- (.xlsx) of CSV-bestand.']);
        }

        try {
            $rijen = Tabellezer::rijen($bestand->getRealPath(), $ext === 'txt' ? 'csv' : $ext);
        } catch (\Throwable $e) {
            return back()->withErrors(['bestand' => 'Het bestand kon niet worden gelezen. Controleer of het een geldig Excel- of CSV-bestand is.']);
        }
        if ($rijen === []) {
            return back()->withErrors(['bestand' => 'Het bestand bevat geen gegevens.']);
        }

        $cursusPerCode = $this->actieveCursussen($request->user())->keyBy(fn ($c) => strtolower($c->code));
        $geldig = [];
        $fouten = [];

        foreach ($rijen as $i => $rij) {
            $regelnr = $i + 2; // +1 kop, +1 mensvriendelijk
            $voornaam = $rij['voornaam'] ?? '';
            $achternaam = $rij['achternaam'] ?? '';
            if ($voornaam === '' || $achternaam === '') {
                $fouten[] = "Regel {$regelnr}: voornaam en achternaam zijn verplicht.";

                continue;
            }

            $cursusId = null;
            $cursuscode = trim((string) ($rij['cursuscode'] ?? ''));
            if ($cursuscode !== '') {
                $cursus = $cursusPerCode->get(strtolower($cursuscode));
                if (! $cursus) {
                    $fouten[] = "Regel {$regelnr}: onbekende cursuscode '{$cursuscode}'.";

                    continue;
                }
                $cursusId = $cursus->id;
            }

            $geldig[] = [
                'voornaam' => $voornaam,
                'tussenvoegsel' => $rij['tussenvoegsel'] ?? null ?: null,
                'achternaam' => $achternaam,
                'geboortedatum' => $this->parseDatum($rij['geboortedatum'] ?? ''),
                'email' => $rij['email'] ?? null ?: null,
                'telefoon' => $rij['telefoon'] ?? null ?: null,
                'adres' => $rij['adres'] ?? null ?: null,
                'postcode' => $rij['postcode'] ?? null ?: null,
                'woonplaats' => $rij['woonplaats'] ?? null ?: null,
                'cursus_id' => $cursusId,
                'cursus' => $cursusId ? $cursusPerCode->get(strtolower($cursuscode))->naam : null,
                'naam' => trim($voornaam.' '.$achternaam),
            ];
        }

        session()->put('cursist_import', $geldig);
        session()->put('cursist_import_fouten', $fouten);

        return view('cursisten.import-controle', [
            'geldig' => $geldig,
            'fouten' => $fouten,
            'bestandsnaam' => $bestand->getClientOriginalName(),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $rijen = session('cursist_import', []);
        if (empty($rijen)) {
            return redirect()->route('cursisten')->withErrors(['bestand' => 'Er is geen gecontroleerde import. Upload eerst een bestand.']);
        }

        $aantal = 0;
        $ingeschreven = 0;
        foreach ($rijen as $r) {
            $cursusId = $r['cursus_id'] ?? null;
            unset($r['cursus_id'], $r['cursus'], $r['naam']);

            $cursist = null;
            for ($poging = 0; $poging < 5 && $cursist === null; $poging++) {
                try {
                    $cursist = Cursist::create($r + ['status' => 'actief', 'cursistnummer' => CursistnummerGenerator::genereer()]);
                } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                    $cursist = null;
                }
            }
            if ($cursist === null) {
                continue;
            }
            $aantal++;

            if ($cursusId) {
                $this->schrijfIn($cursist, $cursusId, $request->user()->id);
                $ingeschreven++;
            }
        }

        $fouten = session('cursist_import_fouten', []);
        session()->forget(['cursist_import', 'cursist_import_fouten']);

        AuditLogger::log(AuditLogger::AANMAAK, 'Cursist', veld: 'cursist_import',
            context: ['aantal' => $aantal, 'ingeschreven' => $ingeschreven]);

        return redirect()->route('cursisten')->with('status',
            "{$aantal} cursisten geïmporteerd".($ingeschreven ? ", waarvan {$ingeschreven} direct ingeschreven." : '.'));
    }

    /* ------------------------------------------------------------- helpers */

    private function actieveCursussen(User $gebruiker)
    {
        return Cursus::query()->zichtbaarVoor($gebruiker)->where('actief', true)->orderBy('naam')->get();
    }

    /** Schrijf een cursist in op een cursus met het cursusgeld als momentopname. */
    private function schrijfIn(Cursist $cursist, int $cursusId, int $doorId): void
    {
        $cursus = Cursus::find($cursusId);
        if (! $cursus) {
            return;
        }

        $cursist->inschrijvingen()->create([
            'cursus_id' => $cursus->id,
            'inschrijfdatum' => now()->toDateString(),
            'status' => CursusinschrijvingStatus::Actief,
            'totaalbedrag' => $cursus->cursusgeld,
            'ingeschreven_door_id' => $doorId,
        ]);
    }

    private function valideer(Request $request): array
    {
        return $request->validate([
            'aanhef' => ['nullable', 'string', 'max:20'],
            'voornaam' => ['required', 'string', 'max:120'],
            'tussenvoegsel' => ['nullable', 'string', 'max:40'],
            'achternaam' => ['required', 'string', 'max:120'],
            'geboortedatum' => ['nullable', 'date'],
            'geslacht' => ['nullable', 'string', 'max:20'],
            'adres' => ['nullable', 'string', 'max:180'],
            'postcode' => ['nullable', 'string', 'max:12'],
            'woonplaats' => ['nullable', 'string', 'max:120'],
            'telefoon' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:180'],
            'opmerkingen' => ['nullable', 'string', 'max:2000'],
            'cursus_id' => ['nullable', Rule::exists('cursussen', 'id')],
        ]);
    }

    private function parseDatum(string $raw): ?string
    {
        $s = trim($raw);
        if ($s === '') {
            return null;
        }
        foreach (['d-m-Y', 'Y-m-d', 'd/m/Y', 'd-m-y', 'd.m.Y'] as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $s);
            if ($d !== false && $d->format($fmt) === $s) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }
}
