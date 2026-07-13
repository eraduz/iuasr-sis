@extends('layouts.app')

@section('titel', 'Artikelen zoeken')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('bibliotheek.dashboard') }}">Bibliotheek</a><span class="sep">›</span><b>Artikelen</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Artikelen zoeken</h1>
    <div class="summary">Zoek op artikeltitel, auteur, trefwoord of tijdschriftnaam — u ziet meteen in welk tijdschrift het artikel staat.</div>
  </div>
</div>

<form method="GET" action="{{ route('bibliotheek.artikelen') }}" class="sis-toolbar" style="margin-bottom:12px; gap:8px; flex-wrap:wrap;">
  <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op titel, auteur of trefwoord" dir="auto">
  <select name="tijdschrift">
    <option value="">Alle tijdschriften</option>
    @foreach ($tijdschriften as $t)
      <option value="{{ $t->id }}" @selected($tijdschriftFilter === $t->id)>{{ $t->titel }}</option>
    @endforeach
  </select>
  <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Zoeken</button>
  <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('bibliotheek.artikelen') }}">Wissen</a>
</form>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Artikel</th><th>Auteur(s)</th><th>Staat in</th><th>Pagina's</th><th>Trefwoorden</th></tr></thead>
    <tbody>
      @forelse ($artikelen as $a)
        <tr>
          <td class="nm" dir="auto">{{ $a->titel }}</td>
          <td>{{ $a->auteurs->pluck('naam')->implode(', ') ?: '—' }}</td>
          <td>
            <a href="{{ route('bibliotheek.uitgaven.show', $a->uitgave) }}">{{ $a->uitgave->tijdschrift->titel }}</a>
            <br><small class="sis-muted">uitgave {{ $a->uitgave->uitgavenummer }}@if ($a->uitgave->jaar) ({{ $a->uitgave->jaar }})@endif</small>
          </td>
          <td class="tnum">{{ $a->paginas ?? '—' }}</td>
          <td>{{ $a->trefwoorden ?? '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="5"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen artikelen</h3><p class="sis-muted">Er zijn geen artikelen die aan deze zoekopdracht voldoen.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div style="margin-top:12px;">{{ $artikelen->links() }}</div>
@endsection
