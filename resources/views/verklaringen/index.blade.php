@extends('layouts.app')

@section('titel', 'Verklaring genereren')

@push('head')
<style>
  .verk-layout { display: grid; grid-template-columns: 300px 1fr; gap: 18px; align-items: start; }
  @media (max-width: 980px) { .verk-layout { grid-template-columns: 1fr; } }
</style>
@endpush

@php
  $huidige = $student?->inschrijvingen->sortByDesc('inschrijfdatum')->first();
  $types = ['studentbewijs' => ['Studentbewijs','Bevestiging van inschrijving voor dit studiejaar.'],
            'vertraging' => ['Studievertraging','Verklaring t.b.v. DUO / gemeente bij vertraging.'],
            'afstudeerfase' => ['Afstudeerfase','Bevestiging dat de student in de afstudeerfase zit.']];
@endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Verklaringen</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Verklaring genereren</h1>
    <div class="summary">Officiële verklaring op IUASR-briefpapier · A4 · bevat geen cijfers of BSN</div>
  </div>
  @if ($student)
    <div class="iuasr-dash-vhead__actions">
      <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="button" onclick="window.print()">Printen / PDF</button>
    </div>
  @endif
</div>

<div class="verk-layout">
  <div>
    <form method="GET" action="{{ route('verklaringen') }}" class="sis-card" style="margin-bottom:16px;">
      <div class="sis-card__hd"><h3>Student</h3></div>
      <div class="sis-fld" style="margin-bottom:10px;">
        <select name="student" onchange="this.form.submit()">
          <option value="">— kies student —</option>
          @foreach ($studenten as $s)
            <option value="{{ $s->id }}" @selected($student && $student->id === $s->id)>{{ $s->studentnummer }} — {{ $s->volledigeNaam() }}</option>
          @endforeach
        </select>
      </div>
      <input type="hidden" name="type" value="{{ $type }}">
    </form>

    <div class="sis-card">
      <div class="sis-card__hd"><h3>Type verklaring</h3></div>
      <div style="display:flex;flex-direction:column;gap:8px;">
        @foreach ($types as $sleutel => [$titel, $oms])
          <a class="sis-choice {{ $type === $sleutel ? 'is-selected' : '' }}" style="padding:12px 14px;"
             href="{{ route('verklaringen', array_filter(['student' => $student?->id, 'type' => $sleutel])) }}">
            <h4 style="margin:0 0 3px;font-size:14px;">{{ $titel }}</h4>
            <p>{{ $oms }}</p>
          </a>
        @endforeach
      </div>
    </div>
    <div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-top:16px;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg>
      <span>Verklaringen bevatten <b>geen cijfers</b> en geen BSN. Uitgifte wordt gelogd in de audit-log.</span>
    </div>
  </div>

  <div class="sis-paper-stage" style="padding:24px;">
    @if (! $student)
      <div class="iuasr-dash-empty" style="border:0;background:#fff;width:100%;">
        <span class="iuasr-dash-empty__icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M8.21 13.89 7 22l5-3 5 3-1.21-8.11"/></svg></span>
        <h3>Kies een student</h3>
        <p>Selecteer links een student en het type verklaring; het document verschijnt hier.</p>
      </div>
    @else
      <div class="sis-a4" style="min-height:auto;">
        <div class="sis-a4__head">
          <img src="{{ asset('assets/img/logo-dark.png') }}" alt="IUASR">
          <div class="org"><b>Islamic University of Applied Sciences Rotterdam</b>Bureau Studentenzaken<br>Postbus 12345 · 3000 AB Rotterdam<br>studentenzaken@iuasr.nl</div>
        </div>
        <h1>{{ $verklaring['title'] }}</h1>
        <p class="doc-sub">{{ $verklaring['sub'] }}</p>
        <p>Hierbij verklaart Bureau Studentenzaken van de Islamic University of Applied Sciences Rotterdam dat:</p>
        <dl class="kv" style="margin:14px 0 18px;">
          <dt>Naam</dt><dd>{{ $student->volledigeNaam() }}</dd>
          <dt>Studentnummer</dt><dd>{{ $student->studentnummer }}</dd>
          <dt>Geboortedatum</dt><dd>{{ $student->geboortedatum?->format('d-m-Y') ?? '—' }}</dd>
          <dt>Opleiding</dt><dd>{{ $verklaring['opleiding'] }}</dd>
        </dl>
        <p>{{ $verklaring['body'] }}</p>
        <p>{{ $verklaring['body2'] }}</p>
        <div class="sis-a4__sig">
          <div class="line">Namens Bureau Studentenzaken<br><b style="color:var(--priColor100);">{{ auth()->user()->naam }}</b> · medewerker Studentenzaken</div>
          <div class="line" style="text-align:right;border-top:0;padding-top:0;">
            <span class="sis-a4__stamp"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg> Gewaarmerkt IUASR</span>
          </div>
        </div>
        <div class="sis-a4__foot">
          <span>Referentie: {{ $verklaring['ref'] }} · Rotterdam, {{ now()->format('d-m-Y') }}</span>
          <span class="sis-a4__watermark">Officieel document</span>
        </div>
      </div>
    @endif
  </div>
</div>
@endsection
