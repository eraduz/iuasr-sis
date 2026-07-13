@extends('layouts.app')

@section('titel', 'Balie / Receptie')

@section('inhoud')
@php $magBeheer = auth()->user()->magBalieBeheren(); @endphp

<div class="sis-crumb"><b>Balie / Receptie</b><span class="sep">›</span><b>Overzicht</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Balie / Receptie</h1>
    <div class="summary">Vandaag, {{ now()->translatedFormat('l j F Y') }}</div>
  </div>
  @if ($magBeheer)
    <div class="iuasr-dash-vhead__actions">
      <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('balie.create', ['soort' => 'telefoon']) }}">Telefoongesprek</a>
      <a class="iuasr-dash-btn" href="{{ route('balie.create', ['soort' => 'bezoek']) }}">Bezoeker</a>
      <a class="iuasr-dash-btn" href="{{ route('balie.create', ['soort' => 'post']) }}">Poststuk</a>
    </div>
  @endif
</div>

<div class="iuasr-dash-stats">
  <div class="iuasr-dash-stat"><span class="lbl">Telefoon inkomend</span><span class="val">{{ $kpi['telefoon_in'] }}</span><span class="delta">vandaag</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Telefoon uitgaand</span><span class="val">{{ $kpi['telefoon_uit'] }}</span><span class="delta">vandaag</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Bezoekers</span><span class="val">{{ $kpi['bezoekers'] }}</span><span class="delta">vandaag</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Post ontvangen</span><span class="val">{{ $kpi['post_in'] }}</span><span class="delta">vandaag</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Post verzonden</span><span class="val">{{ $kpi['post_uit'] }}</span><span class="delta">vandaag</span></div>
</div>

<h2 style="margin:22px 0 10px;">Nu in het pand</h2>
<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Bezoeker</th><th>Organisatie</th><th>Afspraak met</th><th>Binnengekomen</th><th class="row-act"></th></tr></thead>
    <tbody>
      @forelse ($aanwezig as $bezoek)
        <tr>
          <td class="nm">{{ $bezoek->contact_naam }}</td>
          <td>{{ $bezoek->contact_organisatie ?? '—' }}</td>
          <td>{{ $bezoek->bestemdVoor() }}</td>
          <td class="tnum">{{ $bezoek->datum_tijd->format('H:i') }}</td>
          <td class="row-act">
            @if ($magBeheer)
              <form method="POST" action="{{ route('balie.vertrek', $bezoek) }}" style="display:inline;">
                @csrf
                <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Afmelden</button>
              </form>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="5"><div class="iuasr-dash-empty" style="border:0;"><h3>Niemand aangemeld</h3><p class="sis-muted">Er zijn op dit moment geen bezoekers in het pand.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<p class="sis-tblnote">Bezoekers blijven hier staan tot zij zijn afgemeld. Deze lijst is bedoeld als bezoekersoverzicht bij een ontruiming.</p>

<h2 style="margin:22px 0 10px;">Laatste registraties</h2>
<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Datum en tijd</th><th>Soort</th><th>Onderwerp</th><th>Naam</th><th>Bestemd voor</th></tr></thead>
    <tbody>
      @forelse ($recent as $r)
        <tr>
          <td class="tnum">{{ $r->datum_tijd->format('d-m-Y H:i') }}</td>
          <td>{{ $r->soortLabel() }}</td>
          <td class="nm">{{ $r->onderwerp ?? '—' }}</td>
          <td>{{ $r->contact_naam }}</td>
          <td>{{ $r->bestemdVoor() }}</td>
        </tr>
      @empty
        <tr><td colspan="5"><div class="iuasr-dash-empty" style="border:0;"><h3>Nog geen registraties</h3><p class="sis-muted">Zodra u een gesprek, bezoek of poststuk vastlegt, verschijnt het hier.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div style="margin-top:12px;"><a class="iuasr-dash-btn" href="{{ route('balie') }}">Naar het volledige logboek</a></div>
@endsection
