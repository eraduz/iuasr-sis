<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Audit-log (Beheerder). Alleen-lezen weergave van gevoelige acties: wie zag of
 * wijzigde welk record, wanneer en vanaf welk IP. De log kan niet worden
 * gewijzigd of verwijderd via de applicatie.
 */
class AuditLogController extends Controller
{
    /**
     * Filters die meerdere acties tegelijk tonen. 'noodtoegang' bundelt de
     * geslaagde én mislukte noodlogins: die twee apart bekijken is precies wat
     * je NIET wilt — een reeks mislukte pogingen gevolgd door een geslaagde is
     * het patroon waar je op let. Omdat er bewust geen accountblokkade is bij de
     * noodtoegang, is logreview daar de enige detectie.
     */
    private const GROEPEN = [
        'noodtoegang' => [AuditLogger::NOODLOGIN, AuditLogger::NOODLOGIN_MISLUKT],
    ];

    public function index(Request $request): View
    {
        $actie = $request->query('actie');
        $rol = $request->query('rol');

        $events = AuditLog::query()
            ->with('user')
            ->when($actie, fn ($q) => isset(self::GROEPEN[$actie])
                ? $q->whereIn('actie', self::GROEPEN[$actie])
                : $q->where('actie', $actie))
            ->when($rol, fn ($q) => $q->where('rol', $rol))
            ->orderByDesc('gelogd_op')
            ->paginate(25)
            ->withQueryString();

        return view('audit.index', compact('events', 'actie', 'rol'));
    }
}
