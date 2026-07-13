<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Bibliotheek IUASR — boek zoeken</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Fira+Sans:wght@200;300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/sis.css') }}?v={{ filemtime(public_path('assets/css/sis.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/iuasr-plugin-dash.css') }}?v={{ filemtime(public_path('assets/css/iuasr-plugin-dash.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/sis-theme.css') }}?v={{ filemtime(public_path('assets/css/sis-theme.css')) }}">
<script>try{var t=localStorage.getItem('sis-theme');document.documentElement.setAttribute('data-theme',t==='light'?'light':'dark');}catch(e){document.documentElement.setAttribute('data-theme','dark');}</script>
<style>
  /* Zoekpagina voor de PC in de bibliotheek: groot en rustig, één taak. */
  .bieb-wrap { max-width: 1100px; margin: 0 auto; padding: 32px 20px 60px; }
  .bieb-kop { text-align: center; margin-bottom: 26px; }
  .bieb-kop h1 { font-size: 34px; margin: 0 0 6px; }
  .bieb-kop p { margin: 0; opacity: .75; }
  .bieb-zoek input[type="search"] { font-size: 19px; padding: 14px 16px; width: 100%; }
  .bieb-filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 10px; }
  .bieb-rek { font-weight: 700; font-size: 15px; }
</style>
</head>
<body>
<div class="bieb-wrap">

  <div class="bieb-kop">
    <h1>Bibliotheek IUASR</h1>
    <p>Zoek een boek, tijdschrift of document. U ziet waar het ligt en of het beschikbaar is.</p>
  </div>

  <form method="GET" action="{{ route('catalogus.publiek') }}" class="sis-card bieb-zoek" style="margin-bottom:18px;">
    <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op titel, auteur, ISBN of reknummer" dir="auto" autofocus>

    <div class="bieb-filters">
      <select name="taal">
        <option value="">Alle talen</option>
        @foreach ($talen as $t)
          <option value="{{ $t->id }}" @selected($taalFilter === $t->id)>{{ $t->naam }}</option>
        @endforeach
      </select>
      <select name="vakgebied">
        <option value="">Alle vakgebieden</option>
        @foreach ($vakgebieden as $v)
          <option value="{{ $v->id }}" @selected($vakgebiedFilter === $v->id)>{{ $v->naam }}</option>
        @endforeach
      </select>
      <label class="sis-check-inline"><input type="checkbox" name="beschikbaar" value="1" @checked($alleenBeschikbaar)> Alleen beschikbaar</label>
      <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Zoeken</button>
      <a class="iuasr-dash-btn" href="{{ route('catalogus.publiek') }}">Wissen</a>
    </div>
  </form>

  <p class="sis-muted" style="margin:0 0 10px;">
    {{ number_format($publicaties->total(), 0, ',', '.') }} {{ $publicaties->total() === 1 ? 'resultaat' : 'resultaten' }}
    @if ($zoek !== '') voor <b>"{{ $zoek }}"</b>@endif
  </p>

  <div class="iuasr-dash-tbl-card">
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Titel</th><th>Auteur(s)</th><th>Taal</th><th>Jaar</th><th>Rek</th><th style="text-align:center;">Beschikbaar</th></tr></thead>
      <tbody>
        @forelse ($publicaties as $p)
          <tr>
            <td class="nm" dir="auto">
              {{ $p->volledigeTitel() }}
              @if ($p->isbn)<br><small class="sis-muted">ISBN {{ $p->isbn }}</small>@endif
            </td>
            <td dir="auto">{{ $p->auteursTekst() }}</td>
            <td>{{ $p->talenTekst() }}</td>
            <td class="tnum">{{ $p->uitgavejaar ?? '—' }}</td>
            <td class="tnum bieb-rek">{{ $p->rekplaats() ?? '—' }}</td>
            <td style="text-align:center;">
              @if (! $p->soort->heeftExemplaren())
                <span class="sis-muted">digitaal</span>
              @elseif ($p->aantalBeschikbaar() > 0)
                <span class="iuasr-dash-status s-approved">ja</span>
              @else
                <span class="iuasr-dash-status s-submitted">uitgeleend</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6">
            <div class="iuasr-dash-empty" style="border:0;">
              <h3>Niets gevonden</h3>
              <p class="sis-muted">Probeer een deel van de titel, de naam van de auteur, of vraag het aan de bibliotheekmedewerker.</p>
            </div>
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top:14px;">{{ $publicaties->links() }}</div>

  <p class="sis-tblnote" style="margin-top:18px;">
    <b>Rek</b> is de plek in de kast (bijvoorbeeld <b>F. 1070</b>: letter = kast, nummer = plaats in het rek).
    Wilt u een boek lenen? Meld u bij de bibliotheekmedewerker.
  </p>

</div>
</body>
</html>
