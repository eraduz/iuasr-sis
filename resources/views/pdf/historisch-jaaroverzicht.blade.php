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
    @page { margin: 12mm 16mm; }
    body { font-family: "DejaVu Sans", sans-serif; color: #1E1446; font-size: 9pt; line-height: 1.3; }
    .head { border-bottom: 2px solid #1E1446; padding-bottom: 6px; margin-bottom: 8px; }
    h1 { font-size: 15pt; font-weight: bold; margin: 0 0 2px; color: #1E1446; }
    .sub { font-size: 8.5pt; color: #666; margin: 0 0 8px; }
    .banner { background: #f3f1e9; border: 1px solid #D69A2D; color: #7a5a12; font-size: 8pt;
              padding: 5px 8px; margin: 0 0 10px; }
    table.cijfers { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
    table.cijfers th { text-align: left; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.03em; color: #555; border-bottom: 1px solid #999; padding: 3px 5px; }
    table.cijfers td { padding: 2.5px 5px; border-bottom: 1px solid #f0f0f0; }
    table.cijfers td.r { text-align: right; }
    table.cijfers td.c { text-align: center; }
    tr.stud td { background: #eef0f6; border-top: 1px solid #1E1446; padding: 4px 5px; font-size: 9pt; }
    tr.stud b { color: #1E1446; }
    tr.stud .meta { color: #666; font-size: 8pt; }
    .fail { color: #C8102E; font-weight: bold; }
    .pass { color: #285C4D; font-weight: bold; }
    .foot { margin-top: 12px; padding-top: 6px; border-top: 1px solid #ddd; font-size: 7.5pt; color: #999; }
  </style>
</head>
<body>
  <div class="head">
    @if ($logo)<img src="{{ $logo }}" alt="IUASR" style="height:56px;">@else<b>IUASR</b>@endif
    <h1>Cijferoverzicht studiejaar {{ $periodeCode }}@if (!empty($deelLabel)) <span style="font-size:10pt;color:#666;">({{ $deelLabel }})</span>@endif</h1>
    <p class="sub">{{ $opleiding->naam }} &middot; {{ $studenten->count() }} {{ $studenten->count() === 1 ? 'student' : 'studenten' }}@if (!empty($deelLabel)) ({{ $deelLabel }})@endif &middot; Rotterdam, {{ now()->format('d-m-Y') }}</p>
  </div>

  <div class="banner">
    <b>Informatief overzicht &mdash; geen officieel gewaarmerkt document.</b>
    Gemigreerd uit het oude Access-systeem; oude schaal 0&ndash;100 omgezet naar 0&ndash;10 (voldoende vanaf {{ number_format($grens, 1, ',', '') }}). Per vak één eindcijfer. Toont uitsluitend studiejaar {{ $periodeCode }}.
  </div>

  <table class="cijfers">
    <thead>
      <tr>
        <th style="width:82px;">Code</th><th>Vak</th>
        <th style="text-align:right;width:52px;">Cijfer</th>
        <th style="text-align:center;width:36px;">EC</th>
        <th style="width:92px;">Resultaat</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($studenten as $blok)
        @php $s = $blok['student']; @endphp
        <tr class="stud">
          <td colspan="5"><b>{{ $s->studentnummer }} &middot; {{ $s->volledigeNaam() }}</b>
            <span class="meta">&nbsp; EC behaald {{ $ec($blok['ec_behaald']) }}@if ($blok['gemiddelde'] !== null) &middot; gemiddelde {{ number_format($blok['gemiddelde'], 1, ',', '') }}@endif</span>
          </td>
        </tr>
        @foreach ($blok['rijen'] as $r)
          @php $vak = $r->toetsonderdeel->vak; @endphp
          <tr>
            <td>{{ $vak?->code ?? '—' }}</td>
            <td>{{ $vak?->naam ?? '—' }}</td>
            <td class="r">@if ($r->vrijstelling)VR @elseif ($r->cijfer !== null)<span class="{{ $r->cijfer < $grens ? 'fail' : 'pass' }}">{{ number_format($r->cijfer, 1, ',', '') }}</span>@else —@endif</td>
            <td class="c">{{ $r->voldoende ? $ec($vak?->ec ?? 0) : '—' }}</td>
            <td>@if ($r->vrijstelling)Vrijstelling @elseif ($r->voldoende)Voldoende @else Onvoldoende @endif</td>
          </tr>
        @endforeach
      @endforeach
    </tbody>
  </table>

  <div class="foot">Afgedrukt door {{ $uitgegevenDoor }} op {{ now()->format('d-m-Y H:i') }} &middot; Bron: migratie oude Access-database &middot; Informatief, niet gewaarmerkt.</div>
</body>
</html>
