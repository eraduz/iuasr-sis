<?php

namespace App\Http\Controllers\Scriptie;

use App\Enums\Scriptiestap;
use App\Http\Controllers\Controller;
use App\Models\Scriptie;
use App\Models\ScriptieGesprek;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Begeleidingsgesprekken binnen een scriptietraject (onderdeel van stap 6). De
 * begeleider (docent) of de coördinator registreert en corrigeert; gesprekken
 * kunnen worden verwijderd zolang het traject loopt.
 */
class ScriptieGesprekController extends Controller
{
    public function store(Request $request, Scriptie $scriptie): RedirectResponse
    {
        abort_unless($scriptie->magStapBewerken($request->user(), Scriptiestap::PlanVanAanpak), 403);

        $data = $this->valideer($request);
        $data['geregistreerd_door_id'] = $request->user()->id;
        $scriptie->gesprekken()->create($data);

        AuditLogger::log(AuditLogger::AANMAAK, $scriptie, veld: 'scriptie_gesprek');

        return $this->terug($scriptie, 'Begeleidingsgesprek geregistreerd.');
    }

    public function update(Request $request, Scriptie $scriptie, ScriptieGesprek $gesprek): RedirectResponse
    {
        abort_unless($gesprek->scriptie_id === $scriptie->id, 404);
        abort_unless($scriptie->magStapBewerken($request->user(), Scriptiestap::PlanVanAanpak), 403);

        $gesprek->update($this->valideer($request));
        AuditLogger::log(AuditLogger::WIJZIGING, $scriptie, veld: 'scriptie_gesprek', context: ['id' => $gesprek->id]);

        return $this->terug($scriptie, 'Begeleidingsgesprek bijgewerkt.');
    }

    public function destroy(Request $request, Scriptie $scriptie, ScriptieGesprek $gesprek): RedirectResponse
    {
        abort_unless($gesprek->scriptie_id === $scriptie->id, 404);
        abort_unless($scriptie->magStapBewerken($request->user(), Scriptiestap::PlanVanAanpak), 403);

        $gesprek->delete();
        AuditLogger::log(AuditLogger::VERWIJDERING, $scriptie, veld: 'scriptie_gesprek', context: ['id' => $gesprek->id]);

        return $this->terug($scriptie, 'Begeleidingsgesprek verwijderd.');
    }

    /** @return array<string, mixed> */
    private function valideer(Request $request): array
    {
        $data = $request->validate([
            'datum' => ['required', 'date'],
            'begintijd' => ['nullable', 'date_format:H:i'],
            'eindtijd' => ['nullable', 'date_format:H:i'],
            'locatie' => ['nullable', 'string', 'max:255'],
            'online' => ['nullable', 'boolean'],
            'onderwerp' => ['nullable', 'string', 'max:255'],
            'besproken' => ['nullable', 'string', 'max:5000'],
            'feedback' => ['nullable', 'string', 'max:5000'],
            'afspraken' => ['nullable', 'string', 'max:5000'],
            'actiepunten_student' => ['nullable', 'string', 'max:5000'],
            'actiepunten_begeleider' => ['nullable', 'string', 'max:5000'],
            'actiepunten_deadline' => ['nullable', 'date'],
            'status' => ['required', Rule::in(array_keys(ScriptieGesprek::STATUSSEN))],
            'bevestigd_student' => ['nullable', 'boolean'],
            'bevestigd_begeleider' => ['nullable', 'boolean'],
        ]);

        $data['online'] = $request->boolean('online');
        $data['bevestigd_student'] = $request->boolean('bevestigd_student');
        $data['bevestigd_begeleider'] = $request->boolean('bevestigd_begeleider');

        return $data;
    }

    private function terug(Scriptie $scriptie, string $melding): RedirectResponse
    {
        return redirect()
            ->route('scriptie.show', ['scriptie' => $scriptie, 'tab' => Scriptiestap::PlanVanAanpak->value])
            ->with('status', $melding);
    }
}
