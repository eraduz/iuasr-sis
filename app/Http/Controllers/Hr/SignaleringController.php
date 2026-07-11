<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Dienstverband;
use App\Models\Medewerker;
use App\Support\Verzuimsignalering;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Signaleringen (module HR / Personeelszaken — Fase G): één overzicht met de
 * slimme, afgeleide signalen — aflopende contracten en verzuimsignalering
 * (Wet Verbetering Poortwachter + frequent verzuim). Gescoped: een team-beperkte
 * gebruiker ziet uitsluitend het eigen team; HR/Beheer/Bestuur zien iedereen.
 */
class SignaleringController extends Controller
{
    public function index(Request $request): View
    {
        $ids = $this->scope($request);

        $dagen = (int) config('sis.hr.contract_signaal_dagen', 60);
        $aflopend = Dienstverband::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('medewerker_id', $ids))
            ->whereNotNull('einddatum')
            ->whereDate('einddatum', '>=', now()->toDateString())
            ->whereDate('einddatum', '<=', now()->addDays($dagen)->toDateString())
            ->with(['medewerker', 'functie'])
            ->orderBy('einddatum')->get();

        return view('hr.signaleringen', [
            'aflopend' => $aflopend,
            'contractDagen' => $dagen,
            'langdurig' => Verzuimsignalering::langdurig($ids),
            'frequent' => Verzuimsignalering::frequent($ids),
        ]);
    }

    /** Medewerker-ids binnen de scope, of null (geen beperking). */
    private function scope(Request $request): ?array
    {
        if (! $request->user()->isHrTeamBeperkt()) {
            return null;
        }

        return Medewerker::query()->zichtbaarVoor($request->user())->pluck('id')->all();
    }
}
