<!DOCTYPE html>
<html lang="nl">
@php
  $logoPad = public_path('assets/img/iuasr-logo.png');
  $logo = is_file($logoPad) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPad)) : null;
@endphp
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 13mm 16mm; }
    body { font-family: "DejaVu Sans", sans-serif; color: #1E1446; font-size: 10pt; line-height: 1.35; }
    .head { width: 100%; border-bottom: 2px solid #1E1446; padding-bottom: 8px; margin-bottom: 12px; }
    .head td { vertical-align: middle; }
    .head .org { text-align: right; font-size: 8pt; color: #666; line-height: 1.4; }
    h1 { font-size: 17pt; font-weight: bold; margin: 0 0 3px; color: #1E1446; }
    .sub { font-size: 8.5pt; color: #666; margin: 0 0 10px; }
    table.meta { width: 100%; border-collapse: collapse; font-size: 9pt; margin: 0 0 12px; }
    table.meta td { padding: 3px 0; }
    table.meta td.k { color: #666; width: 130px; }
    table.lijst { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
    table.lijst th { text-align: left; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.03em; color: #1E1446; border-bottom: 1.5px solid #1E1446; padding: 6px 6px; }
    table.lijst td { padding: 8px 6px; border-bottom: 1px solid #ccc; }
    .sig { width: 100%; margin-top: 30px; }
    .sig td { width: 50%; vertical-align: top; padding-top: 6px; border-top: 1px solid #1E1446; font-size: 8.5pt; color: #666; }
    .foot { width: 100%; margin-top: 22px; padding-top: 8px; border-top: 1px solid #ddd; font-size: 7.5pt; color: #666; }
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

  <h1>Presentielijst</h1>
  <p class="sub"><b>{{ $vak->code }} — {{ $vak->naam }}</b> &middot; {{ $vak->opleiding?->naam }} &middot; {{ $periode->naam }} &middot; gegenereerd {{ now()->format('d-m-Y') }}</p>

  <table class="meta">
    <tr><td class="k">Docent</td><td>{{ $vak->docent?->achternaam ?? '—' }}</td><td class="k">Aantal deelnemers</td><td>{{ $samenvatting['aantal'] }}</td></tr>
    <tr><td class="k">Datum tentamen</td><td>………………………</td><td class="k">Tijd / lokaal</td><td>………………………</td></tr>
  </table>

  <table class="lijst">
    <thead>
      <tr><th style="width:24px;">#</th><th style="width:90px;">Studentnr.</th><th>Naam</th><th style="width:40%;">Handtekening</th></tr>
    </thead>
    <tbody>
      @forelse ($rijen as $rij)
        <tr>
          <td>{{ $loop->iteration }}</td>
          <td>{{ $rij['student']->studentnummer }}</td>
          <td>{{ $rij['student']->volledigeNaam() }}</td>
          <td></td>
        </tr>
      @empty
        <tr><td colspan="4" style="text-align:center;color:#666;padding:16px;">Geen deelnemers.</td></tr>
      @endforelse
    </tbody>
  </table>

  <table class="sig">
    <tr>
      <td>Naam surveillant / docent</td>
      <td>Handtekening surveillant</td>
    </tr>
  </table>

  <table class="foot">
    <tr>
      <td>Presentielijst &middot; bevat geen cijfers of studiepunten (privacy) &middot; uitgegeven door {{ $ondertekenaar ?? 'IUASR' }}.</td>
      <td class="wm">Aanwezigheid</td>
    </tr>
  </table>
</body>
</html>
