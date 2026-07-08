<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Support\AuditLogger;
use App\Support\Backup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Recovery-backup (Beheerder): downloadt een met wachtwoord versleutelde ZIP
 * met database, applicatie en geüploade bestanden. Het wachtwoord wordt bij het
 * maken opgegeven en NERGENS opgeslagen.
 */
class BackupController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->rol === Rol::Beheerder, 403);

        return view('backup.index');
    }

    public function download(Request $request): BinaryFileResponse
    {
        abort_unless(auth()->user()->rol === Rol::Beheerder, 403);

        $data = $request->validate([
            'wachtwoord' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'wachtwoord.confirmed' => 'De twee wachtwoorden komen niet overeen.',
            'wachtwoord.min' => 'Gebruik een wachtwoord van minimaal 8 tekens.',
        ]);

        $pad = Backup::maak($data['wachtwoord']);

        AuditLogger::log(AuditLogger::UITGIFTE, 'Backup', veld: 'recovery-backup', context: [
            'grootte_bytes' => filesize($pad) ?: 0,
        ]);

        $bestandsnaam = 'iuasr-sis-backup-'.now()->format('Ymd-Hi').'.zip';

        return response()
            ->download($pad, $bestandsnaam, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }
}
