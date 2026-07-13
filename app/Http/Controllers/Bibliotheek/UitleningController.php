<?php

namespace App\Http\Controllers\Bibliotheek;

use App\Enums\BibliotheekMailsoort;
use App\Enums\ExemplaarStatus;
use App\Enums\Materiaalstaat;
use App\Enums\MedewerkerStatus;
use App\Http\Controllers\Controller;
use App\Models\Bibliotheek\Exemplaar;
use App\Models\Bibliotheek\Uitlening;
use App\Models\Medewerker;
use App\Models\Student;
use App\Support\AuditLogger;
use App\Support\BibliotheekMailer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Uitlenen en innemen.
 *
 * De lener is ALTIJD een bestaande student of medewerker (echte foreign key);
 * naam, telefoon en e-mail komen uit het dossier. Zo klopt het te-laat-signaal
 * op het Studentenzaken-dashboard en is er geen dubbele administratie.
 *
 * Wat afgeleid kan worden, wordt afgeleid: 'te laat', 'op tijd ingeleverd' en
 * 'aantal dagen te laat' staan niet in de database (zie het model Uitlening).
 * De status van het exemplaar volgt de uitleen: uitlenen zet hem op Uitgeleend,
 * innemen op Beschikbaar — of op Beschadigd bij schade.
 */
class UitleningController extends Controller
{
    public function index(Request $request): View
    {
        $filter = (string) $request->query('status', 'lopend');

        $uitleningen = Uitlening::query()
            ->with(['exemplaar.publicatie', 'student', 'medewerker', 'emaillogs'])
            ->when($filter === 'lopend', fn ($q) => $q->lopend())
            ->when($filter === 'telaat', fn ($q) => $q->teLaat())
            ->when($filter === 'retour', fn ($q) => $q->whereNotNull('retour_op'))
            ->when($request->filled('q'), function ($q) use ($request) {
                $zoek = trim((string) $request->query('q'));
                $q->where(function ($sub) use ($zoek) {
                    $sub->whereHas('exemplaar.publicatie', fn ($p) => $p->where('titel', 'like', "%{$zoek}%"))
                        ->orWhereHas('exemplaar', fn ($e) => $e->where('serienummer', 'like', "%{$zoek}%"))
                        ->orWhereHas('student', fn ($s) => $s->where('achternaam', 'like', "%{$zoek}%")
                            ->orWhere('voornaam', 'like', "%{$zoek}%")
                            ->orWhere('studentnummer', 'like', "%{$zoek}%"))
                        ->orWhereHas('medewerker', fn ($m) => $m->where('achternaam', 'like', "%{$zoek}%")
                            ->orWhere('voornaam', 'like', "%{$zoek}%"));
                });
            })
            ->orderByRaw('retour_op is not null')      // lopende bovenaan
            ->orderBy('verwachte_retour_op')            // eerst wat het snelst terug moet
            ->paginate(25)
            ->withQueryString();

        return view('bibliotheek.uitleningen.index', [
            'uitleningen' => $uitleningen,
            'statusFilter' => $filter,
            'zoek' => (string) $request->query('q', ''),
        ]);
    }

