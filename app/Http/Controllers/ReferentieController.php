<?php

namespace App\Http\Controllers;

use App\Models\Docent;
use App\Models\Faculteit;
use App\Models\Kennistoets;
use App\Models\Klas;
use App\Models\Land;
use App\Models\ContactmomentType;
use App\Models\Nationaliteit;
use App\Models\Opleiding;
use App\Models\OrganisatieType;
use App\Models\Periode;
use App\Models\Vak;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Beheer van de opzoektabellen (referentiedata). Eén generieke controller die
 * meerdere tabellen bedient via een registry — vroeger losse Access-tabellen,
 * nu centraal en met echte relaties. Alleen Beheerder.
 */
class ReferentieController extends Controller
{
    /** Registry: per tabel het model, de lijstkolommen en de formuliervelden. */
    private function registry(): array
    {
        return [
            'opleidingen' => [
                'model' => Opleiding::class, 'enkel' => 'Opleiding', 'meer' => 'Opleidingen',
                'kolommen' => [
                    'Code' => fn ($m) => $m->code,
                    'Naam' => fn ($m) => $m->naam,
                    'Soort' => fn ($m) => ucfirst((string) $m->soort),
                    'EC' => fn ($m) => $m->ec_totaal ?? '—',
                    'Actief' => fn ($m) => $m->actief ? 'Ja' : 'Nee',
                ],
                'velden' => [
                    'code' => ['label' => 'Code', 'type' => 'text', 'rules' => 'required|string|max:20'],
                    'naam' => ['label' => 'Naam', 'type' => 'text', 'rules' => 'required|string|max:255'],
                    'faculteit_id' => ['label' => 'Faculteit', 'type' => 'belongsto', 'model' => Faculteit::class, 'toon' => 'naam', 'rules' => 'required|exists:faculteiten,id'],
                    'soort' => ['label' => 'Soort', 'type' => 'select', 'opties' => ['bachelor', 'master', 'premaster', 'cursus', 'toelating'], 'rules' => 'required|string'],
                    'nominale_jaren' => ['label' => 'Nominale jaren', 'type' => 'number', 'rules' => 'nullable|integer|min:1|max:10'],
                    'ec_totaal' => ['label' => 'EC totaal', 'type' => 'number', 'rules' => 'nullable|integer|min:0'],
                    'voldoende_grens' => ['label' => 'Voldoende-grens', 'type' => 'text', 'rules' => 'nullable|numeric|between:1,10', 'hint' => 'TE BEVESTIGEN per opleiding'],
                    'ec_overgang_drempel' => ['label' => 'EC-drempel overgang', 'type' => 'number', 'rules' => 'nullable|integer|min:0', 'hint' => 'TE BEVESTIGEN per opleiding'],
                    'actief' => ['label' => 'Actief', 'type' => 'checkbox'],
                ],
            ],
            'vakken' => [
                'model' => Vak::class, 'enkel' => 'Vak', 'meer' => 'Vakken',
                'kolommen' => [
                    'Code' => fn ($m) => $m->code,
                    'Naam' => fn ($m) => $m->naam,
                    'Opleiding' => fn ($m) => $m->opleiding?->code ?? '—',
                    'EC' => fn ($m) => $m->ec,
                    'Docent' => fn ($m) => $m->docent?->achternaam ?? '—',
                ],
                'velden' => [
                    'code' => ['label' => 'Code', 'type' => 'text', 'rules' => 'required|string|max:40'],
                    'naam' => ['label' => 'Naam', 'type' => 'text', 'rules' => 'required|string|max:255'],
                    'opleiding_id' => ['label' => 'Opleiding', 'type' => 'belongsto', 'model' => Opleiding::class, 'toon' => 'naam', 'rules' => 'required|exists:opleidingen,id'],
                    'docent_id' => ['label' => 'Docent', 'type' => 'belongsto', 'model' => Docent::class, 'toon' => 'achternaam', 'rules' => 'nullable|exists:docenten,id', 'leeg' => '— geen —'],
                    'ec' => ['label' => 'EC', 'type' => 'number', 'rules' => 'required|numeric|min:0'],
                    'leerjaar' => ['label' => 'Leerjaar', 'type' => 'number', 'rules' => 'nullable|integer|min:1|max:10'],
                    'blok' => ['label' => 'Blok', 'type' => 'number', 'rules' => 'nullable|integer|min:1|max:4'],
                    'actief' => ['label' => 'Actief', 'type' => 'checkbox'],
                ],
            ],
            'kennistoetsen' => [
                'model' => Kennistoets::class, 'enkel' => 'Kennistoets', 'meer' => 'Landelijke kennistoetsen',
                'kolommen' => [
                    'Code' => fn ($m) => $m->code,
                    'Naam' => fn ($m) => $m->naam,
                    'Opleiding' => fn ($m) => $m->opleiding?->code ?? '—',
                    'Volgorde' => fn ($m) => $m->volgorde,
                    'Actief' => fn ($m) => $m->actief ? 'Ja' : 'Nee',
                ],
                'velden' => [
                    'opleiding_id' => ['label' => 'Opleiding', 'type' => 'belongsto', 'model' => Opleiding::class, 'toon' => 'naam', 'rules' => 'required|exists:opleidingen,id'],
                    'code' => ['label' => 'Code', 'type' => 'text', 'rules' => 'required|string|max:20', 'hint' => 'bv. RWT of LKT-TAAL'],
                    'naam' => ['label' => 'Naam', 'type' => 'text', 'rules' => 'required|string|max:120'],
                    'volgorde' => ['label' => 'Volgorde', 'type' => 'number', 'rules' => 'nullable|integer|min:0|max:99', 'hint' => 'volgorde op het dossier'],
                    'actief' => ['label' => 'Actief', 'type' => 'checkbox'],
                ],
            ],
            'perioden' => [
                'model' => Periode::class, 'enkel' => 'Studiejaar', 'meer' => 'Studiejaren',
                'kolommen' => [
                    'Code' => fn ($m) => $m->code,
                    'Naam' => fn ($m) => $m->naam,
                    'Start' => fn ($m) => $m->startdatum?->format('d-m-Y') ?? '—',
                    'Einde' => fn ($m) => $m->einddatum?->format('d-m-Y') ?? '—',
                    'Huidig' => fn ($m) => $m->actief ? 'Ja' : 'Nee',
                ],
                'velden' => [
                    'code' => ['label' => 'Code', 'type' => 'text', 'rules' => 'required|string|max:20', 'hint' => 'bv. 2026-2027'],
                    'naam' => ['label' => 'Naam', 'type' => 'text', 'rules' => 'required|string|max:255', 'hint' => 'bv. Studiejaar 2026 / 2027'],
                    'startdatum' => ['label' => 'Startdatum', 'type' => 'date', 'rules' => 'nullable|date', 'hint' => '1 september'],
                    'einddatum' => ['label' => 'Einddatum', 'type' => 'date', 'rules' => 'nullable|date|after_or_equal:startdatum', 'hint' => '31 juli'],
                    'actief' => ['label' => 'Huidig studiejaar', 'type' => 'checkbox', 'hint' => 'Aanvinken activeert dit jaar en deactiveert automatisch het vorige.'],
                ],
            ],
            'klassen' => [
                'model' => Klas::class, 'enkel' => 'Klas', 'meer' => 'Klassen',
                'kolommen' => [
                    'Code' => fn ($m) => $m->code,
                    'Opleiding' => fn ($m) => $m->opleiding?->code ?? '—',
                    'Leerjaar' => fn ($m) => $m->leerjaar,
                    'Groep' => fn ($m) => $m->groep,
                ],
                'velden' => [
                    'code' => ['label' => 'Code', 'type' => 'text', 'rules' => 'required|string|max:40', 'hint' => 'bv. IT-1'],
                    'naam' => ['label' => 'Naam', 'type' => 'text', 'rules' => 'nullable|string|max:255'],
                    'opleiding_id' => ['label' => 'Opleiding', 'type' => 'belongsto', 'model' => Opleiding::class, 'toon' => 'naam', 'rules' => 'required|exists:opleidingen,id'],
                    'leerjaar' => ['label' => 'Leerjaar', 'type' => 'number', 'rules' => 'required|integer|min:1|max:10'],
                    'groep' => ['label' => 'Groep', 'type' => 'select', 'opties' => ['dag', 'avond', 'deeltijd'], 'rules' => 'required|string'],
                ],
            ],
            'docenten' => [
                'model' => Docent::class, 'enkel' => 'Docent', 'meer' => 'Docenten',
                'kolommen' => [
                    'Code' => fn ($m) => $m->code,
                    'Naam' => fn ($m) => $m->volledigeNaam(),
                    'E-mail' => fn ($m) => $m->email ?? '—',
                    'Actief' => fn ($m) => $m->actief ? 'Ja' : 'Nee',
                ],
                'velden' => [
                    'code' => ['label' => 'Docentcode', 'type' => 'text', 'rules' => 'required|string|max:40'],
                    'aanhef' => ['label' => 'Aanhef', 'type' => 'text', 'rules' => 'nullable|string|max:20'],
                    'voornaam' => ['label' => 'Voornaam', 'type' => 'text', 'rules' => 'nullable|string|max:255'],
                    'achternaam' => ['label' => 'Achternaam', 'type' => 'text', 'rules' => 'required|string|max:255'],
                    'email' => ['label' => 'E-mail', 'type' => 'text', 'rules' => 'nullable|email|max:255'],
                    'actief' => ['label' => 'Actief', 'type' => 'checkbox'],
                ],
            ],
            'landen' => [
                'model' => Land::class, 'enkel' => 'Land', 'meer' => 'Landen',
                'kolommen' => ['Code' => fn ($m) => $m->code, 'Naam' => fn ($m) => $m->naam],
                'velden' => [
                    'code' => ['label' => 'ISO-code', 'type' => 'text', 'rules' => 'required|string|max:3'],
                    'naam' => ['label' => 'Naam', 'type' => 'text', 'rules' => 'required|string|max:255'],
                ],
            ],
            'nationaliteiten' => [
                'model' => Nationaliteit::class, 'enkel' => 'Nationaliteit', 'meer' => 'Nationaliteiten',
                'kolommen' => ['Naam' => fn ($m) => $m->naam],
                'velden' => [
                    'naam' => ['label' => 'Naam', 'type' => 'text', 'rules' => 'required|string|max:255'],
                ],
            ],
            'organisatietypes' => [
                'model' => OrganisatieType::class, 'enkel' => 'Organisatietype', 'meer' => 'Organisatietypes',
                'kolommen' => [
                    'Code' => fn ($m) => $m->code,
                    'Naam' => fn ($m) => $m->naam,
                    'Opleiding' => fn ($m) => $m->opleiding?->code ?? 'Alle',
                    'Actief' => fn ($m) => $m->actief ? 'Ja' : 'Nee',
                ],
                'velden' => [
                    'code' => ['label' => 'Code', 'type' => 'text', 'rules' => 'required|string|max:40', 'hint' => 'bv. BASISSCHOOL of ZORGINSTELLING'],
                    'naam' => ['label' => 'Naam', 'type' => 'text', 'rules' => 'required|string|max:255'],
                    'opleiding_id' => ['label' => 'Opleiding', 'type' => 'belongsto', 'model' => Opleiding::class, 'toon' => 'naam', 'rules' => 'nullable|exists:opleidingen,id', 'leeg' => '— alle opleidingen —', 'hint' => 'Leeg = geldt voor alle opleidingen'],
                    'actief' => ['label' => 'Actief', 'type' => 'checkbox'],
                ],
            ],
            'contactmomenttypes' => [
                'model' => ContactmomentType::class, 'enkel' => 'Contactmoment-type', 'meer' => 'Contactmoment-types',
                'kolommen' => [
                    'Code' => fn ($m) => $m->code,
                    'Naam' => fn ($m) => $m->naam,
                    'Volgorde' => fn ($m) => $m->volgorde,
                    'Actief' => fn ($m) => $m->actief ? 'Ja' : 'Nee',
                ],
                'velden' => [
                    'code' => ['label' => 'Code', 'type' => 'text', 'rules' => 'required|string|max:40', 'hint' => 'bv. TELEFOON of STAGEBEZOEK'],
                    'naam' => ['label' => 'Naam', 'type' => 'text', 'rules' => 'required|string|max:255'],
                    'volgorde' => ['label' => 'Volgorde', 'type' => 'number', 'rules' => 'nullable|integer|min:0|max:99'],
                    'actief' => ['label' => 'Actief', 'type' => 'checkbox'],
                ],
            ],
        ];
    }

