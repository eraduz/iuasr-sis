<?php

namespace App\Http\Controllers\Scriptie;

use App\Enums\Rol;
use App\Enums\Scriptiestap;
use App\Http\Controllers\Controller;
use App\Models\Scriptie;
use App\Support\AuditLogger;
use App\Support\Documentondertekening;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * De mutaties per stap van een scriptietraject: het stapformulier invullen, de
 * status zetten, de stap (sequentieel) afvinken, de checklist beantwoorden, de
 * digitale goedkeuring van de overeenkomst vastleggen en de overeenkomst als
 * ondertekende PDF genereren. De rolscheiding per stap zit in
 * Scriptie::magStapBewerken(); afvinken volgt Scriptiestap::magAfvinkenDoor().
 */
class ScriptieStapController extends Controller
{
    /** Het formulier van een stap opslaan. */
    public function update(Request $request, Scriptie $scriptie, Scriptiestap $stap): RedirectResponse
    {
        abort_unless($scriptie->magStapBewerken($request->user(), $stap), 403);

        $regels = $this->veldRegels($stap);
        $data = $regels === [] ? [] : $request->validate($regels);

        // Vinkjes komen alleen mee als ze zijn aangevinkt: expliciet normaliseren.
        foreach ($this->booleanVelden($stap) as $veld) {
            $data[$veld] = $request->boolean($veld);
        }

        if ($data !== []) {
            $scriptie->update($data);
            AuditLogger::log(AuditLogger::WIJZIGING, $scriptie, veld: 'scriptie_stap_'.$stap->value);
        }

        return $this->terug($scriptie, $stap, $stap->label().' opgeslagen.');
    }

