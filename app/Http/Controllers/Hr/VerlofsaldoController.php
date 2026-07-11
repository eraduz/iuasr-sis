<?php

namespace App\Http\Controllers\Hr;

use App\Enums\Verloftype;
use App\Http\Controllers\Controller;
use App\Models\Medewerker;
use App\Models\Verlofsaldo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Het verlofrecht (uren per type per jaar) van een medewerker instellen — module
 * HR. Alleen HR en Beheer. Het opgenomen verlof en het saldo zijn afgeleid.
 */
class VerlofsaldoController extends Controller
{
    public function bijwerken(Request $request, Medewerker $medewerker): RedirectResponse
    {
        abort_unless($medewerker->beheerbaarVoor($request->user()), 403, 'U mag dit personeelsdossier niet wijzigen.');

        $request->validate([
            'jaar' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'recht' => ['array'],
            'recht.*' => ['nullable', 'numeric', 'min:0', 'max:2000'],
        ]);
        $jaar = (int) $request->input('jaar', date('Y'));

        foreach (Verloftype::waarden() as $type) {
            $uren = $request->input("recht.$type");
            if ($uren === null || $uren === '') {
                continue;
            }
            Verlofsaldo::updateOrCreate(
                ['medewerker_id' => $medewerker->id, 'jaar' => $jaar, 'verloftype' => $type],
                ['recht_uren' => $uren]
            );
        }

        return redirect()->route('medewerkers.show', $medewerker)->with('status', 'Verlofrecht bijgewerkt.');
    }
}
