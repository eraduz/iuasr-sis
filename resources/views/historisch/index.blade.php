@extends('layouts.app')

@section('titel', 'Historisch dossier')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Historisch dossier</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Historisch studentdossier</h1>
    <div class="summary">
      @if ($opleiding)
        Alleen-lezen cijferlijsten uit het oude Access-systeem ({{ $opleiding->naam }}).
        @if ($studenten instanceof \Illuminate\Contracts\Pagination\Paginator || $studenten instanceof \Illuminate\Pagination\LengthAwarePaginator)
          <b>{{ $studenten->total() }}</b> studenten{{ $zoek !== '' ? ' · zoekterm “'.$zoek.'”' : '' }}.
        @endif
      @else
        Er is nog geen gemigreerde historische data.
      @endif
    </div>
  </div>
  <div class="iuasr-dash-vhead__actions">
    <span class="sis-role-note"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg> Alleen-lezen</span>
  </div>
</div>

@if (! $opleiding)
  <div class="iuasr-dash-empty">
    <span class="iuasr-dash-empty__icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg></span>
    <h3>Nog geen historische data</h3>
    <p>De migratie uit de oude Access-database is nog niet uitgevoerd. Zodra studenten, vakken en cijfers zijn geïmporteerd, verschijnen de dossiers hier.</p>
  </div>
@else
  <form method="GET" action="{{ route('historisch.index') }}">
    <div class="iuasr-dash-filters">
      <div class="search" style="grid-column:1 / -1;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op studentnummer (bijv. 131516) of naam…">
      </div>
    </div>
  </form>

  <div class="iuasr-dash-tbl-card">
    <table class="iuasr-dash-tbl">
      <thead>
        <tr>
          <th style="width:120px;">Studentnr.</th>
          <th>Naam</th>
          <th style="width:150px;">Gemigreerde cijfers</th>
          <th class="row-act"></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($studenten as $student)
          <tr>
            <td class="tnum">{{ $student->studentnummer }}</td>
            <td class="nm">{{ $student->volledigeNaam() }}<small>{{ $student->email ?? '—' }}</small></td>
            <td class="tnum">{{ $student->historische_resultaten_count }}</td>
            <td class="row-act"><a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('historisch.show', $student) }}">Cijferlijst</a></td>
          </tr>
        @empty
          <tr><td colspan="4" style="padding:0;">
            <div class="iuasr-dash-empty" style="border:0;">
              <span class="iuasr-dash-empty__icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
              <h3>Geen studenten gevonden</h3>
              <p>Pas de zoekopdracht aan{{ $zoek !== '' ? ' — er is gezocht op “'.$zoek.'”' : '' }}.</p>
            </div>
          </td></tr>
        @endforelse
      </tbody>
    </table>
    @if ($studenten->hasPages())
      <div class="iuasr-dash-pagination">
        <div class="iuasr-dash-pagination__range">Toont <b>{{ $studenten->firstItem() }}–{{ $studenten->lastItem() }}</b> van <b>{{ $studenten->total() }}</b></div>
        <div class="iuasr-dash-pagination__nav">
          <a href="{{ $studenten->previousPageUrl() ?: '#' }}"><button {{ $studenten->onFirstPage() ? 'disabled' : '' }}>‹</button></a>
          <button class="is-current">{{ $studenten->currentPage() }}</button>
          <span style="color:var(--blackAltText);font-size:12px;">van {{ $studenten->lastPage() }}</span>
          <a href="{{ $studenten->nextPageUrl() ?: '#' }}"><button {{ $studenten->hasMorePages() ? '' : 'disabled' }}>›</button></a>
        </div>
      </div>
    @endif
  </div>
  <p class="sis-tblnote">Zoeken gebeurt op studentnummer (niet op achternaam) — dat voorkomt verwarring bij gelijke namen. Deze dossiers zijn gemigreerd uit het oude Access-systeem en zijn alleen-lezen.</p>
@endif
@endsection