    public function create(Request $request): View
    {
        // Vooraf gekozen exemplaar (via de knop 'Uitlenen' op de publicatiekaart).
        $exemplaar = $request->filled('exemplaar')
            ? Exemplaar::with('publicatie')->find((int) $request->query('exemplaar'))
            : null;

        return view('bibliotheek.uitleningen.form', [
            'gekozenExemplaar' => $exemplaar,
            'exemplaren' => Exemplaar::query()->uitleenbaar()->with('publicatie')
                ->whereDoesntHave('uitleningen', fn ($u) => $u->whereNull('retour_op'))
                ->get()->sortBy(fn (Exemplaar $e) => $e->publicatie?->titel)->values(),
            'studenten' => Student::orderBy('achternaam')->orderBy('voornaam')->get(),
            'medewerkers' => Medewerker::where('status', '!=', MedewerkerStatus::UitDienst)
                ->orderBy('achternaam')->orderBy('voornaam')->get(),
            'termijnStudent' => (int) config('sis.bibliotheek.uitleentermijn_student_dagen'),
            'termijnDocent' => (int) config('sis.bibliotheek.uitleentermijn_docent_dagen'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'exemplaar_id' => ['required', 'integer', 'exists:bibliotheek_exemplaren,id'],
            'lenerstype' => ['required', Rule::in(['student', 'medewerker'])],
            'student_id' => ['nullable', 'integer', 'exists:studenten,id'],
            'medewerker_id' => ['nullable', 'integer', 'exists:medewerkers,id'],
            'uitgeleend_op' => ['required', 'date'],
            'verwachte_retour_op' => ['required', 'date', 'after:uitgeleend_op'],
        ], [], [
            'exemplaar_id' => 'exemplaar',
            'student_id' => 'student',
            'medewerker_id' => 'medewerker',
            'uitgeleend_op' => 'uitleendatum',
            'verwachte_retour_op' => 'verwachte retourdatum',
        ]);

        // Precies één lener: het type bepaalt welk veld gevuld moet zijn.
        $isStudent = $data['lenerstype'] === 'student';
        $lenerVeld = $isStudent ? 'student_id' : 'medewerker_id';

        if (empty($data[$lenerVeld])) {
            throw ValidationException::withMessages([
                $lenerVeld => $isStudent ? 'Kies een student.' : 'Kies een medewerker.',
            ]);
        }

        $exemplaar = Exemplaar::with('publicatie')->findOrFail($data['exemplaar_id']);

        // Dubbele uitlening voorkomen: de lopende uitlening is de waarheid, niet de status.
        if (! $exemplaar->isUitleenbaar()) {
            throw ValidationException::withMessages([
                'exemplaar_id' => 'Dit exemplaar is niet beschikbaar (uitgeleend, verloren of beschadigd).',
            ]);
        }

        $uitlening = Uitlening::create([
            'exemplaar_id' => $exemplaar->id,
            'student_id' => $isStudent ? (int) $data['student_id'] : null,
            'medewerker_id' => $isStudent ? null : (int) $data['medewerker_id'],
            'uitgeleend_op' => $data['uitgeleend_op'],
            'verwachte_retour_op' => $data['verwachte_retour_op'],
            'uitgeleend_door_user_id' => $request->user()->id,
        ]);

        $exemplaar->update(['status' => ExemplaarStatus::Uitgeleend]);

        AuditLogger::log(AuditLogger::AANMAAK, $uitlening, veld: 'uitlening', context: [
            'serienummer' => $exemplaar->serienummer,
            'lener' => $uitlening->lenerNaam(),
            'retour_op' => $uitlening->verwachte_retour_op->toDateString(),
        ]);

        // De bevestigingsmail mag de uitlening nooit blokkeren; mislukt hij, dan
        // staat dat in het e-maillogboek.
        $verstuurd = BibliotheekMailer::verstuur($uitlening, BibliotheekMailsoort::Uitleenbevestiging);

        return redirect()->route('bibliotheek.uitleningen')
            ->with('status', 'Uitgeleend aan '.$uitlening->lenerNaam().'.'
                .($verstuurd ? ' Bevestigingsmail verstuurd.' : ' Let op: de bevestigingsmail is niet verstuurd (zie het e-maillogboek).'));
    }

    /** Innameformulier (retourverwerking). */
    public function innameForm(Request $request, Uitlening $uitlening): View
    {
        abort_unless($request->user()->magBibliotheekBeheren(), 403, 'U mag de bibliotheek niet beheren.');
        abort_if($uitlening->isRetour(), 404, 'Deze uitlening is al ingenomen.');

        $uitlening->load(['exemplaar.publicatie', 'student', 'medewerker']);

        return view('bibliotheek.uitleningen.inname', ['uitlening' => $uitlening]);
    }

    public function innemen(Request $request, Uitlening $uitlening): RedirectResponse
    {
        abort_unless($request->user()->magBibliotheekBeheren(), 403, 'U mag de bibliotheek niet beheren.');

        if ($uitlening->isRetour()) {
            return back()->with('fout', 'Deze uitlening is al ingenomen.');
        }

        $data = $request->validate([
            'retour_op' => ['required', 'date', 'after_or_equal:'.$uitlening->uitgeleend_op->toDateString()],
            'staat' => ['required', Rule::in(Materiaalstaat::waarden())],
            'retour_opmerking' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'retour_op' => 'retourdatum',
            'staat' => 'staat van het materiaal',
        ]);

        $staat = Materiaalstaat::from($data['staat']);

        $uitlening->innemen(
            $staat,
            $data['retour_opmerking'] ?? null,
            $request->user(),
            Carbon::parse($data['retour_op']),
        );

        AuditLogger::log(AuditLogger::WIJZIGING, $uitlening, veld: 'inname', context: [
            'serienummer' => $uitlening->exemplaar->serienummer,
            'staat' => $staat->value,
            'op_tijd' => $uitlening->isOpTijdIngeleverd(),
            'dagen_te_laat' => $uitlening->dagenTeLaat(),
        ]);

        BibliotheekMailer::verstuur($uitlening, BibliotheekMailsoort::Retourbevestiging);

        $melding = 'Ingenomen: '.$uitlening->exemplaar->serienummer.'.';

        if ($staat->isSchade()) {
            // Bij schade gaat het exemplaar uit de uitleen (status Beschadigd) en
            // volgt er een melding voor de bibliotheekmedewerker.
            AuditLogger::log(AuditLogger::WIJZIGING, $uitlening->exemplaar, veld: 'schademelding', context: [
                'staat' => $staat->value,
                'opmerking' => $data['retour_opmerking'] ?? null,
            ]);
            $melding .= ' SCHADE gemeld: het exemplaar staat nu op "Beschadigd" en is niet uitleenbaar.';
        }

        if (! $uitlening->isOpTijdIngeleverd()) {
            $melding .= ' Te laat ingeleverd ('.$uitlening->dagenTeLaat().' dagen).';
        }

        return redirect()->route('bibliotheek.uitleningen')->with('status', $melding);
    }

    /** Detailpagina van de lener: wat heeft hij geleend, en welke mails zijn verstuurd? */
    public function lener(Request $request, string $type, int $id): View
    {
        abort_unless($request->user()->magBibliotheekInzien(), 403, 'Geen toegang tot de bibliotheek.');
        abort_unless(in_array($type, ['student', 'medewerker'], true), 404);

        $lener = $type === 'student' ? Student::findOrFail($id) : Medewerker::findOrFail($id);

        $uitleningen = Uitlening::query()
            ->with(['exemplaar.publicatie', 'emaillogs'])
            ->where($type === 'student' ? 'student_id' : 'medewerker_id', $id)
            ->orderByDesc('uitgeleend_op')
            ->get();

        return view('bibliotheek.uitleningen.lener', [
            'lener' => $lener,
            'type' => $type,
            'uitleningen' => $uitleningen,
            // Het aantal verzonden e-mails moet zichtbaar zijn op de lenerpagina.
            'aantalMails' => $uitleningen->sum(fn (Uitlening $u) => $u->emaillogs->count()),
        ]);
    }
}
