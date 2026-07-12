<!DOCTYPE html>
<html lang="nl">
@php
  $logoPad = public_path('assets/img/iuasr-logo.png');
  $logo = is_file($logoPad) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPad)) : null;
  $grens = 5.5;
  $ec = fn ($v) => rtrim(rtrim(number_format((float) $v, 1, ',', ''), '0'), ',');
@endphp
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 14mm 18mm; }
    body { font-family: "DejaVu Sans", sans-serif; color: #1E1446; font-size: 10pt; line-height: 1.4; }
    .head { width: 100%; border-bottom: 2px solid #1E1446; padding-bottom: 8px; margin-bottom: 14px; }
    .head td { vertical-align: middle; }
    .head .org { text-align: right; font-size: 8.5pt; color: #666; line-height: 1.45; }
    h1 { font-size: 18pt; font-weight: bold; margin: 0 0 3px; color: #1E1446; }
    .sub { font-size: 9pt; color: #666; margin: 0 0 10px; }
    .banner { background: #f3f1e9; border: 1px solid #D69A2D; color: #7a5a12; font-size: 8.5pt;
              padding: 6px 9px; border-radius: 3px; margin: 0 0 14px; }
    table.kv { width: 100%; border-collapse: collapse; margin: 6px 0 14px; font-size: 9.5pt; }
    table.kv td { padding: 2px 0; }
    table.kv td.k { color: #666; width: 150px; }
    table.kv td.v { color: #1E1446; font-weight: bold; }
    .jaar { font-size: 10.5pt; font-weight: bold; color: #1E1446; margin: 14px 0 5px; }
    .jaar span { font-weight: normal; color: #666; font-size: 8.5pt; }
    table.cijfers { width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 4px; }
    table.cijfers th { text-align: left; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.04em; color: #1E1446; border-bottom: 1.5px solid #1E1446; padding: 5px 6px; }
    table.cijfers td { padding: 4px 6px; border-bottom: 1px solid #eee; }
    table.cijfers td.r { text-align: right; }
    table.cijfers td.c { text-align: center; }
    .fail { color: #C8102E; font-weight: bold; }
    .pass { color: #285C4D; font-weight: bold; }
    .totaal { margin-top: 10px; font-size: 10pt; font-weight: bold; }
    .foot { width: 100%; margin-top: 22px; padding-top: 8px; border-top: 1px solid #ddd; font-size: 8pt; color: #666; }
    .foot td.wm { text-align: right; text-transform: uppercase; letter-spacing: 2px; color: #999; font-weight: bold; }
  </style>
</head>
<body>
  <table class="head">
    <tr>
      <td>@if ($logo)<img src="{{ $logo }}" alt="IUASR" style="height:96px;">@else<b>IUASR</b>@endif</td>
      <td class="org">Bergsingel 135 &middot; 3037 GC Rotterdam<br>Tel: +31 (0)10 485 47 21<br>szaken@iuasr.nl<br>info@iuasr.nl</td>
    </tr>
  </table>

  <h1>Historische cijferlijst</h1>
  <p class="sub">Gemigreerd overzicht uit het oude studentsysteem &middot; Rotterdam, {{ now()->format('d-m-Y') }}</p>

  <div class="banner">
    <b>Informatief overzicht &mdash; geen officieel gewaarmerkt document.</b>
    Deze cijfers zijn overgezet uit het oude Access-systeem (t/m studiejaar 2024&ndash;2025). De oorspronkelijke schaal 0&ndash;100 is omgezet naar 0&ndash;10 (voldoende vanaf {{ number_format($grens, 1, ',', '') }}). Het oude systeem kende geen deeltoetsen; per vak geldt één eindcijfer.
  </div>

  <table class="kv">
    <tr><td class="k">Naam</td><td class="v">{{ $student->volledigeNaam() }}</td></tr>
    <tr><td class="k">Studentnummer</td><td class="v">{{ $student->studentnummer }}</td></tr>
    <tr><td class="k">Geboortedatum</td><td class="v">{{ $student->geboortedatum?->format('d-m-Y') ?? '—' }}</td></tr>
    <tr><td class="k">Opleiding</td><td class="v">{{ $opleiding->naam }}</td></tr>
    <tr><td class="k">Behaalde EC (voldoende)</td><td class="v">{{ $ec($totaalEcBehaald) }}</td></tr>
  </table>

  @foreach ($jaren as $jaar => $data)
    <div class="jaar">Studiejaar {{ $jaar }}
      <span>({{ $data['rijen']->count() }} {{ $data['rijen']->count() === 1 ? 'vak' : 'vakken' }} &middot; EC behaald {{ $ec($data['ec_behaald']) }}/{{ $ec($data['ec_totaal']) }}@if ($data['gemiddelde'] !== null) &middot; gemiddelde {{ number_format($data['gemiddelde'], 1, ',', '') }}@endif)</span>
    </div>
    <table class="cijfers">
      <thead><tr><th>Code</th><th>Vak</th><th style="text-align:right;">Cijfer</th><th style="text-align:center;">EC</th><th>Resultaat</th></tr></thead>
      <tbody>
        @foreach ($data['rijen'] as $r)
          @php $vak = $r->toetsonderdeel->vak; @endphp
          <tr>
            <td>{{ $vak?->code ?? '—' }}</td>
            <td>{{ $vak?->naam ?? '—' }}</td>
            <td class="r">
              @if ($r->vrijstelling)VR
              @elseif ($r->cijfer !== null)<span class="{{ $r->cijfer < $grens ? 'fail' : 'pass' }}">{{ number_format($r->cijfer, 1, ',', '') }}</span>
              @else —@endif
            </td>
            <td class="c">{{ $r->voldoende ? $ec($vak?->ec ?? 0) : '—' }}</td>
            <td>@if ($r->vrijstelling)Vrijstelling @elseif ($r->voldoende)Voldoende @else Onvoldoende @endif</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endforeach

  <p class="totaal">Totaal behaalde EC (voldoende): {{ $ec($totaalEcBehaald) }}</p>

  <table class="foot">
    <tr>
      <td>Afgedrukt door {{ $uitgegevenDoor ?? 'IUASR' }} op {{ now()->format('d-m-Y H:i') }}. Bron: migratie oude Access-database. Voldoende-grens: {{ number_format($grens, 1, ',', '') }}.</td>
      <td class="wm">Informatief &middot; niet gewaarmerkt</td>
    </tr>
  </table>
</body>
</html>
