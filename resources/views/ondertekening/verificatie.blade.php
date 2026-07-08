<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Documentverificatie · IUASR</title>
  <style>
    :root { --pri:#1E1446; --sec:#C8102E; --ok:#285C4D; }
    * { box-sizing:border-box; }
    body { margin:0; font-family:'Fira Sans',Arial,sans-serif; background:#f4f4f7; color:#1a1a1a; }
    .wrap { max-width:640px; margin:0 auto; padding:32px 20px; }
    .brand { border-bottom:3px solid var(--sec); padding-bottom:12px; margin-bottom:24px; }
    .brand h1 { color:var(--pri); font-size:20px; margin:0; }
    .brand .sub { color:#666; font-size:13px; }
    .card { background:#fff; border:1px solid #e5e5ea; border-radius:12px; padding:22px; margin-bottom:16px; }
    h2 { color:var(--pri); font-size:17px; margin:0 0 12px; }
    label { display:block; font-size:13px; font-weight:600; margin-bottom:6px; }
    input[type=text], input[type=file] { width:100%; padding:10px 12px; border:1px solid #cfcfd6; border-radius:8px; font-size:14px; margin-bottom:14px; }
    button { background:var(--pri); color:#fff; border:0; border-radius:8px; padding:11px 20px; font-size:14px; font-weight:600; cursor:pointer; }
    .badge { display:inline-block; padding:5px 12px; border-radius:999px; font-size:13px; font-weight:600; }
    .badge.ok { background:#e7f2ee; color:var(--ok); }
    .badge.bad { background:#fbe9ec; color:var(--sec); }
    dl { display:grid; grid-template-columns:180px 1fr; gap:8px 12px; margin:0; font-size:14px; }
    dt { color:#666; }
    dd { margin:0; font-weight:600; }
    .muted { color:#666; font-size:13px; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="brand">
      <h1>Islamic University of Applied Sciences Rotterdam</h1>
      <div class="sub">Echtheidscontrole digitaal ondertekende documenten</div>
    </div>

    <div class="card">
      <h2>Controleer een document</h2>
      <form method="POST" action="{{ route('verificatie') }}" enctype="multipart/form-data">
        @csrf
        <label>Verificatiecode (staat onderaan het document)</label>
        <input type="text" name="code" value="{{ $code }}" placeholder="IUASR-XXXX-XXXX" required>
        <label>PDF-bestand controleren (optioneel — bevestigt dat het ongewijzigd is)</label>
        <input type="file" name="bestand" accept="application/pdf">
        <button type="submit">Controleren</button>
      </form>
    </div>

    @if ($code !== '')
      <div class="card">
        @if ($document)
          <h2>Geldig document <span class="badge ok">echt</span></h2>
          <dl>
            <dt>Documenttype</dt><dd>{{ $document->titel }}</dd>
            <dt>Uitgegeven door</dt><dd>{{ $document->uitgegevenDoor?->naam ?? 'IUASR' }} namens IUASR</dd>
            <dt>Datum</dt><dd>{{ $document->created_at->format('d-m-Y H:i') }}</dd>
            <dt>Verstrekt aan</dt><dd>{{ $document->ontvanger ?? '—' }}</dd>
            <dt>Verificatiecode</dt><dd>{{ $document->code }}</dd>
          </dl>
          @if ($bestandStatus === 'ongewijzigd')
            <p style="margin-top:16px;"><span class="badge ok">Bestand ongewijzigd</span> — de geüploade PDF komt exact overeen met het uitgegeven origineel.</p>
          @elseif ($bestandStatus === 'gewijzigd')
            <p style="margin-top:16px;"><span class="badge bad">Bestand gewijzigd</span> — de geüploade PDF wijkt af van het origineel en is mogelijk vervalst.</p>
          @else
            <p class="muted" style="margin-top:16px;">Upload optioneel de PDF om te bevestigen dat het bestand niet is gewijzigd.</p>
          @endif
        @else
          <h2>Niet gevonden <span class="badge bad">onbekend</span></h2>
          <p class="muted">Er is geen document gevonden met verificatiecode <b>{{ $code }}</b>. Controleer de code of neem contact op met de afdeling Studentenzaken.</p>
        @endif
      </div>
    @endif

    <p class="muted">Deze pagina bevestigt de herkomst en onveranderlijkheid van door IUASR uitgegeven documenten. Vragen? szaken@iuasr.nl</p>
  </div>
</body>
</html>
