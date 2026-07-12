<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Support\Handleiding;
use Symfony\Component\HttpFoundation\Response;

/**
 * Levert de twee PDF-handleidingen. De medewerkershandleiding is voor iedereen
 * die is ingelogd; de technische handleiding (data-recovery/herstel) is
 * voorbehouden aan de Beheerder.
 */
class HandleidingController extends Controller
{
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
}
