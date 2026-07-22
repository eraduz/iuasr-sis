@extends('layouts.app')

@section('titel', 'Studenten')

@php
    $magInschrijven = auth()->user()->magInschrijvingBeheren();
    $vrijstellingModus = request('doel') === 'vrijstelling';
@endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Studenten</b></div>

@if ($vrijstellingModus)
  <div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-bottom:16px;display:flex;gap:8px;align-items:center;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M8.21 13.89 7 22l5-3 5 3-1.21-8.11"/></svg>
    <span>Kies een student om een <b>vrijstelling</b> vast te leggen — u komt direct bij het vrijstellingsformulier op het dossier.</span>
  </div>
@endif

<div class="iuasr-dash-vhead">
  <div>
    <h1>Alle studenten</h1>
    <div class="summary"><b>{{ $studenten->total() }}</b> {{ $status === 'alle' ? 'studenten' : \App\Enums\InschrijvingStatus::tryFrom($status)?->label().' — studenten' }}{{ $zoek !== '' ? ' · zoekterm “'.$zoek.'”' : '' }}</div>
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

<p class="sis-tblnote" style="margin:0 0 8px;">Zoeken gebeurt op <b>studentnummer</b> (niet op achternaam) — dat voorkomt verwarring bij gelijke namen (les uit het oude Access-systeem). Bladeren door de hele lijst kan met de <b>A–Z</b>-index en het <b>aantal per pagina</b> hieronder.</p>

<form method="GET" action="{{ route('studenten.index') }}">
  <input type="hidden" name="per" value="{{ $perPagina }}">
  <div class="iuasr-dash-filters">
    <div class="search" style="grid-column:1 / -1;">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op studentnummer (bijv. 261001) of naam…">
    </div>
  </div>
  <div class="iuasr-dash-filters" style="grid-template-columns:1fr 1fr;">
    <select name="status" aria-label="Status" onchange="this.form.submit()">
      <option value="actief" @selected($status==='actief')>Actieve studenten</option>
      <option value="alle" @selected($status==='alle')>Alle statussen</option>
      @foreach ($statussen as $st)
        @if ($st->value !== 'actief')
          <option value="{{ $st->value }}" @selected($status===$st->value)>{{ $st->label() }}</option>
        @endif
      @endforeach
    </select>
    <select name="opleiding" aria-label="Opleiding" onchange="this.form.submit()">
      <option value="">Alle opleidingen</option>
      @foreach ($opleidingen as $o)
        <option value="{{ $o->id }}" @selected((string) $opleidingId === (string) $o->id)>{{ $o->naam }}</option>
      @endforeach
    </select>
  </div>
</form>

@include('partials.az-index', ['route' => 'studenten.index', 'letterFilter' => $letterFilter, 'perPagina' => $perPagina])

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
        @php
          $insch = $student->inschrijvingen->first();
          $actieveInsch = $student->actieveInschrijvingen();
          $dubbel = $actieveInsch->count() > 1;
          $dubbelPill = $dubbel
            ? '<span class="sis-pill-soft" style="color:var(--heritageGroen,#285C4D);background:rgba(40,92,77,0.10);margin-left:6px;" title="Volgt twee opleidingen tegelijk">dubbele inschrijving</span>'
            : '';
          $schuldPill = in_array($student->id, $schuldIds)
            ? '<span class="sis-pill-soft" style="color:var(--secColor100);background:rgba(200,16,46,0.09);margin-left:6px;" title="Openstaande betalingsachterstand">schuld</span>'
            : '';
        @endphp
        <tr>
          <td class="tnum">{{ $student->studentnummer }}</td>
          <td class="nm">{{ $student->volledigeNaam() }}{!! $dubbelPill !!}{!! $schuldPill !!}<small>{{ $student->email ?? '—' }}</small></td>
          <td class="pg">
            @if ($dubbel)
              @foreach ($actieveInsch as $ai)
                <div>{{ $ai->opleiding?->naam ?? '—' }}</div>
              @endforeach
            @else
              {{ $insch?->opleiding?->naam ?? '—' }}
            @endif
          </td>
          <td>{{ $insch?->klas?->code ?? '—' }}</td>
          <td class="tnum">{{ $insch?->inschrijfdatum?->format('Y') ?? '—' }}</td>
          <td>
            @if ($insch?->status)
              <span class="iuasr-dash-status {{ $insch->status->badge() }}">{{ $insch->status->label() }}</span>
            @else
              <span class="iuasr-dash-status s-draft">—</span>
            @endif
          </td>
          <td class="row-act"><a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('studenten.show', $student) }}{{ $vrijstellingModus ? '#vrijstelling' : '' }}">Openen</a></td>
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
    <div class="iuasr-dash-pagination__range" style="margin:12px 2px 6px;">Toont <b>{{ $studenten->firstItem() }}–{{ $studenten->lastItem() }}</b> van <b>{{ $studenten->total() }}</b> studenten</div>
    {{ $studenten->links() }}
  @endif
</div>
@endsection
