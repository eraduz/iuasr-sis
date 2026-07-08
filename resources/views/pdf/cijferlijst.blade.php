<!DOCTYPE html>
<html lang="nl">
@php
  $logoPad = public_path('assets/img/iuasr-logo.png');
  $logo = is_file($logoPad) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPad)) : null;
  $grens = 5.5;
  $eindTekst = fn ($e) => match ($e['status']) {
      'cijfer' => number_format($e['cijfer'], 1, ',', ''),
      'vr' => 'VR', 'onvolledig' => 'onvolledig', default => '—',
  };
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
    .sub { font-size: 9pt; color: #666; margin: 0 0 12px; }
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
    .foot td.wm { text-align: right; text-transform: uppercase; letter-spacing: 3px; color: #C8102E; font-weight: bold; }
  </style>
</head>
<body>
  <table class="head">
    <tr>
      <td>@if ($logo)<img src="{{ $logo }}" alt="IUASR" style="height:96px;">@else<b>IUASR</b>@endif</td>
      <td class="org">Bergsingel 135 &middot; 3037 GC Rotterdam<br>Tel: +31 (0)10 485 47 21<br>szaken@iuasr.nl<br>info@iuasr.nl</td>
    </tr>
  </table>

  <h1>Cijferlijst</h1>
  <p class="sub">Officieel cijferoverzicht &middot; Rotterdam, {{ now()->format('d-m-Y') }}</p>

  <table class="kv">
    <tr><td class="k">Naam</td><td class="v">{{ $student->volledigeNaam() }}</td></tr>
    <tr><td class="k">Studentnummer</td><td class="v">{{ $student->studentnummer }}</td></tr>
    <tr><td class="k">Geboortedatum</td><td class="v">{{ $student->geboortedatum?->format('d-m-Y') ?? '—' }}</td></tr>
    <tr><td class="k">Behaalde EC</td><td class="v">{{ $transcript['behaaldeEc'] }}@if($transcript['ecTotaal']) van {{ $transcript['ecTotaal'] }}@endif</td></tr>
  </table>

  @forelse ($transcript['studiejaren'] as $sj)
    <div class="jaar">{{ $sj['inschrijving']->periode?->naam ?? 'Studiejaar' }} &middot; leerjaar {{ $sj['inschrijving']->leerjaar }} <span>({{ $sj['inschrijving']->opleiding?->naam }} &middot; {{ $sj['behaaldeEc'] }}/{{ $sj['mogelijkeEc'] }} EC)</span></div>
    <table class="cijfers">
      <thead><tr><th>Code</th><th>Vak</th><th style="text-align:right;">Cijfer</th><th style="text-align:center;">EC</th><th>Status</th></tr></thead>
      <tbody>
        @foreach ($sj['regels'] as $r)
          @php $e = $r['eind']; $ec = $r['ec']; @endphp
          <tr>
            <td>{{ $r['vak']->code }}</td>
            <td>{{ $r['vak']->naam }}</td>
            <td class="r">@if($e['status']==='cijfer')<span class="{{ $e['cijfer'] < $grens ? 'fail' : 'pass' }}">{{ $eindTekst($e) }}</span>@else{{ $eindTekst($e) }}@endif</td>
            <td class="c">{{ $ec !== null ? $ec : '—' }}</td>
            <td>@if($e['status']==='vr')Vrijstelling @elseif(($ec ?? 0) > 0)Behaald @elseif($e['status']==='cijfer')Niet behaald @else Open @endif</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @empty
    <p>Geen inschrijvingen / resultaten geregistreerd.</p>
  @endforelse

  <p class="totaal">Totaal behaalde EC: {{ $transcript['behaaldeEc'] }}@if($transcript['ecTotaal']) van {{ $transcript['ecTotaal'] }}@endif</p>

  <table class="foot">
    <tr>
      <td>Uitgegeven door {{ $ondertekenaar ?? 'IUASR' }} namens IUASR. Voldoende-grens: {{ number_format($grens, 1, ',', '') }}.</td>
      <td class="wm">Officieel document</td>
    </tr>
  </table>
</body>
</html>