    /** De status (en opmerking) van een stap zetten. */
    public function status(Request $request, Scriptie $scriptie, Scriptiestap $stap): RedirectResponse
    {
        abort_unless($scriptie->magStapBewerken($request->user(), $stap), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys($stap->statussen()))],
            'opmerking' => ['nullable', 'string', 'max:2000'],
        ]);

        $stand = $scriptie->stapstanden()->where('stap', $stap->value)->firstOrFail();
        $stand->update(['status' => $data['status'], 'opmerking' => $data['opmerking'] ?? $stand->opmerking]);

        AuditLogger::log(AuditLogger::WIJZIGING, $scriptie, veld: 'scriptie_status_'.$stap->value, context: [
            'status' => $data['status'],
        ]);

        return $this->terug($scriptie, $stap, 'Status bijgewerkt naar: '.$stap->statusLabel($data['status']).'.');
    }

    /** Een stap (sequentieel) afvinken of heropenen. */
    public function afvinken(Request $request, Scriptie $scriptie, Scriptiestap $stap): RedirectResponse
    {
        $gebruiker = $request->user();
        abort_unless($scriptie->zichtbaarVoor($gebruiker), 403);
        abort_unless($stap->magAfvinkenDoor($gebruiker), 403, 'Uw rol mag deze stap niet afvinken.');

        if (! $scriptie->isLopend() && ! $gebruiker->heeftRol(Rol::Beheerder)) {
            return $this->terug($scriptie, $stap, 'Het traject is niet meer actief.');
        }

        $request->validate(['opmerking' => ['nullable', 'string', 'max:2000']]);

        $scriptie->load('stapstanden');
        $standen = $scriptie->stapstanden->sortBy('volgorde')->values();
        $index = $standen->search(fn ($s) => $s->stap === $stap);
        $stand = $standen[$index];
        $nu = ! $stand->gereed; // nieuwe waarde: aan of uit

        if ($nu) {
            // Aanzetten: de vorige stap moet gereed zijn.
            $vorige = $index > 0 ? $standen[$index - 1] : null;
            if ($vorige !== null && ! $vorige->gereed) {
                return $this->terug($scriptie, $stap, 'Rond eerst de vorige stap af ('.$vorige->stap->label().').');
            }
        } else {
            // Uitzetten (heropenen): de volgende stap mag nog niet gereed zijn.
            $volgende = $index < $standen->count() - 1 ? $standen[$index + 1] : null;
            if ($volgende !== null && $volgende->gereed) {
                return $this->terug($scriptie, $stap, 'Heropen eerst de volgende stap ('.$volgende->stap->label().').');
            }
        }

        $stand->update([
            'gereed' => $nu,
            'gereed_op' => $nu ? now() : null,
            'gereed_door_id' => $nu ? $gebruiker->id : null,
            'opmerking' => $request->filled('opmerking') ? $request->string('opmerking')->toString() : $stand->opmerking,
        ]);

        // De afrondende stap rondt het traject af (of heropent het bij uitzetten).
        if ($stap->isAfrondend()) {
            $scriptie->update($nu
                ? ['status' => Scriptie::AFGEROND, 'afgerond_op' => now()]
                : ['status' => Scriptie::LOPEND, 'afgerond_op' => null]);
        }

        AuditLogger::log(AuditLogger::WIJZIGING, $scriptie, veld: 'scriptie_afvinken_'.$stap->value, context: [
            'gereed' => $nu,
        ]);

        return $this->terug($scriptie, $stap, $nu
            ? $stap->label().' afgevinkt.'
            : $stap->label().' heropend.');
    }

    /** De ja/nee-checklist van een stap beantwoorden. */
    public function checklist(Request $request, Scriptie $scriptie, Scriptiestap $stap): RedirectResponse
    {
        abort_unless($scriptie->magStapBewerken($request->user(), $stap), 403);

        $request->validate([
            'waarde' => ['array'],
            'waarde.*' => ['nullable', 'in:ja,nee'],
            'toelichting' => ['array'],
            'toelichting.*' => ['nullable', 'string', 'max:1000'],
        ]);

        $waarden = (array) $request->input('waarde', []);
        $toelichtingen = (array) $request->input('toelichting', []);

        $punten = $scriptie->checklistpunten()->where('stap', $stap->value)->get();
        foreach ($punten as $punt) {
            $keuze = $waarden[$punt->id] ?? null;
            $punt->update([
                'waarde' => $keuze === null ? null : ($keuze === 'ja'),
                'toelichting' => $toelichtingen[$punt->id] ?? null,
                'beoordelaar_id' => $keuze === null ? null : $request->user()->id,
                'beoordeeld_op' => $keuze === null ? null : now(),
            ]);
        }

        AuditLogger::log(AuditLogger::WIJZIGING, $scriptie, veld: 'scriptie_checklist_'.$stap->value);

        return $this->terug($scriptie, $stap, 'Checklist opgeslagen.');
    }

    /** De digitale goedkeuring van de scriptieovereenkomst (stap 5) vastleggen. */
    public function goedkeuring(Request $request, Scriptie $scriptie): RedirectResponse
    {
        abort_unless($scriptie->magStapBewerken($request->user(), Scriptiestap::Overeenkomst), 403);

        $data = $request->validate([
            'goedkeuring_student' => ['nullable', 'boolean'],
            'goedkeuring_begeleider' => ['nullable', 'boolean'],
            'goedkeuring_coordinator' => ['nullable', 'boolean'],
            'goedkeuring_directeur' => ['nullable', 'boolean'],
        ]);

        $wijziging = [];
        foreach (['student', 'begeleider', 'coordinator', 'directeur'] as $wie) {
            $veld = 'goedkeuring_'.$wie;
            $akkoord = (bool) ($data[$veld] ?? false);
            $wijziging[$veld] = $akkoord;
            // De datum wordt gezet bij een NIEUW akkoord en gewist bij intrekken.
            if ($akkoord && ! $scriptie->{$veld}) {
                $wijziging[$veld.'_op'] = now()->toDateString();
            } elseif (! $akkoord) {
                $wijziging[$veld.'_op'] = null;
            }
        }

        $scriptie->update($wijziging);
        AuditLogger::log(AuditLogger::WIJZIGING, $scriptie, veld: 'scriptie_goedkeuring');

        return $this->terug($scriptie, Scriptiestap::Overeenkomst, 'Goedkeuringen bijgewerkt.');
    }

    /**
     * De scriptieovereenkomst als ONDERTEKENDE PDF genereren (SHA-256 +
     * verificatiecode + waarmerk). Hergebruikt de bestaande ondertekenmodule.
     */
    public function overeenkomstGenereren(Request $request, Scriptie $scriptie): RedirectResponse
    {
        abort_unless($scriptie->magStapBewerken($request->user(), Scriptiestap::Overeenkomst), 403);

        $scriptie->load(['student', 'opleiding', 'begeleider']);

        $html = view('scriptie.pdf.overeenkomst', ['scriptie' => $scriptie])->render();

        $document = Documentondertekening::ondertekenHtml($html, [
            'type' => 'scriptieovereenkomst',
            'titel' => 'Scriptieovereenkomst '.$scriptie->scriptienummer,
            'student_id' => $scriptie->student_id,
            'ontvanger' => $scriptie->student?->volledigeNaam(),
            'uitgegeven_door_id' => $request->user()->id,
        ]);

        $scriptie->update(['overeenkomst_document_id' => $document->id]);

        AuditLogger::log(AuditLogger::UITGIFTE, $scriptie, veld: 'scriptie_overeenkomst', context: [
            'code' => $document->code,
        ]);

        return $this->terug($scriptie, Scriptiestap::Overeenkomst,
            'Ondertekende scriptieovereenkomst gegenereerd ('.$document->code.').');
    }

    /** De ondertekende scriptieovereenkomst (PDF) downloaden. */
    public function overeenkomstDownload(Request $request, Scriptie $scriptie): StreamedResponse
    {
        abort_unless($scriptie->zichtbaarVoor($request->user()), 403);

        $document = $scriptie->overeenkomstDocument;
        abort_if($document === null, 404, 'Nog geen ondertekende overeenkomst.');

        $bytes = Documentondertekening::pdfBytes($document);
        abort_if($bytes === null, 404);

        AuditLogger::log(AuditLogger::INZAGE, $scriptie, veld: 'scriptie_overeenkomst', context: [
            'code' => $document->code,
        ]);

        return response()->streamDownload(
            fn () => print($bytes),
            $document->bestandsnaam ?: ('scriptieovereenkomst-'.$scriptie->scriptienummer.'.pdf'),
            ['Content-Type' => 'application/pdf']
        );
    }

    /** Terug naar de trajectpagina met het juiste tabblad open. */
    private function terug(Scriptie $scriptie, Scriptiestap $stap, string $melding): RedirectResponse
    {
        return redirect()
            ->route('scriptie.show', ['scriptie' => $scriptie, 'tab' => $stap->value])
            ->with('status', $melding);
    }

    /**
     * De validatieregels van het formulier per stap. De sleutels zijn tevens de
     * velden die worden opgeslagen (whitelist). Stappen zonder eigen formulier
     * (Toelating) geven een lege lijst.
     *
     * @return array<string, array<int, string>>
     */
    private function veldRegels(Scriptiestap $stap): array
    {
        return match ($stap) {
            Scriptiestap::Toelating => [],
            Scriptiestap::Voorstel => [
                'titel_voorlopig' => ['nullable', 'string', 'max:255'],
                'taal' => ['nullable', 'string', 'max:40'],
                'voorstel_onderwerp_keuze' => ['nullable', 'string', 'max:255'],
                'voorstel_onderwerp_eigen' => ['nullable', 'string', 'max:5000'],
                'voorstel_omschrijving' => ['nullable', 'string', 'max:5000'],
                'voorstel_aanleiding' => ['nullable', 'string', 'max:5000'],
                'voorstel_probleemstelling' => ['nullable', 'string', 'max:5000'],
                'voorstel_hoofdvraag' => ['nullable', 'string', 'max:2000'],
                'voorstel_doelgroep' => ['nullable', 'string', 'max:255'],
                'voorstel_voorkeur_begeleider' => ['nullable', 'string', 'max:255'],
            ],
            Scriptiestap::Onderwerpbeoordeling => [
                'onderwerp_beoordeeld_op' => ['nullable', 'date'],
                'onderwerp_beoordelaar' => ['nullable', 'string', 'max:255'],
                'onderwerp_toelichting' => ['nullable', 'string', 'max:5000'],
                'onderwerp_vereiste_aanpassingen' => ['nullable', 'string', 'max:5000'],
                'onderwerp_herindiening_uiterlijk' => ['nullable', 'date'],
            ],
            Scriptiestap::Begeleider => [
                'begeleider_id' => ['nullable', 'integer', 'exists:docenten,id'],
                'begeleider_naam' => ['nullable', 'string', 'max:255'],
                'begeleider_email' => ['nullable', 'email', 'max:255'],
                'begeleider_expertise' => ['nullable', 'string', 'max:255'],
                'begeleider_toegewezen_op' => ['nullable', 'date'],
                'begeleiding_aantal_momenten' => ['nullable', 'integer', 'min:0', 'max:100'],
                'begeleiding_contactwijze' => ['nullable', 'string', 'max:255'],
                'begeleiding_spreekuren' => ['nullable', 'string', 'max:255'],
                'begeleiding_eerste_gesprek' => ['nullable', 'date'],
            ],
            Scriptiestap::Overeenkomst => [
                'titel_definitief' => ['nullable', 'string', 'max:255'],
                'taal' => ['nullable', 'string', 'max:40'],
                'overeenkomst_onderzoeksvraag' => ['nullable', 'string', 'max:2000'],
                'overeenkomst_commissieleden' => ['nullable', 'string', 'max:1000'],
                'overeenkomst_studielast' => ['nullable', 'string', 'max:100'],
                'overeenkomst_deadline_pva' => ['nullable', 'date'],
                'overeenkomst_startdatum' => ['nullable', 'date'],
                'overeenkomst_einddatum' => ['nullable', 'date'],
            ],
            Scriptiestap::PlanVanAanpak => [
                'pva_aanleiding' => ['nullable', 'string', 'max:10000'],
                'pva_probleembeschrijving' => ['nullable', 'string', 'max:10000'],
                'pva_toegevoegde_waarde' => ['nullable', 'string', 'max:10000'],
                'pva_maatschappelijke_relevantie' => ['nullable', 'string', 'max:10000'],
                'pva_wetenschappelijke_relevantie' => ['nullable', 'string', 'max:10000'],
                'pva_historische_context' => ['nullable', 'string', 'max:10000'],
                'pva_literatuuronderzoek' => ['nullable', 'string', 'max:10000'],
                'pva_doelgroep' => ['nullable', 'string', 'max:2000'],
                'pva_hoofdvraag' => ['nullable', 'string', 'max:2000'],
                'pva_deelvragen' => ['nullable', 'string', 'max:5000'],
                'pva_methode_verzameling' => ['nullable', 'string', 'max:10000'],
                'pva_methode_analyse' => ['nullable', 'string', 'max:10000'],
                'pva_planning' => ['nullable', 'string', 'max:10000'],
                'pva_risicos' => ['nullable', 'string', 'max:10000'],
                'pva_literatuurlijst' => ['nullable', 'string', 'max:10000'],
            ],
            Scriptiestap::Inlevering => [
                'definitief_ingeleverd_op' => ['nullable', 'date'],
            ],
            Scriptiestap::Plagiaat => [
                'plagiaat_datum' => ['nullable', 'date'],
                'plagiaat_versienummer' => ['nullable', 'string', 'max:40'],
                'plagiaat_similariteit' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'plagiaat_beoordeeld_door' => ['nullable', 'string', 'max:255'],
                'plagiaat_toelichting' => ['nullable', 'string', 'max:5000'],
                'plagiaat_vervolgstappen' => ['nullable', 'string', 'max:5000'],
            ],
            Scriptiestap::Beoordeling => [
                'beoordelaar_1' => ['nullable', 'string', 'max:255'],
                'beoordelaar_2' => ['nullable', 'string', 'max:255'],
                'beoordelaar_3' => ['nullable', 'string', 'max:255'],
                'beoordeling_datum' => ['nullable', 'date'],
                'voorlopig_cijfer' => ['nullable', 'numeric', 'min:1', 'max:10'],
                'definitief_cijfer' => ['nullable', 'numeric', 'min:1', 'max:10'],
                'beoordeling_motivering' => ['nullable', 'string', 'max:10000'],
                'beoordeling_eindbesluit' => ['nullable', 'string', 'max:255'],
            ],
            Scriptiestap::Verdediging => [
                'verdediging_datum' => ['nullable', 'date'],
                'verdediging_tijd' => ['nullable', 'date_format:H:i'],
                'verdediging_locatie' => ['nullable', 'string', 'max:255'],
                'verdediging_online_link' => ['nullable', 'string', 'max:500'],
                'verdediging_commissieleden' => ['nullable', 'string', 'max:1000'],
                'verdediging_duur_presentatie' => ['nullable', 'integer', 'min:0', 'max:600'],
                'verdediging_duur_vragen' => ['nullable', 'integer', 'min:0', 'max:600'],
                'verdediging_feedback' => ['nullable', 'string', 'max:5000'],
                'verdediging_eindbesluit' => ['nullable', 'string', 'max:255'],
            ],
            Scriptiestap::Afronding => [
                'gearchiveerd_op' => ['nullable', 'date'],
            ],
        };
    }

    /**
     * De vinkveld(en) van een stap: die worden expliciet uit de request genormaliseerd
     * (een niet-aangevinkt vakje stuurt niets mee).
     *
     * @return array<int, string>
     */
    private function booleanVelden(Scriptiestap $stap): array
    {
        return match ($stap) {
            Scriptiestap::Voorstel => ['voorstel_contact_begeleider'],
            Scriptiestap::Plagiaat => ['plagiaat_rapport_beschikbaar'],
            Scriptiestap::Beoordeling => ['kalibratie_afgerond'],
            default => [],
        };
    }
}