    private function config(string $tabel): array
    {
        $reg = $this->registry();
        abort_unless(isset($reg[$tabel]), 404, 'Onbekende tabel.');

        return $reg[$tabel] + ['sleutel' => $tabel];
    }

    public function index(?string $tabel = null): View
    {
        $reg = $this->registry();
        $tabel ??= array_key_first($reg);
        $conf = $this->config($tabel);

        $tabbladen = [];
        foreach ($reg as $sleutel => $c) {
            $tabbladen[$sleutel] = ['label' => $c['meer'], 'aantal' => $c['model']::count()];
        }

        $rijen = $conf['model']::query()->orderBy(array_key_first($conf['velden']))->get();

        return view('referentie.index', compact('tabel', 'conf', 'tabbladen', 'rijen'));
    }

    public function create(string $tabel): View
    {
        $conf = $this->config($tabel);

        return view('referentie.form', ['conf' => $conf, 'tabel' => $tabel, 'rij' => null]);
    }

    public function store(Request $request, string $tabel): RedirectResponse
    {
        $conf = $this->config($tabel);
        $data = $this->valideer($request, $conf);

        $conf['model']::create($data);

        return redirect()->route('opzoektabellen.tabel', $tabel)
            ->with('status', $conf['enkel'].' toegevoegd.');
    }

