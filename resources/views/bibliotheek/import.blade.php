@extends('layouts.app')

@section('titel', 'Bibliotheek importeren')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('bibliotheek.dashboard') }}">Bibliotheek</a><span class="sep">›</span><b>Importeren</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Excel-bibliotheek importeren</h1>
    <div class="summary">Eerst proefdraaien, dan pas importeren. Bij het proefdraaien wordt er niets opgeslagen.</div>
  </div>
</div>

<div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-bottom:16px;">
  <span>Het bestand moet het werkblad <code>{{ \App\Support\BibliotheekImport::WERKBLAD }}</code> bevatten, met de kolommen: rekcode, aantal, vakgebied, taal, titel, schrijver, opmerking. Het vakgebied wordt bepaald uit de <b>rekletter</b> (A&nbsp;=&nbsp;Tafsir, F&nbsp;=&nbsp;Fiqh, …); de vakgebiedkolom uit de bron blijft als opmerking bewaard.</span>
</div>

<form method="POST" action="{{ route('bibliotheek.import.proef') }}" enctype="multipart/form-data" class="sis-card sis-form" style="max-width:720px;">
  @csrf
  <div class="sis-fld">
    <label>Excel-bestand <span class="req">*</span></label>
    <input type="file" name="bestand" accept=".xlsx,.xls" required>
    <small class="sis-muted">Maximaal 20 MB.</small>
  </div>
  <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Inlezen en rapporteren</button></div></div>
</form>

@if ($rapport)
  <h2 style="margin:22px 0 10px;">Rapport — {{ $rapport['bestandsnaam'] }}</h2>

  <div class="iuasr-dash-stats">
    <div class="iuasr-dash-stat"><span class="lbl">Bruikbare titels</span><span class="val">{{ number_format($rapport['statistiek']['titels'], 0, ',', '.') }}</span><span class="delta">worden aangemaakt</span></div>
    <div class="iuasr-dash-stat"><span class="lbl">Fysieke exemplaren</span><span class="val">{{ number_format($rapport['statistiek']['exemplaren'], 0, ',', '.') }}</span><span class="delta">uit de aantalkolom</span></div>
    <div class="iuasr-dash-stat"><span class="lbl">Tijdschriften</span><span class="val">{{ $rapport['statistiek']['tijdschriften'] }}</span><span class="delta">geen boek</span></div>
    <div class="iuasr-dash-stat {{ $rapport['statistiek']['zonder_taal'] > 0 ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Zonder taal</span><span class="val">{{ $rapport['statistiek']['zonder_taal'] }}</span><span class="delta">bron onbruikbaar</span></div>
    <div class="iuasr-dash-stat {{ count($rapport['overgeslagen']) > 0 ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Overgeslagen</span><span class="val">{{ $rapport['overgeslagen_totaal'] }}</span><span class="delta">regels</span></div>
  </div>

  <h3 style="margin:18px 0 8px;">Voorbeeld van de eerste 15 regels</h3>
  <div class="iuasr-dash-tbl-card">
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Rek</th><th>Titel</th><th>Auteur</th><th>Taal</th><th>Vakgebied (uit rekletter)</th><th style="text-align:right;">Exemplaren</th></tr></thead>
      <tbody>
        @foreach ($rapport['voorbeeld'] as $rij)
          <tr>
            <td class="tnum">{{ $rij['rekcode'] }}</td>
            <td class="nm" dir="auto">{{ \Illuminate\Support\Str::limit($rij['titel'], 60) }}</td>
            <td dir="auto">{{ \Illuminate\Support\Str::limit($rij['auteur'] ?? '—', 35) }}</td>
            <td>{{ $rij['taalcode'] ?? '—' }}</td>
            <td>{{ $rij['rekletter'] }}</td>
            <td class="tnum" style="text-align:right;">{{ $rij['aantal'] }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @if ($rapport['overgeslagen'])
    <h3 style="margin:18px 0 8px;">Overgeslagen regels</h3>
    <div class="iuasr-dash-tbl-card">
      <table class="iuasr-dash-tbl">
        <thead><tr><th style="width:100px;">Regel</th><th style="width:140px;">Rekcode</th><th>Reden</th></tr></thead>
        <tbody>
          @foreach ($rapport['overgeslagen'] as $r)
            <tr><td class="tnum">{{ $r['regel'] }}</td><td class="tnum">{{ $r['rekcode'] }}</td><td>{{ $r['reden'] }}</td></tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @if ($rapport['overgeslagen_totaal'] > count($rapport['overgeslagen']))
      <p class="sis-tblnote">+ {{ $rapport['overgeslagen_totaal'] - count($rapport['overgeslagen']) }} meer. Deze regels bevatten geen titel en zijn in de bron niet bruikbaar.</p>
    @endif
  @endif

  @if ($rapport['statistiek']['titels'] > $maxViaScherm)
    <div class="iuasr-dash-alert iuasr-dash-alert--warn" style="margin-top:16px;">
      <span>
        Dit bestand bevat {{ number_format($rapport['statistiek']['titels'], 0, ',', '.') }} titels — te veel om via de browser weg te schrijven (grens: {{ $maxViaScherm }}).
        Draai op de server: <code>php artisan bibliotheek:importeren "{{ $rapport['pad'] }}"</code>
      </span>
    </div>
  @else
    <form method="POST" action="{{ route('bibliotheek.import.uitvoeren') }}" style="margin-top:16px;">
      @csrf
      <input type="hidden" name="pad" value="{{ $rapport['pad'] }}">
      <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Deze {{ $rapport['statistiek']['titels'] }} titels nu importeren</button>
    </form>
  @endif

  <p class="sis-tblnote">De import is herhaalbaar: regels waarvan de rekcode al is ingelezen, worden overgeslagen. U krijgt dus geen dubbele boeken als u het nog een keer draait.</p>
@endif
@endsection
