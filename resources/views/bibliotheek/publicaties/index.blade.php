@extends('layouts.app')

@section('titel', 'Catalogus')

@section('inhoud')
@php
  $magBeheer = auth()->user()->magBibliotheekBeheren();
  $soorten = \App\Enums\PublicatieSoort::opties();
  $statussen = \App\Enums\ExemplaarStatus::opties();
@endphp

<div class="sis-crumb"><a href="{{ route('bibliotheek.dashboard') }}">Bibliotheek</a><span class="sep">›</span><b>Catalogus</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Catalogus</h1>
    <div class="summary">{{ $publicaties->total() }} {{ $publicaties->total() === 1 ? 'titel' : 'titels' }}</div>
  </div>
  <div class="iuasr-dash-vhead__actions">
    <a class="iuasr-dash-btn" href="{{ route('bibliotheek.export', request()->query()) }}">Exporteren (CSV)</a>
    @if ($magBeheer)
      <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('bibliotheek.publicaties.create') }}">Publicatie toevoegen</a>
    @endif
  </div>
</div>

<form method="GET" action="{{ route('bibliotheek.publicaties') }}" class="sis-toolbar" style="margin-bottom:12px; gap:8px; flex-wrap:wrap;" data-autofilter>
  <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op titel, auteur, ISBN of rek (F. 1070)">
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
  <select name="status">
    <option value="">Alle statussen</option>
    @foreach ($statussen as $waarde => $label)
      <option value="{{ $waarde }}" @selected($statusFilter === $waarde)>{{ $label }}</option>
    @endforeach
  </select>
  <input type="number" name="jaar" value="{{ $jaarFilter }}" placeholder="Jaar" min="1000" max="{{ date('Y') + 1 }}" style="width:90px;">
  <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Filteren</button>
  <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('bibliotheek.publicaties') }}">Wissen</a>
</form>

@if ($soortFilter || $vakgebiedFilter || $taalFilter || $statusFilter || $jaarFilter || $zoek !== '')
  <p class="sis-muted" style="margin:-6px 2px 12px; font-size:12px;">
    Actieve filters:
    @if ($zoek !== '')<b>zoekterm "{{ $zoek }}"</b>@endif
    @if ($soortFilter)<b>{{ $soorten[$soortFilter] ?? $soortFilter }}</b>@endif
    @if ($vakgebiedFilter)<b>{{ $vakgebieden->firstWhere('id', $vakgebiedFilter)?->naam }}</b>@endif
    @if ($taalFilter)<b>{{ $talen->firstWhere('id', $taalFilter)?->naam }}</b>@endif
    @if ($statusFilter)<b>{{ $statussen[$statusFilter] ?? $statusFilter }}</b>@endif
    @if ($jaarFilter)<b>jaar {{ $jaarFilter }}</b>@endif
    — <a href="{{ route('bibliotheek.publicaties') }}">alles tonen</a>
  </p>
@endif

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Titel</th><th>Rek</th><th>ISBN</th><th>Soort</th><th>Auteur(s)</th><th>Talen</th><th>Jaar</th><th>Vakgebied</th><th style="text-align:center;">Exemplaren</th><th class="row-act"></th></tr></thead>
    <tbody>
      @forelse ($publicaties as $p)
        <tr>
          <td class="nm" dir="auto"><a href="{{ route('bibliotheek.publicaties.show', $p) }}">{{ $p->volledigeTitel() }}</a></td>
          <td class="tnum"><b>{{ $p->rekplaats() ?? '—' }}</b></td>
          <td class="tnum">{{ $p->isbn ?? '—' }}</td>
          <td>{{ $p->soort->label() }}</td>
          <td>{{ $p->auteursTekst() }}</td>
          <td>{{ $p->talenTekst() }}</td>
          <td class="tnum">{{ $p->uitgavejaar ?? '—' }}</td>
          <td>{{ $p->vakgebied?->naam ?? '—' }}</td>
          <td style="text-align:center;">
            @if ($p->soort->heeftExemplaren())
              <span class="iuasr-dash-status {{ $p->aantalBeschikbaar() > 0 ? 's-approved' : 's-submitted' }}">{{ $p->aantalBeschikbaar() }} / {{ $p->exemplaren->count() }}</span>
            @else
              <span class="sis-muted">digitaal</span>
            @endif
          </td>
          <td class="row-act" style="white-space:nowrap;">
            <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('bibliotheek.publicaties.show', $p) }}">Bekijken</a>
            @if ($magBeheer)
              <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('bibliotheek.publicaties.edit', $p) }}">Bewerken</a>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="10"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen publicaties</h3><p class="sis-muted">Er zijn geen titels die aan deze filters voldoen.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div style="margin-top:12px;">{{ $publicaties->links() }}</div>
<p class="sis-tblnote">De teller bij Exemplaren toont beschikbaar / totaal. Een titel staat één keer in de catalogus; de fysieke boeken hangen eronder als exemplaren.</p>
@endsection

@include('partials.autofilter')
