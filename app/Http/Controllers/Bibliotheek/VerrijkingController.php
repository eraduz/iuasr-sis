<?php

namespace App\Http\Controllers\Bibliotheek;

use App\Http\Controllers\Controller;
use App\Models\Bibliotheek\Publicatie;
use App\Models\Bibliotheek\Verrijking;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\BibliotheekVerrijker;

/**
 * Overzicht van de verrijking met de externe bibliografische bron.
 *
 * Wat automatisch is toegepast (zekere match), en vooral: welke titels zijn
 * OVERGESLAGEN omdat de match onzeker was. Die twijfelgevallen zijn precies de
 * lijst die een mens moet nalopen — het systeem raadt niet.
 *
 * Vanaf hier kan de bibliotheekmedewerker een overgeslagen voorstel alsnog
 * handmatig overnemen (met één klik) of definitief afwijzen.
 */
class VerrijkingController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->magBibliotheekBeheren(), 403, 'U mag de bibliotheek niet beheren.');

        $status = (string) $request->query('status', Verrijking::ONZEKER);

        $verrijkingen = Verrijking::query()
            ->with(['publicatie.auteurs', 'publicatie.talen'])
            ->when(in_array($status, [Verrijking::TOEGEPAST, Verrijking::ONZEKER, Verrijking::GEEN_TREFFER, Verrijking::FOUT], true),
                fn ($q) => $q->where('status', $status))
            ->orderByDesc('score')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('bibliotheek.verrijking', [
            'verrijkingen' => $verrijkingen,
            'status' => $status,
            'telling' => Verrijking::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'nogTeGaan' => Publicatie::whereHas('talen', fn ($q) => $q->whereIn('code', BibliotheekVerrijker::TALEN))
                ->whereDoesntHave('verrijkingen')->count(),
            'metIsbn' => Publicatie::whereNotNull('isbn')->count(),
        ]);
    }

    /**
     * Een overgeslagen (onzeker) voorstel alsnog overnemen — een bewuste keuze van
     * een mens. Neemt ISBN en jaar over, en de schrijfwijze van de titel.
     */
    public function overnemen(Request $request, Verrijking $verrijking): RedirectResponse
    {
        abort_unless($request->user()->magBibliotheekBeheren(), 403, 'U mag de bibliotheek niet beheren.');
        abort_unless($verrijking->status === Verrijking::ONZEKER, 422, 'Alleen een overgeslagen voorstel kan handmatig worden overgenomen.');

        $publicatie = $verrijking->publicatie;
        $oudeTitel = $publicatie->titel;
        $wijzigingen = [];

        if (($publicatie->isbn === null || $publicatie->isbn === '') && $verrijking->isbn) {
            $publicatie->isbn = $verrijking->isbn;
            $wijzigingen['isbn'] = ['oud' => null, 'nieuw' => $verrijking->isbn];
        }

        if ($publicatie->uitgavejaar === null && $verrijking->jaar) {
            $publicatie->uitgavejaar = $verrijking->jaar;
            $wijzigingen['uitgavejaar'] = ['oud' => null, 'nieuw' => $verrijking->jaar];
        }

        if ($verrijking->gevonden_titel && $verrijking->gevonden_titel !== $oudeTitel) {
            $publicatie->titel = mb_substr($verrijking->gevonden_titel, 0, 255);
            $wijzigingen['titel'] = ['oud' => $oudeTitel, 'nieuw' => $publicatie->titel];
        }

        $publicatie->save();

        $verrijking->update([
            'status' => Verrijking::TOEGEPAST,
            'oude_titel' => $oudeTitel,
            'toelichting' => 'Handmatig overgenomen door '.$request->user()->naam.'.',
        ]);

        AuditLogger::log(AuditLogger::WIJZIGING, $publicatie, veld: 'bibliotheek_verrijking_handmatig', context: $wijzigingen);

        return back()->with('status', 'Voorstel overgenomen voor "'.$publicatie->titel.'".');
    }

    /** Een voorstel definitief afwijzen: onze eigen gegevens blijven staan. */
    public function afwijzen(Request $request, Verrijking $verrijking): RedirectResponse
    {
        abort_unless($request->user()->magBibliotheekBeheren(), 403, 'U mag de bibliotheek niet beheren.');

        $verrijking->update([
            'status' => Verrijking::GEEN_TREFFER,
            'toelichting' => 'Afgewezen door '.$request->user()->naam.'; onze gegevens blijven staan.',
        ]);

        return back()->with('status', 'Voorstel afgewezen.');
    }
}
