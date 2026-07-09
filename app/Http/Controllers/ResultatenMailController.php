<?php

namespace App\Http\Controllers;

use App\Mail\ResultatenCijferlijstMail;
use App\Models\Inschrijving;
use App\Models\Opleiding;
use App\Models\Periode;
use App\Models\Resultaat;
use App\Support\AuditLogger;
use App\Support\Documentondertekening;
use App\Support\Transcript;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Verstuurt aan het einde van het blok de definitieve resultaten per e-mail naar
 * de studenten van een opleiding. Elke student krijgt INDIVIDUEEL zijn/haar eigen
 * officiële (ondertekende) cijferlijst als PDF-bijlage. Alleen vastgestelde
 * resultaten tellen mee; studenten zonder vastgestelde resultaten of zonder
 * e-mailadres worden overgeslagen. Voor Examencommissie en Directie; gelogd.
 */
class ResultatenMailController extends Controller
{
    /** Controlestap: toon wie een e-mail krijgt en wie wordt overgeslagen. */
    public function overzicht(Request $request): View
    {
        $data = $request->validate(['opleiding_id' => ['required', 'exists:opleidingen,id']]);
        $opleiding = Opleiding::findOrFail($data['opleiding_id']);
        $this->autoriseerOpleiding($request, $opleiding);
        [$teVersturen, $overgeslagen] = $this->bepaalOntvangers($opleiding);

        return view('rapporten.resultaten-mailen', compact('opleiding', 'teVersturen', 'overgeslagen'));
    }

    /** Definitief versturen. */
    public function versturen(Request $request): RedirectResponse
    {
        $data = $request->validate(['opleiding_id' => ['required', 'exists:opleidingen,id']]);
        $opleiding = Opleiding::findOrFail($data['opleiding_id']);
        $this->autoriseerOpleiding($request, $opleiding);
        [$teVersturen] = $this->bepaalOntvangers($opleiding);

        $aantal = 0;
        foreach ($teVersturen as $rij) {
            $student = $rij['student'];
            $transcript = Transcript::voor($student, alleenDefinitief: true);

            $html = view('pdf.cijferlijst', [
                'student' => $student, 'transcript' => $transcript, 'ondertekenaar' => auth()->user()->naam,
            ])->render();

            $doc = Documentondertekening::ondertekenHtml($html, [
                'type' => 'cijferlijst',
                'titel' => 'Cijferlijst '.$student->studentnummer,
                'student_id' => $student->id,
                'ontvanger' => $student->volledigeNaam().' (e-mail)',
                'uitgegeven_door_id' => auth()->id(),
            ]);

            $periodeNaam = Periode::where('actief', true)->value('naam') ?? '';

            Mail::to($rij['email'])->send(new ResultatenCijferlijstMail(
                $student->volledigeNaam(),
                $periodeNaam,
                Documentondertekening::pdfBytes($doc) ?? '',
                'Cijferlijst-'.$student->studentnummer.'.pdf',
            ));

            AuditLogger::log(AuditLogger::UITGIFTE, $student, veld: 'resultaten-email', context: [
                'opleiding' => $opleiding->code, 'code' => $doc->code,
            ]);
            $aantal++;
        }

        return redirect()->route('cijferlijst', ['opleiding_id' => $opleiding->id])
            ->with('status', $aantal.' student(en) van '.$opleiding->code.' hebben hun cijferlijst per e-mail ontvangen.');
    }

    /** Directie mag alleen de eigen opleiding(en) mailen. */
    private function autoriseerOpleiding(Request $request, Opleiding $opleiding): void
    {
        $gebruiker = $request->user();
        abort_if($gebruiker->isOpleidingBeperkt() && ! $gebruiker->opleidingIds()->contains($opleiding->id),
            403, 'Deze opleiding valt buiten uw opleiding(en).');
    }

    /**
     * Splitst de actieve studenten van een opleiding in ontvangers en overgeslagen.
     *
     * @return array{0: list<array{student: \App\Models\Student, email: string}>, 1: list<array{student: \App\Models\Student, reden: string}>}
     */
    private function bepaalOntvangers(Opleiding $opleiding): array
    {
        $studenten = Inschrijving::where('status', 'actief')->where('opleiding_id', $opleiding->id)
            ->with('student')->get()->pluck('student')->filter()->unique('id');

        $teVersturen = [];
        $overgeslagen = [];
        foreach ($studenten as $student) {
            $email = $student->email ?: $student->email_prive;
            if (! Resultaat::where('student_id', $student->id)->where('definitief', true)->exists()) {
                $overgeslagen[] = ['student' => $student, 'reden' => 'geen vastgestelde resultaten'];

                continue;
            }
            if (! $email) {
                $overgeslagen[] = ['student' => $student, 'reden' => 'geen e-mailadres'];

                continue;
            }
            $teVersturen[] = ['student' => $student, 'email' => $email];
        }

        return [$teVersturen, $overgeslagen];
    }
}
