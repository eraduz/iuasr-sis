<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Support\Handleiding;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Levert de twee PDF-handleidingen. De medewerkershandleiding is voor iedereen
 * die is ingelogd; de technische handleiding (data-recovery/herstel) is
 * voorbehouden aan de Beheerder.
 */
class HandleidingController extends Controller
{
    /**
     * HTML-versie van de medewerkershandleiding met een hoofdstuk-navigatie aan de
     * linkerkant, zodat een gebruiker snel de hulp voor de eigen afdeling vindt.
     * De hoofdstukken van de eigen rol(len) krijgen een 'voor u'-markering. De PDF
     * blijft downloadbaar.
     */
    public function web(): View
    {
        return view('handleiding-web', [
            'hoofdstukken' => $this->hoofdstukken(),
            'mijnRollen' => auth()->user()->rolSleutels(),
        ]);
    }

    public function medewerkers(): Response
    {
        return $this->toon(Handleiding::MEDEWERKERS, 'IUASR-Management-Systeem-Handleiding-Medewerkers.pdf');
    }

    public function technisch(): Response
    {
        abort_unless(in_array(auth()->user()->rol, [Rol::Beheerder, Rol::Bestuur], true), 403);

        return $this->toon(Handleiding::TECHNISCH, 'IUASR-Management-Systeem-Technische-Handleiding.pdf');
    }

    private function toon(string $view, string $bestandsnaam): Response
    {
        return response(Handleiding::pdf($view), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$bestandsnaam.'"',
        ]);
    }

    /**
     * De hoofdstukken met per hoofdstuk de rol(len) waarvoor het vooral bedoeld
     * is — dit stuurt de 'voor u'-markering in de navigatie. Een lege rollenlijst
     * is algemene stof voor iedereen.
     *
     * LET OP: deze lijst moet gelijk lopen met de koppen in
     * `partials/handleiding-inhoud` (nummer én ankervolgorde). Voegt u daar een
     * hoofdstuk toe, werk het hier bij — anders wijst de navigatie mis.
     *
     * @return array<int, array{nr:int, titel:string, rollen:array<int,string>}>
     */
    private function hoofdstukken(): array
    {
        return [
            ['nr' => 1, 'titel' => 'Waarvoor is dit systeem?', 'rollen' => []],
            ['nr' => 2, 'titel' => 'Inloggen & een module kiezen', 'rollen' => []],
            ['nr' => 3, 'titel' => 'Rollen: wie mag wat', 'rollen' => []],
            ['nr' => 4, 'titel' => 'Uw dashboard', 'rollen' => []],
            ['nr' => 5, 'titel' => 'Werken met studenten', 'rollen' => ['studentenzaken']],
            ['nr' => 6, 'titel' => 'Cijfers en aanwezigheid', 'rollen' => ['docent']],
            ['nr' => 7, 'titel' => 'Cijfers vaststellen & lijsten', 'rollen' => ['examencommissie', 'directie']],
            ['nr' => 8, 'titel' => 'Collegegeld & betalingen', 'rollen' => ['financien', 'studentenzaken']],
            ['nr' => 9, 'titel' => 'Verklaringen & ondertekende documenten', 'rollen' => ['studentenzaken']],
            ['nr' => 10, 'titel' => 'Rapporten', 'rollen' => ['directie', 'examencommissie', 'bestuur', 'studentenzaken']],
            ['nr' => 11, 'titel' => 'Cursussen Administratie', 'rollen' => ['cursusadministratie']],
            ['nr' => 12, 'titel' => 'Relatiebeheer & Stage', 'rollen' => ['relatiebeheerder', 'stagecoordinator']],
            ['nr' => 13, 'titel' => 'HR / Personeelszaken', 'rollen' => ['hrmedewerker']],
            ['nr' => 14, 'titel' => 'Balie / Receptie', 'rollen' => ['balie']],
            ['nr' => 15, 'titel' => 'Bibliotheek', 'rollen' => ['bibliotheek']],
            ['nr' => 16, 'titel' => 'Scriptie Coördinatie', 'rollen' => ['scriptiecoordinator']],
            ['nr' => 17, 'titel' => 'Stichtingsbestuur', 'rollen' => ['stichtingsbestuur']],
            ['nr' => 18, 'titel' => 'Noodtoegang', 'rollen' => ['beheerder']],
            ['nr' => 19, 'titel' => 'Vragen of problemen?', 'rollen' => []],
        ];
    }
}
