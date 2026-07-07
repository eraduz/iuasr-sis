<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Audit-log (Beheerder). Alleen-lezen weergave van gevoelige acties: wie zag of
 * wijzigde welk record, wanneer en vanaf welk IP. De log kan niet worden
 * gewijzigd of verwijderd via de applicatie.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $actie = $request->query('actie');
        $rol = $request->query('rol');

        $events = AuditLog::query()
            ->with('user')
            ->when($actie, fn ($q) => $q->where('actie', $actie))
            ->when($rol, fn ($q) => $q->where('rol', $rol))
            ->orderByDesc('gelogd_op')
            ->paginate(25)
            ->withQueryString();

        return view('audit.index', compact('events', 'actie', 'rol'));
    }
}
