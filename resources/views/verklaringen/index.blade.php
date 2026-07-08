@extends('layouts.app')

@section('titel', 'Verklaring genereren')

@push('head')
<style>
  .verk-layout { display: grid; grid-template-columns: 300px 1fr; gap: 18px; align-items: start; }
  @media (max-width: 980px) { .verk-layout { grid-template-columns: 1fr; } }
  /* Bij printen alleen het A4-document tonen. */
  @media print {
    .iuasr-dash-vhead, .verk-side { display: none !important; }
    .verk-layout { display: block !important; }
    .sis-paper-stage { padding: 0 !important; background: #fff !important; border: 0 !important; }
    .sis-a4 { box-shadow: none !important; margin: 0 auto !important; }
  }
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
  @if ($student && $verklaring)
    <div class="iuasr-dash-vhead__actions" style="gap:8px;flex-wrap:wrap;align-items:center;">
      <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="button" onclick="window.print()">Printen</button>
      <form method="POST" action="{{ route('verklaringen.genereer') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        @csrf
        <input type="hidden" name="student" value="{{ $student->id }}">
        <input type="hidden" name="type" value="{{ $type }}">
        <input type="text" name="ontvanger" required value="{{ old('ontvanger') }}" placeholder="Verstrekt aan (bv. DUO / gemeente)" style="padding:8px 10px;border:1px solid var(--stroke,#cfcfd6);border-radius:8px;min-width:220px;">
        <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Ondertekende PDF</button>
      </form>
    </div>
  @endif
</div>
@error('ontvanger')<div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $message }}</span></div>@enderror

<div class="verk-layout">
  <div class="verk-side">
    <div class="sis-card" style="margin-bottom:16px;">
      <div class="sis-card__hd"><h3>Student</h3></div>

      @if ($student)
        {{-- Gekozen student --}}
        <div class="iuasr-dash-intake" style="margin-bottom:0;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <div style="flex:1;min-width:0;">
            <b>{{ $student->volledigeNaam() }}</b>
            <small>Studentnr. {{ $student->studentnummer }}</small>
          </div>
          <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('verklaringen', ['type' => $type]) }}">Andere kiezen</a>
        </div>
      @else
        {{-- Zoekbalk: op studentnummer of naam --}}
        <form method="GET" action="{{ route('verklaringen') }}" style="display:flex;gap:8px;margin-bottom:12px;">
          <input type="hidden" name="type" value="{{ $type }}">
          <div class="search" style="position:relative;flex:1;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--blackAltText);pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op studentnummer of naam…" autofocus
              style="width:100%;height:38px;padding:7px 11px 7px 34px;font-family:inherit;font-size:13.5px;border:1px solid var(--borderColor);border-radius:4px;">
          </div>
          <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Zoek</button>
        </form>

        @if ($zoek !== '')
          <div class="sis-worklist">
            @forelse ($resultaten as $r)
              <a class="sis-work" href="{{ route('verklaringen', ['student' => $r->id, 'type' => $type]) }}">
                <span class="sis-work__ic"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                <div class="sis-work__bd"><b>{{ $r->volledigeNaam() }}</b><small>{{ $r->studentnummer }}</small></div>
                <span class="sis-work__meta">Kies →</span>
              </a>
            @empty
              <p class="sis-muted" style="font-size:13px;margin:4px 2px;">Geen studenten gevonden voor “{{ $zoek }}”.</p>
            @endforelse
          </div>
        @else
          <p class="sis-muted" style="font-size:12.5px;margin:0;">Typ een studentnummer of naam en klik op Zoek.</p>
        @endif
      @endif
    </div>

    <div class="sis-card">
      <div class="sis-card__hd"><h3>Type verklaring</h3></div>
      <div style="display:flex;flex-direction:column;gap:8px;">
        @foreach ($types as $sleutel => [$titel, $oms])
          <a class="sis-choice {{ $type === $sleutel ? 'is-selected' : '' }}" style="padding:12px 14px;"
             href="{{ route('verklaringen', array_filter(['student' => $student?->id, 'q' => $student ? null : $zoek, 'type' => $sleutel])) }}">
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
    @elseif ($financieel && $financieel['achterstand'])
      <div class="iuasr-dash-empty" style="border:0;background:#fff;width:100%;">
        <span class="iuasr-dash-empty__icon" style="background:var(--st-rejected-bg);color:var(--st-rejected-fg);"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
        <h3>Verklaring geblokkeerd</h3>
        <p>Deze student heeft een openstaande betalingsachterstand van <b>€ {{ number_format($financieel['openstaand'], 2, ',', '.') }}</b>. Het afgeven van officiële documenten en verklaringen is geblokkeerd tot de schuld is voldaan.</p>
      </div>
    @else
      <div class="sis-a4" style="min-height:auto;">
        <div class="sis-a4__head">
          <img src="{{ asset('assets/img/logo-dark.png') }}" alt="IUASR">
          <div class="org"><b>Islamic University of Applied Sciences Rotterdam</b>Bergsingel 135 · 3037 GC Rotterdam<br>Tel: +31 (0)10 485 47 21<br>szaken@iuasr.nl</div>
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
