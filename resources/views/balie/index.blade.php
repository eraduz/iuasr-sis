@extends('layouts.app')

@section('titel', 'Balielogboek')

@section('inhoud')
@php
  $magBeheer = auth()->user()->magBalieBeheren();
  $soorten = \App\Enums\BalieSoort::opties();
  $richtingen = \App\Enums\BalieRichting::opties();
@endphp

<div class="sis-crumb"><a href="{{ route('balie.dashboard') }}">Balie / Receptie</a><span class="sep">›</span><b>Logboek</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Logboek</h1>
    <div class="summary">{{ $registraties->total() }} {{ $registraties->total() === 1 ? 'registratie' : 'registraties' }}, nieuwste bovenaan</div>
  </div>
  <div class="iuasr-dash-vhead__actions">
    <a class="iuasr-dash-btn" href="{{ route('balie.export', request()->query()) }}">Exporteren (CSV)</a>
    @if ($magBeheer)
      <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('balie.create') }}">Nieuwe registratie</a>
    @endif
  </div>
</div>

<form method="GET" action="{{ route('balie') }}" class="sis-toolbar" style="margin-bottom:12px; gap:8px; flex-wrap:wrap;">
  <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op onderwerp, naam, afdeling of toelichting">
  <select name="soort">
    <option value="">Alle soorten</option>
    @foreach ($soorten as $waarde => $label)
      <option value="{{ $waarde }}" @selected($soortFilter === $waarde)>{{ $label }}</option>
    @endforeach
  </select>
  <select name="richting">
    <option value="">In- en uitgaand</option>
    @foreach ($richtingen as $waarde => $label)
      <option value="{{ $waarde }}" @selected($richtingFilter === $waarde)>{{ $label }}</option>
    @endforeach
  </select>
  <select name="medewerker">
    <option value="">Iedereen</option>
    @foreach ($medewerkers as $m)
      <option value="{{ $m->id }}" @selected($medewerkerFilter === $m->id)>{{ $m->volledigeNaam() }}</option>
    @endforeach
  </select>
  <input type="date" name="vanaf" value="{{ $vanaf }}" title="Vanaf datum">
  <input type="date" name="tot" value="{{ $tot }}" title="Tot en met datum">
  <label class="sis-check-inline"><input type="checkbox" name="aanwezig" value="1" @checked($alleenAanwezig)> Alleen nog aanwezig</label>
  <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Filteren</button>
  <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('balie') }}">Wissen</a>
</form>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Datum en tijd</th><th>Soort</th><th>Onderwerp</th><th>Naam</th><th>Bestemd voor</th><th>Toelichting</th><th class="row-act"></th></tr></thead>
    <tbody>
      @forelse ($registraties as $r)
        <tr>
          <td class="tnum">{{ $r->datum_tijd->format('d-m-Y H:i') }}</td>
          <td>
            {{ $r->soortLabel() }}
            @if ($r->isNogAanwezig())
              <span class="iuasr-dash-status s-submitted">Nog aanwezig</span>
            @endif
          </td>
          <td class="nm">{{ $r->onderwerp ?? '—' }}</td>
          <td>
            {{ $r->contact_naam }}
            @if ($r->contact_organisatie)<br><small class="sis-muted">{{ $r->contact_organisatie }}</small>@endif
          </td>
          <td>{{ $r->bestemdVoor() }}</td>
          <td>{{ \Illuminate\Support\Str::limit($r->toelichting, 60) ?: '—' }}</td>
          <td class="row-act" style="white-space:nowrap;">
            @if ($magBeheer)
              <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('balie.edit', $r) }}">Bewerken</a>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="7"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen registraties</h3><p class="sis-muted">Er zijn geen registraties die aan deze filters voldoen.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div style="margin-top:12px;">{{ $registraties->links() }}</div>
<p class="sis-tblnote">Registraties worden nooit verwijderd: het logboek is een chronologisch verantwoordingsdocument. Een fout corrigeert u met een wijziging; die wordt gelogd.</p>
@endsection
