@extends('layouts.app')

@section('titel', 'Studenten')

@php $magInschrijven = auth()->user()->magInschrijvingBeheren(); @endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Studenten</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Studenten</h1>
    <div class="summary"><b>{{ $studenten->total() }}</b> studenten · zoek op <b>studentnummer</b></div>
  </div>
  <div class="iuasr-dash-vhead__actions">
    @unless ($magInschrijven)
      <span class="sis-role-note"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg> Alleen-lezen</span>
    @endunless
    @if ($magInschrijven)
      <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('inschrijven') }}">Student inschrijven</a>
    @endif
  </div>
</div>

<form method="GET" action="{{ route('studenten.index') }}" class="iuasr-dash-filters">
  <div class="search" style="grid-column:1 / -1;">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op studentnummer (bijv. 261001) of naam…">
  </div>
</form>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead>
      <tr>
        <th style="width:110px;">Studentnr.</th>
        <th>Naam</th>
        <th>Opleiding</th>
        <th>Klas</th>
        <th>Cohort</th>
        <th>Status</th>
        <th class="row-act"></th>
      </tr>
    </thead>
    <tbody>
      @forelse ($studenten as $student)
        @php $insch = $student->inschrijvingen->first(); @endphp
        <tr>
          <td class="tnum">{{ $student->studentnummer }}</td>
          <td class="nm">{{ $student->volledigeNaam() }}<small>{{ $student->email ?? '—' }}</small></td>
          <td class="pg">{{ $insch?->opleiding?->naam ?? '—' }}</td>
          <td>{{ $insch?->klas?->code ?? '—' }}</td>
          <td class="tnum">{{ $insch?->inschrijfdatum?->format('Y') ?? '—' }}</td>
          <td>
            @if ($insch?->status)
              <span class="iuasr-dash-status {{ $insch->status->badge() }}">{{ $insch->status->label() }}</span>
            @else
              <span class="iuasr-dash-status s-draft">—</span>
            @endif
          </td>
          <td class="row-act"><a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('studenten.show', $student) }}">Openen</a></td>
        </tr>
      @empty
        <tr><td colspan="7" style="padding:0;">
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
<p class="sis-tblnote">Zoeken gebeurt op studentnummer (niet op achternaam) — dat voorkomt verwarring bij gelijke namen (les uit het oude Access-systeem).</p>
@endsection