    public function edit(string $tabel, int $id): View
    {
        $conf = $this->config($tabel);
        $rij = $conf['model']::findOrFail($id);

        return view('referentie.form', ['conf' => $conf, 'tabel' => $tabel, 'rij' => $rij]);
    }

    public function update(Request $request, string $tabel, int $id): RedirectResponse
    {
        $conf = $this->config($tabel);
        $rij = $conf['model']::findOrFail($id);
        $rij->update($this->valideer($request, $conf));

        return redirect()->route('opzoektabellen.tabel', $tabel)
            ->with('status', $conf['enkel'].' bijgewerkt.');
    }

    public function destroy(string $tabel, int $id): RedirectResponse
    {
        $conf = $this->config($tabel);
        $rij = $conf['model']::findOrFail($id);

        try {
            $rij->delete();
        } catch (QueryException) {
            return redirect()->route('opzoektabellen.tabel', $tabel)
                ->with('status', 'Kan niet verwijderen: er zijn nog gekoppelde records.');
        }

        return redirect()->route('opzoektabellen.tabel', $tabel)
            ->with('status', $conf['enkel'].' verwijderd.');
    }

    /** Valideert en normaliseert de invoer op basis van de veldregistratie. */
    private function valideer(Request $request, array $conf): array
    {
        $regels = [];
        foreach ($conf['velden'] as $naam => $veld) {
            if (($veld['type'] ?? '') !== 'checkbox') {
                $regels[$naam] = $veld['rules'] ?? 'nullable';
            }
        }
        $data = $request->validate($regels);

        // Checkbox-velden: aan/afwezig => boolean.
        foreach ($conf['velden'] as $naam => $veld) {
            if (($veld['type'] ?? '') === 'checkbox') {
                $data[$naam] = $request->boolean($naam);
            }
        }

        return $data;
    }
}
