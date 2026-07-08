<!DOCTYPE html>
<html lang="nl">
@php
  $logoPad = public_path('assets/img/logo-dark.png');
  $logo = is_file($logoPad) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPad)) : null;
@endphp
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 22mm 20mm; }
    body { font-family: "DejaVu Sans", sans-serif; color: #1E1446; font-size: 11pt; line-height: 1.55; }
    .head { width: 100%; border-bottom: 2px solid #1E1446; padding-bottom: 12px; margin-bottom: 22px; }
    .head td { vertical-align: top; }
    .head .org { text-align: right; font-size: 9pt; color: #666; line-height: 1.5; }
    .head .org b { color: #1E1446; font-weight: bold; display: block; margin-bottom: 2px; }
    h1 { font-size: 22pt; font-weight: bold; margin: 0 0 4px; color: #1E1446; }
    .doc-sub { font-size: 10pt; color: #666; margin: 0 0 22px; }
    p { margin: 0 0 11px; }
    table.kv { width: 100%; border-collapse: collapse; margin: 14px 0 18px; font-size: 10.5pt; }
    table.kv td { padding: 4px 0; vertical-align: top; }
    table.kv td.k { color: #666; width: 190px; }
    table.kv td.v { color: #1E1446; font-weight: bold; }
    .sig { width: 100%; margin-top: 40px; }
    .sig td { width: 50%; vertical-align: top; padding-top: 6px; border-top: 1px solid #1E1446; font-size: 9.5pt; color: #666; }
    .sig td.right { text-align: right; border-top: 0; }
    .sig .naam { color: #1E1446; font-weight: bold; }
    .stamp { display: inline-block; border: 1.5px solid #285C4D; color: #285C4D; border-radius: 8px; padding: 7px 14px; font-size: 9pt; font-weight: bold; }
    .foot { width: 100%; margin-top: 34px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 8.5pt; color: #666; }
    .foot td.wm { text-align: right; text-transform: uppercase; letter-spacing: 3px; color: #C8102E; font-weight: bold; }
  </style>
</head>
<body>
  <table class="head">
    <tr>
      <td>@if ($logo)<img src="{{ $logo }}" alt="IUASR" style="height:72px;">@else<b style="font-size:14pt;">IUASR</b>@endif</td>
      <td class="org">
        <b>Islamic University of Applied Sciences Rotterdam</b>
        Bergsingel 135 &middot; 3037 GC Rotterdam<br>
        Tel: +31 (0)10 485 47 21<br>
        szaken@iuasr.nl &middot; info@iuasr.nl
      </td>
    </tr>
  </table>

  <h1>{{ $verklaring['title'] }}</h1>
  <p class="doc-sub">{{ $verklaring['sub'] }}</p>

  <p>Hierbij verklaart Bureau Studentenzaken van de Islamic University of Applied Sciences Rotterdam dat:</p>

  <table class="kv">
    <tr><td class="k">Naam</td><td class="v">{{ $student->volledigeNaam() }}</td></tr>
    <tr><td class="k">Studentnummer</td><td class="v">{{ $student->studentnummer }}</td></tr>
    <tr><td class="k">Geboortedatum</td><td class="v">{{ $student->geboortedatum?->format('d-m-Y') ?? '—' }}</td></tr>
    <tr><td class="k">Opleiding</td><td class="v">{{ $verklaring['opleiding'] }}</td></tr>
  </table>

  <p>{{ $verklaring['body'] }}</p>
  <p>{{ $verklaring['body2'] }}</p>

  <table class="sig">
    <tr>
      <td>Namens Bureau Studentenzaken<br><span class="naam">{{ $ondertekenaar ?? 'Studentenzaken' }}</span><br>medewerker Studentenzaken</td>
      <td class="right"><span class="stamp">Gewaarmerkt IUASR</span></td>
    </tr>
  </table>

  <table class="foot">
    <tr>
      <td>Referentie: {{ $verklaring['ref'] }} &middot; Rotterdam, {{ now()->format('d-m-Y') }}</td>
      <td class="wm">Officieel document</td>
    </tr>
  </table>
</body>
</html>
