@extends('layouts.app')

@section('titel', 'Bibliotheek IUASR')

@section('inhoud')
<div class="sis-crumb"><b>Bibliotheek IUASR</b><span class="sep">›</span><b>Boek zoeken</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Bibliotheek IUASR</h1>
    <div class="summary">
      {{ number_format($publicaties->total(), 0, ',', '.') }} {{ $publicaties->total() === 1 ? 'titel' : 'titels' }} —
      zoek een boek, tijdschrift of digitaal document. U ziet in welke kast het staat en of er een exemplaar beschikbaar is.
    </div>
  </div>
</div>

<form method="GET" action="{{ route('catalogus') }}" class="sis-toolbar" style="margin-bottom:12px; gap:8px; flex-wrap:wrap;" data-autofilter>
  <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op titel, auteur, ISBN of rek (F. 1070)" dir="auto" style="min-width:280px;">
  <select name="soort">
    <option value="">Alle soorten</option>
    @foreach ($soorten as $waarde => $label)
      <option value="{{ $waarde }}" @selected($soortFilter === $waarde)>{{ $label }}</option>
    @endforeach
  </select>
  <select name="vakgebied">
    <option value="">Alle vakgebieden</option>
    @foreach ($vakgebieden as $v)
      <option value="{{ $v->id }}" @selected($vakgebiedFilter === $v->id)>{{ $v->naam }}</option>
    @endforeach
  </select>
  <select name="taal">
    <option value="">Alle talen</option>
    @foreach ($talen as $t)
      <option value="{{ $t->id }}" @selected($taalFilter === $t->id)>{{ $t->naam }}</option>
    @endforeach
  </select>
  <label class="sis-check-inline"><input type="checkbox" name="beschikbaar" value="1" @checked($alleenBeschikbaar)> Alleen beschikbaar</label>
  <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Zoeken</button>
  <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('catalogus') }}">Wissen</a>
</form>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Titel</th><th>ISBN</th><th>Auteur(s)</th><th>Talen</th><th>Jaar</th><th>Vakgebied</th><th>Rek</th><th style="text-align:center;">Beschikbaar</th></tr></thead>
    <tbody>
      @forelse ($publicaties as $p)

        <tr>
          <td class="nm" dir="auto"><a href="{{ route('catalogus.show', $p) }}">{{ $p->volledigeTitel() }}</a></td>
          <td class="tnum">{{ $p->isbn ?? '—' }}</td>
          <td dir="auto">{{ $p->auteursTekst() }}</td>
          <td>{{ $p->talenTekst() }}</td>
          <td class="tnum">{{ $p->uitgavejaar ?? '—' }}</td>
          <td>{{ $p->vakgebied?->naam ?? '—' }}</td>
          <td class="tnum"><b>{{ $p->rekplaats() ?? '—' }}</b></td>
          <td style="text-align:center;">
            @if (! $p->heeftExemplaren())
              <span class="sis-muted">digitaal</span>
            @elseif ($p->aantalBeschikbaar() > 0)
              <span class="iuasr-dash-status s-approved">{{ $p->aantalBeschikbaar() }} van {{ $p->exemplaren->count() }}</span>
            @else
              <span class="iuasr-dash-status s-submitted">uitgeleend</span>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="8"><div class="iuasr-dash-empty" style="border:0;"><h3>Niets gevonden</h3><p class="sis-muted">Er zijn geen titels die aan uw zoekopdracht voldoen. Probeer een deel van de titel of de naam van de auteur.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div style="margin-top:12px;">{{ $publicaties->links() }}</div>
<p class="sis-tblnote">De kolom <b>Rek</b> is de plek in de bibliotheek (bijvoorbeeld <b>F. 1070</b>): de letter is de kast, het nummer de plaats in het rek. Dit overzicht is alleen-lezen. Wilt u een boek lenen, meld u dan bij de bibliotheek; zij leggen de uitlening vast.</p>
@endsection

@include('partials.autofilter')
