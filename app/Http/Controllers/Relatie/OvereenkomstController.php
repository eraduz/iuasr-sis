<?php

namespace App\Http\Controllers\Relatie;

use App\Enums\OvereenkomstStatus;
use App\Enums\OvereenkomstType;
use App\Http\Controllers\Controller;
use App\Models\Organisatie;
use App\Models\Overeenkomst;
use App\Support\AuditLogger;
use App\Support\Documentondertekening;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Overeenkomsten met een organisatie (samenwerkingsovereenkomst, convenant,
 * stagecontract). Een geüploade PDF wordt via de bestaande ondertekenmodule
 * gewaarmerkt (SHA-256 + verificatiecode) en gekoppeld. De verloopdatum stuurt de
 * signalering 'contracten die verlopen'. Muteren volgt de organisatie; gelogd.
 */
class OvereenkomstController extends Controller
{
    public function create(Request $request, Organisatie $organisatie): View
    {
        abort_unless($organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        return view('relaties.overeenkomst-form', $this->formData($organisatie, new Overeenkomst(['status' => OvereenkomstStatus::Concept->value])));
    }

    public function store(Request $request, Organisatie $organisatie): RedirectResponse
    {
        abort_unless($organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        $data = $this->valideer($request);
        $data = $this->verwerkOndertekening($request, $organisatie, $data);

        $overeenkomst = $organisatie->overeenkomsten()->create($data);
        AuditLogger::log(AuditLogger::AANMAAK, $overeenkomst, veld: 'overeenkomst', context: ['organisatie' => $organisatie->relatienummer, 'type' => $overeenkomst->type?->value]);

        return redirect()->route('relaties.show', $organisatie)->with('status', 'Overeenkomst toegevoegd.');
    }

    public function edit(Request $request, Overeenkomst $overeenkomst): View
    {
        abort_unless($overeenkomst->organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        return view('relaties.overeenkomst-form', $this->formData($overeenkomst->organisatie, $overeenkomst));
    }

    public function update(Request $request, Overeenkomst $overeenkomst): RedirectResponse
    {
        $organisatie = $overeenkomst->organisatie;
        abort_unless($organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        $data = $this->valideer($request);
        $data = $this->verwerkOndertekening($request, $organisatie, $data);

        $overeenkomst->update($data);
        AuditLogger::log(AuditLogger::WIJZIGING, $overeenkomst, veld: 'overeenkomst', context: ['organisatie' => $organisatie->relatienummer]);

        return redirect()->route('relaties.show', $organisatie)->with('status', 'Overeenkomst bijgewerkt.');
    }

    /** Download de gewaarmerkte (getekende) PDF van de overeenkomst. */
    public function download(Request $request, Overeenkomst $overeenkomst)
    {
        abort_unless($overeenkomst->organisatie->zichtbaarVoor($request->user()), 403, 'Deze overeenkomst valt buiten uw toegang.');
        abort_unless($overeenkomst->ondertekend_document_id !== null, 404, 'Er is geen getekend document.');

        $document = $overeenkomst->ondertekendDocument;
        $bytes = Documentondertekening::pdfBytes($document);
        abort_if($bytes === null, 404, 'Bestand niet gevonden.');

        AuditLogger::log(AuditLogger::INZAGE, $overeenkomst, veld: 'overeenkomst_document', context: ['code' => $document->code]);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$document->bestandsnaam.'"',
        ]);
    }

    public function destroy(Request $request, Overeenkomst $overeenkomst): RedirectResponse
    {
        $organisatie = $overeenkomst->organisatie;
        abort_unless($organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        $overeenkomst->delete();
        AuditLogger::log(AuditLogger::VERWIJDERING, $organisatie, veld: 'overeenkomst');

        return redirect()->route('relaties.show', $organisatie)->with('status', 'Overeenkomst verwijderd.');
    }

    private function formData(Organisatie $organisatie, Overeenkomst $overeenkomst): array
    {
        return [
            'organisatie' => $organisatie,
            'overeenkomst' => $overeenkomst,
            'types' => OvereenkomstType::opties(),
            'statussen' => OvereenkomstStatus::opties(),
        ];
    }

    /**
     * Waarmerkt een eventueel meegestuurde PDF via de ondertekenmodule en zet de
     * koppeling + status. Zonder bestand blijft de gekozen status staan.
     */
    private function verwerkOndertekening(Request $request, Organisatie $organisatie, array $data): array
    {
        if (! $request->hasFile('bestand')) {
            return $data;
        }

        $bestand = $request->file('bestand');
        $document = Documentondertekening::ondertekenUpload(
            file_get_contents($bestand->getRealPath()),
            $bestand->getClientOriginalName(),
            [
                'titel' => ($data['titel'] ?? OvereenkomstType::from($data['type'])->label()).' — '.$organisatie->naam,
                'ontvanger' => $organisatie->naam,
                'uitgegeven_door_id' => $request->user()->id,
            ]
        );

        $data['ondertekend_document_id'] = $document->id;
        $data['status'] = OvereenkomstStatus::Getekend->value;

        return $data;
    }

    private function valideer(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(OvereenkomstType::waarden())],
            'titel' => ['nullable', 'string', 'max:255'],
            'startdatum' => ['nullable', 'date'],
            'verloopdatum' => ['nullable', 'date', 'after_or_equal:startdatum'],
            'status' => ['required', Rule::in(OvereenkomstStatus::waarden())],
            'opmerking' => ['nullable', 'string', 'max:2000'],
            'bestand' => ['nullable', 'file', 'max:16384', 'mimes:pdf'],
        ]);
    }
}
