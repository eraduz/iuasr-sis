<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: "DejaVu Sans", sans-serif; color: #1a1a1a; font-size: 12px; margin: 40px 46px; }
    .brand { border-bottom: 3px solid #C8102E; padding-bottom: 10px; margin-bottom: 26px; }
    .brand h1 { color: #1E1446; font-size: 19px; margin: 0 0 2px; }
    .brand .sub { color: #666; font-size: 10.5px; }
    h2 { color: #1E1446; font-size: 18px; margin: 0 0 4px; }
    .meta { color: #666; font-size: 10.5px; margin-bottom: 22px; }
    p { line-height: 1.65; margin: 0 0 12px; }
    .naam { font-weight: bold; color: #1E1446; }
    .ondertekening { margin-top: 42px; }
  </style>
</head>
<body>
  <div class="brand">
    <h1>Islamic University of Applied Sciences Rotterdam</h1>
    <div class="sub">Afdeling Studentenzaken &middot; Rotterdam &middot; www.iuasr.nl</div>
  </div>

  <h2>{{ $verklaring['title'] }}</h2>
  <div class="meta">{{ $verklaring['sub'] }} &middot; Kenmerk: {{ $verklaring['ref'] }} &middot; {{ now()->format('d-m-Y') }}</div>

  <p>De afdeling Studentenzaken van IUASR verklaart hierbij dat:</p>
  <p class="naam">{{ $student->volledigeNaam() }} (studentnummer {{ $student->studentnummer }}@if($student->geboortedatum), geboren op {{ $student->geboortedatum->format('d-m-Y') }}@endif)</p>
  <p>{{ $verklaring['body'] }}</p>
  <p>{{ $verklaring['body2'] }}</p>

  <div class="ondertekening">
    <p>Hoogachtend,<br><br>Afdeling Studentenzaken<br>Islamic University of Applied Sciences Rotterdam</p>
  </div>
</body>
</html>
