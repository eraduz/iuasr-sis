<!DOCTYPE html>
<html lang="nl">
@php
  $logoPad = public_path('assets/img/iuasr-logo.png');
  $logo = is_file($logoPad) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPad)) : null;
  $eindTekst = fn ($e) => match ($e['status']) {
      'cijfer' => number_format($e['cijfer'], 1, ',', ''),
      'vr' => 'VR', 'onvolledig' => 'onvolledig', default => '—',
  };
@endphp
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 13mm 16mm; }
    body { font-family: "DejaVu Sans", sans-serif; color: #1E1446; font-size: 9.5pt; line-height: 1.35; }
    .head { width: 100%; border-bottom: 2px solid #1E1446; padding-bottom: 8px; margin-bottom: 12px; }
    .head td { vertical-align: middle; }
    .head .org { text-align: right; font-size: 8pt; color: #666; line-height: 1.4; }
    h1 { font-size: 16pt; font-weight: bold; margin: 0 0 3px; color: #1E1446; }
    .sub { font-size: 8.5pt; color: #666; margin: 0 0 10px; }
    .sum { font-size: 8.5pt; color: #1E1446; margin: 0 0 10px; }
    table.lijst { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
    table.lijst th { text-align: left; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.03em; color: #1E1446; border-bottom: 1.5px solid #1E1446; padding: 5px 5px; }
    table.lijst td { padding: 4px 5px; border-bottom: 1px solid #eee; }
    table.lijst td.c { text-align: center; }
    table.lijst td.r { text-align: right; }
    .fail { color: #C8102E; font-weight: bold; }
    .pass { color: #285C4D; font-weight: bold; }
    .foot { width: 100%; margin-top: 18px; padding-top: 8px; border-top: 1px solid #ddd; font-size: 7.5pt; color: #666; }
    .foot td.wm { text-align: right; text-transform: uppercase; letter-spacing: 3px; color: #C8102E; font-weight: bold; }
  </style>
</head>
<body>
  <table class="head">
    <tr>
      <td>@if ($logo)<img src="{{ $logo }}" alt="IUASR" style="height:80px;">@else<b>IUASR</b>@endif</td>
      <td class="org">Bergsingel 135 &middot; 3037 GC Rotterdam<br>Tel: +31 (0)10 485 47 21<br>szaken@iuasr.nl</td>
    </tr>
  </table>

  <h1>Tentamenlijst</h1>
  <p class="sub"><b>{{ $vak->code }} — {{ $vak->naam }}</b> &middot; {{ $vak->opleiding?->naam }} &middot; {{ $periode->naam }} &middot; docent: {{ $vak->docent?->achternaam ?? '—' }} &middot; Rotterdam, {{ now()->format('d-m-Y') }}</p>
  <p class="sum">Deelnemers: {{ $samenvatting['aantal'] }} &middot; geslaagd: {{ $samenvatting['geslaagd'] }} &middot; gemiddeld eindcijfer: {{ $samenvatting['gemiddeld'] !== null ? number_format($samenvatting['gemiddeld'],1,',','') : '—' }} &middot; cesuur: {{ number_format($grens,1,',','') }}</p>

  <table class="lijst">
    <thead>
      <tr>
        <th>Studentnr.</th>
        <th>Naam</th>
        @foreach ($vak->toetsonderdelen as $od)
          <th style="text-align:center;">{{ $od->naam }}<br>{{ rtrim(rtrim(number_format($od->weging*100,0),'0'),'.') }}%</th>
        @endforeach
        <th style="text-align:right;">Eind</th>
        <th style="text-align:center;">EC</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($rijen as $rij)
        @php $eind = $rij['eind']; $ec = $rij['ec']; @endphp
        <tr>
          <td>{{ $rij['student']->studentnummer }}</td>
          <td>{{ $rij['student']->volledigeNaam() }}</td>
          @foreach ($vak->toetsonderdelen as $od)
            @php $res = $rij['perOnderdeel'][$od->id] ?? null; @endphp
            <td class="c">@if($res && $res->vrijstelling)VR @elseif($res && $res->cijfer !== null)<span class="{{ (float)$res->cijfer < $grens ? 'fail' : '' }}">{{ number_format($res->cijfer,1,',','') }}</span>@else—@endif</td>
          @endforeach
          <td class="r">@if($eind['status']==='cijfer')<span class="{{ $eind['cijfer'] < $grens ? 'fail' : 'pass' }}">{{ $eindTekst($eind) }}</span>@else{{ $eindTekst($eind) }}@endif</td>
          <td class="c">{{ $ec !== null ? $ec : '—' }}</td>
          <td>@if($eind['status']==='vr')Vrijstelling @elseif(($ec ?? 0) > 0)Behaald @elseif($eind['status']==='cijfer')Niet behaald @else Open @endif</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <table class="foot">
    <tr>
      <td>Uitgegeven door {{ $ondertekenaar ?? 'IUASR' }} namens IUASR.</td>
      <td class="wm">Officieel document</td>
    </tr>
  </table>
</body>
</html>
