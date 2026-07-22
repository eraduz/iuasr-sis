<?php

namespace App\Http\Controllers;

use App\Enums\CijferlijstStatus;
use App\Jobs\VerstuurCijferlijst;
use App\Models\Cijferlijst;
use App\Models\Cijferlijstverzending;
use App\Models\Inschrijving;
use App\Models\Opleiding;
use App\Models\Periode;
use App\Models\Resultaat;
use App\Models\Vak;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Cijfers mailen (einde blok). De examencommissie/directie ziet per opleiding de
 * blok-vakken met hun vaststellingsstatus en verstuurt met één klik de officiële
 * cijferlijst naar de studenten. Elke student krijgt INDIVIDUEEL de eigen
 * (ondertekende) PDF; alleen vastgestelde resultaten tellen mee.
 *
 * Het versturen gaat via de wachtrij ({@see VerstuurCijferlijst}) en wordt per
 * (student, periode) geregistreerd in {@see Cijferlijstverzending} — dat voorkomt
 * dubbel versturen en toont de status (in wachtrij / verzonden / mislukt).
 */
class ResultatenMailController extends Controller
{
    /** Hub: alle (zichtbare) opleidingen van de actieve periode met status. */
    public function hub(Request $request): View
    {
        $periode = Periode::where('actief', true)->firstOrFail();
        $gebruiker = $request->user();

        $opleidingen = Opleiding::where('actief', true)
            ->when($gebruiker->isOpleidingBeperkt(), fn ($q) => $q->whereIn('id', $gebruiker->opleidingIds()))
            ->orderBy('naam')->get();

        $rijen = $opleidingen->map(function (Opleiding $opleiding) use ($periode) {
            [$teVersturen, $overgeslagen, $alVerzonden] = $this->categoriseer($opleiding, $periode);
            $vakken = $this->blokVakken($opleiding, $periode);

            return [
                'opleiding' => $opleiding,
                'vakken' => $vakken,
                'vastgesteld' => $vakken->filter(fn ($v) => $v['status'] === CijferlijstStatus::Vastgesteld)->count(),
                'teVersturen' => count($teVersturen),
                'overgeslagen' => count($overgeslagen),
                'alVerzonden' => count($alVerzonden),
            ];
        })->filter(fn ($r) => $r['vakken']->isNotEmpty() || $r['teVersturen'] || $r['alVerzonden'])->values();

        return view('cijfers.mailen', compact('periode', 'rijen'));
    }

    /** Detailoverzicht per opleiding: wie krijgt een mail, wie is al gemaild, wie overgeslagen. */
    public function overzicht(Request $request): View
    {
        $data = $request->validate(['opleiding_id' => ['required', 'exists:opleidingen,id']]);
        $opleiding = Opleiding::findOrFail($data['opleiding_id']);
        $this->autoriseerOpleiding($request, $opleiding);
        $periode = Periode::where('actief', true)->firstOrFail();

        [$teVersturen, $overgeslagen, $alVerzonden] = $this->categoriseer($opleiding, $periode);

        return view('rapporten.resultaten-mailen', compact('opleiding', 'periode', 'teVersturen', 'overgeslagen', 'alVerzonden'));
    }

