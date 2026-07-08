<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: "DejaVu Sans", sans-serif; color: #1a1a1a; font-size: 12px; margin: 40px 46px; }
    .brand { border-bottom: 3px solid #C8102E; padding-bottom: 10px; margin-bottom: 24px; }
    .brand h1 { color: #1E1446; font-size: 18px; margin: 0 0 2px; }
    .brand .sub { color: #666; font-size: 10.5px; }
    h2 { color: #1E1446; font-size: 18px; margin: 0 0 14px; }
    p { line-height: 1.6; margin: 0 0 12px; }
    table { width: 100%; border-collapse: collapse; margin: 8px 0 16px; }
    td { padding: 7px 8px; border-bottom: 1px solid #e5e5ea; font-size: 11.5px; vertical-align: top; }
    td.k { color: #666; width: 190px; }
    td.v { font-weight: bold; color: #1E1446; }
    .hash { font-family: "DejaVu Sans Mono", monospace; font-size: 9.5px; word-break: break-all; }
    .note { color: #666; font-size: 10px; margin-top: 18px; border-top: 2px solid #1E1446; padding-top: 8px; }
  </style>
</head>
<body>
  <div class="brand">
    <h1>Islamic University of Applied Sciences Rotterdam</h1>
    <div class="sub">Digitaal waarmerk &middot; echtheidscertificaat</div>
  </div>

  <h2>Digitaal waarmerk</h2>
  <p>IUASR verklaart dat het hieronder omschreven document digitaal is gewaarmerkt. De echtheid en onveranderlijkheid kunnen worden gecontroleerd op basis van de verificatiecode en het echtheidskenmerk (SHA-256).</p>

  <table>
    <tr><td class="k">Documenttitel</td><td class="v">{{ $titel }}</td></tr>
    <tr><td class="k">Origineel bestand</td><td class="v">{{ $origineleNaam }}</td></tr>
    <tr><td class="k">Ondertekend door</td><td class="v">{{ $ondertekenaar?->naam ?? 'IUASR' }}@if($ondertekenaar?->rol) ({{ $ondertekenaar->rol->label() }})@endif namens IUASR</td></tr>
    <tr><td class="k">Datum</td><td class="v">{{ now()->format('d-m-Y H:i') }}</td></tr>
    <tr><td class="k">Verstrekt aan</td><td class="v">{{ $ontvanger ?? '—' }}</td></tr>
    <tr><td class="k">Verificatiecode</td><td class="v">{{ $code }}</td></tr>
    <tr><td class="k">Echtheidskenmerk (SHA-256)</td><td class="v hash">{{ $sha256 }}</td></tr>
  </table>

  <p>Controleer de echtheid op <b>{{ $verifyUrl }}</b> met de verificatiecode <b>{{ $code }}</b>. Upload daar optioneel het originele PDF-bestand; komt het echtheidskenmerk overeen, dan is het document ongewijzigd.</p>

  <div class="note">
    Dit waarmerk hoort bij het originele document ({{ $origineleNaam }}). Bewaar beide bestanden samen. Elke wijziging aan het originele bestand maakt het echtheidskenmerk ongeldig.
  </div>
</body>
</html>
