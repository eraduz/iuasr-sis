<!DOCTYPE html>
<html lang="nl">
@php
    $logoPad = public_path('assets/img/iuasr-logo.png');
    $logo = is_file($logoPad) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPad)) : null;
    $d = fn ($datum) => $datum ? $datum->format('d-m-Y') : '—';
@endphp
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14mm 18mm; }
        body { font-family: "DejaVu Sans", sans-serif; color: #1E1446; font-size: 10.5pt; line-height: 1.4; }
        .head { width: 100%; border-bottom: 2px solid #1E1446; padding-bottom: 8px; margin-bottom: 14px; }
        .head td { vertical-align: middle; }
        .head .org { text-align: right; font-size: 8.5pt; color: #666; line-height: 1.45; }
        h1 { font-size: 19pt; font-weight: bold; margin: 0 0 3px; color: #1E1446; }
        h2 { font-size: 12pt; margin: 14px 0 4px; color: #1E1446; }
        .doc-sub { font-size: 9.5pt; color: #666; margin: 0 0 12px; }
        p { margin: 0 0 7px; }
        table.kv { width: 100%; border-collapse: collapse; margin: 6px 0 10px; font-size: 10pt; }
        table.kv td { padding: 3px 0; vertical-align: top; }
        table.kv td.k { color: #666; width: 210px; }
        table.kv td.v { color: #1E1446; font-weight: bold; }
        .ja { color: #285C4D; font-weight: bold; }
        .nee { color: #C8102E; font-weight: bold; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>@if ($logo)<img src="{{ $logo }}" alt="IUASR" style="height:108px;">@else<b style="font-size:14pt;">IUASR</b>@endif</td>
            <td class="org">
                Bergsingel 135 &middot; 3037 GC Rotterdam<br>
                Tel: +31 (0)10 485 47 21<br>
                szaken@iuasr.nl<br>
                info@iuasr.nl
            </td>
        </tr>
    </table>

    <h1>Scriptieovereenkomst</h1>
    <p class="doc-sub">{{ $scriptie->scriptienummer }} &middot; {{ $scriptie->opleiding?->naam }}</p>

    <p>Deze overeenkomst legt de afspraken vast tussen de student, de scriptiebegeleider en de opleiding over het uit te voeren scriptieonderzoek.</p>

    <h2>Student en begeleiding</h2>
    <table class="kv">
        <tr><td class="k">Naam van de student</td><td class="v">{{ $scriptie->student?->volledigeNaam() }}</td></tr>
        <tr><td class="k">Studentnummer</td><td class="v">{{ $scriptie->student?->studentnummer }}</td></tr>
        <tr><td class="k">Scriptiebegeleider</td><td class="v">{{ $scriptie->begeleider?->volledigeNaam() ?: ($scriptie->begeleider_naam ?: '—') }}</td></tr>
        <tr><td class="k">Leden scriptiecommissie</td><td class="v">{{ $scriptie->overeenkomst_commissieleden ?: '—' }}</td></tr>
    </table>

    <h2>Onderwerp</h2>
    <table class="kv">
        <tr><td class="k">Titel</td><td class="v">{{ $scriptie->titel_definitief ?: ($scriptie->titel_voorlopig ?: '—') }}</td></tr>
        <tr><td class="k">Onderzoeksvraag</td><td class="v">{{ $scriptie->overeenkomst_onderzoeksvraag ?: '—' }}</td></tr>
        <tr><td class="k">Taal van de scriptie</td><td class="v">{{ $scriptie->taal ?: '—' }}</td></tr>
        <tr><td class="k">Studielast</td><td class="v">{{ $scriptie->overeenkomst_studielast ?: '—' }}</td></tr>
    </table>

    <h2>Planning</h2>
    <table class="kv">
        <tr><td class="k">Deadline Plan van Aanpak</td><td class="v">{{ $d($scriptie->overeenkomst_deadline_pva) }}</td></tr>
        <tr><td class="k">Startdatum</td><td class="v">{{ $d($scriptie->overeenkomst_startdatum) }}</td></tr>
        <tr><td class="k">Verwachte einddatum</td><td class="v">{{ $d($scriptie->overeenkomst_einddatum) }}</td></tr>
    </table>

    <h2>Digitale goedkeuring</h2>
    <table class="kv">
        <tr><td class="k">Student</td><td class="v">{!! $scriptie->goedkeuring_student ? '<span class="ja">Akkoord</span>' : '<span class="nee">Nog niet</span>' !!} ({{ $d($scriptie->goedkeuring_student_op) }})</td></tr>
        <tr><td class="k">Scriptiebegeleider</td><td class="v">{!! $scriptie->goedkeuring_begeleider ? '<span class="ja">Akkoord</span>' : '<span class="nee">Nog niet</span>' !!} ({{ $d($scriptie->goedkeuring_begeleider_op) }})</td></tr>
        <tr><td class="k">Scriptiecoördinator</td><td class="v">{!! $scriptie->goedkeuring_coordinator ? '<span class="ja">Akkoord</span>' : '<span class="nee">Nog niet</span>' !!} ({{ $d($scriptie->goedkeuring_coordinator_op) }})</td></tr>
        <tr><td class="k">Opleidingsdirecteur</td><td class="v">{!! $scriptie->goedkeuring_directeur ? '<span class="ja">Akkoord</span>' : '<span class="nee">Nog niet</span>' !!} ({{ $d($scriptie->goedkeuring_directeur_op) }})</td></tr>
    </table>
</body>
</html>