    /** Definitief versturen (in de wachtrij). Met ?opnieuw=1 ook de al-gemailden. */
    public function versturen(Request $request): RedirectResponse
    {
        $data = $request->validate(['opleiding_id' => ['required', 'exists:opleidingen,id']]);
        $opleiding = Opleiding::findOrFail($data['opleiding_id']);
        $this->autoriseerOpleiding($request, $opleiding);
        $periode = Periode::where('actief', true)->firstOrFail();
        $opnieuw = $request->boolean('opnieuw');

        [$teVersturen] = $this->categoriseer($opleiding, $periode, includeAlVerzonden: $opnieuw);

        $aantal = 0;
        foreach ($teVersturen as $rij) {
            $student = $rij['student'];
            $verzending = Cijferlijstverzending::updateOrCreate(
                ['student_id' => $student->id, 'periode_id' => $periode->id],
                [
                    'opleiding_id' => $opleiding->id, 'status' => 'in_wachtrij', 'ontvanger' => $rij['email'],
                    'verzonden_door_id' => $request->user()->id, 'verzonden_op' => null, 'foutmelding' => null,
                ]
            );
            VerstuurCijferlijst::dispatch($verzending->id, $request->user()->naam);
            $aantal++;
        }

        AuditLogger::log(AuditLogger::UITGIFTE, $opleiding, veld: 'resultaten-email-batch', context: [
            'opleiding' => $opleiding->code, 'aantal' => $aantal, 'periode' => $periode->naam, 'opnieuw' => $opnieuw,
        ]);

        $melding = $aantal === 0
            ? 'Geen studenten om te mailen voor '.$opleiding->code.' (alles is al verstuurd of er zijn geen vastgestelde resultaten).'
            : $aantal.' cijferlijst(en) van '.$opleiding->code.' zijn in de wachtrij gezet en worden verstuurd.';

        return redirect()->route('cijfers-mailen')->with('status', $melding);
    }

    /** Directie mag alleen de eigen opleiding(en) mailen. */
    private function autoriseerOpleiding(Request $request, Opleiding $opleiding): void
    {
        $gebruiker = $request->user();
        abort_if($gebruiker->isOpleidingBeperkt() && ! $gebruiker->opleidingIds()->contains($opleiding->id),
            403, 'Deze opleiding valt buiten uw opleiding(en).');
    }

    /**
     * De blok-vakken van een opleiding met hun cijferlijst-status in deze periode.
     *
     * @return \Illuminate\Support\Collection<int, array{vak: Vak, status: CijferlijstStatus}>
     */
    private function blokVakken(Opleiding $opleiding, Periode $periode)
    {
        $vakken = Vak::where('opleiding_id', $opleiding->id)->where('actief', true)
            ->orderBy('leerjaar')->orderBy('code')->get();
        $lijsten = Cijferlijst::where('periode_id', $periode->id)->whereIn('vak_id', $vakken->pluck('id'))
            ->get()->keyBy('vak_id');

        return $vakken->map(fn (Vak $vak) => [
            'vak' => $vak,
            'status' => $lijsten[$vak->id]?->status ?? CijferlijstStatus::Concept,
        ]);
    }

    /**
     * Splitst de actieve studenten van een opleiding in: te versturen, overgeslagen
     * (geen vastgestelde resultaten of geen e-mailadres) en al verzonden (deze periode).
     *
     * @return array{0: list<array{student: \App\Models\Student, email: string}>, 1: list<array{student: \App\Models\Student, reden: string}>, 2: list<array{student: \App\Models\Student, verzending: Cijferlijstverzending}>}
     */
    private function categoriseer(Opleiding $opleiding, Periode $periode, bool $includeAlVerzonden = false): array
    {
        $studenten = Inschrijving::where('status', 'actief')->where('opleiding_id', $opleiding->id)
            ->with('student')->get()->pluck('student')->filter()->unique('id');

        $verzendingen = Cijferlijstverzending::where('periode_id', $periode->id)
            ->whereIn('student_id', $studenten->pluck('id'))->get()->keyBy('student_id');

        $teVersturen = [];
        $overgeslagen = [];
        $alVerzonden = [];
        foreach ($studenten as $student) {
            $v = $verzendingen->get($student->id);
            $reeds = $v !== null && in_array($v->status, ['verzonden', 'in_wachtrij'], true);
            if ($reeds && ! $includeAlVerzonden) {
                $alVerzonden[] = ['student' => $student, 'verzending' => $v];

                continue;
            }
            if (! Resultaat::where('student_id', $student->id)->where('definitief', true)->exists()) {
                $overgeslagen[] = ['student' => $student, 'reden' => 'geen vastgestelde resultaten'];

                continue;
            }
            $email = $student->email ?: $student->email_prive;
            if (! $email) {
                $overgeslagen[] = ['student' => $student, 'reden' => 'geen e-mailadres'];

                continue;
            }
            $teVersturen[] = ['student' => $student, 'email' => $email];
        }

        return [$teVersturen, $overgeslagen, $alVerzonden];
    }
}
