@extends('layouts.app')

@section('titel', 'Rapporten')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Rapporten</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Rapporten</h1>
    <div class="summary">Genereer een nette, printbare lijst — de web-opvolger van de oude Access-rapporten</div>
  </div>
</div>

<div class="sis-card" style="margin-bottom:18px;">
  <div class="sis-card__hd"><h3>1 · Kies rapporttype</h3></div>
  <div class="sis-choicegrid">
    <a class="sis-choice is-selected" href="{{ route('rapporten.alumni') }}">
      <span class="sis-choice__ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1 2 3 6 3s6-2 6-3v-5"/></svg></span>
      <h4>Alumni</h4>
      <p>Afgestudeerde studenten met contactgegevens: naam, telefoon en e-mail.</p>
      <span class="tag">Studentenzaken &amp; Directie</span>
    </a>
    @if (auth()->user()->magInschrijvingBeheren())
    <a class="sis-choice" href="{{ route('rapporten.actieve-studenten') }}">
      <span class="sis-choice__ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 13l2 2 4-4"/></svg></span>
      <h4>Actieve studenten (Excel)</h4>
      <p>Alle actief ingeschreven studenten met alle gegevens, inclusief IBAN voor boekhouding en facturatie. Zonder BSN.</p>
      <span class="tag">Excel · incl. IBAN · zonder BSN</span>
    </a>
    @endif
    @if (auth()->user()->magInschrijvingBeheren())
    <div class="sis-choice">
      <span class="sis-choice__ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
      <h4>Klassenlijst</h4>
      <p>Alle studenten per klas met studentnummer en status. Voor presentie en administratie.</p>
      <span class="tag">geen cijfers · beschikbaar</span>
    </div>
    @endif
    <div class="sis-choice" style="opacity:.55;">
      <span class="sis-choice__ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>
      <h4>Examen-/tentamenlijst</h4>
      <p>Deelnemers en resultaten per toets.</p>
      <span class="tag">volgt in Fase 5</span>
    </div>
    <div class="sis-choice" style="opacity:.55;">
      <span class="sis-choice__ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><rect x="7" y="10" width="3" height="8"/><rect x="12" y="6" width="3" height="12"/></svg></span>
      <h4>EC-rapport</h4>
      <p>Behaalde studiepunten per student en opleiding.</p>
      <span class="tag">volgt in Fase 5</span>
    </div>
    @if (auth()->user()->magCijfersInzien())
    <a class="sis-choice" href="{{ route('cijferlijst') }}">
      <span class="sis-choice__ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg></span>
      <h4>Cijferlijst</h4>
      <p>Officieel cijferoverzicht per student (transcript) — te downloaden als ondertekende PDF.</p>
      <span class="tag">Examencommissie &amp; Directie</span>
    </a>
    @else
    <div class="sis-choice" style="opacity:.55;">
      <span class="sis-choice__ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span>
      <h4>Cijferrapport</h4>
      <p>Cijferoverzicht per student — voorbehouden aan cijfer-inzage (Examencommissie/Directie).</p>
      <span class="tag">geen cijferinzage voor deze rol</span>
    </div>
    @endif
  </div>
</div>

@if (auth()->user()->magInschrijvingBeheren())
<form method="GET" action="{{ route('rapporten.klassenlijst') }}" class="sis-grid-2--even">
  <div class="sis-card sis-form">
    <div class="sis-card__hd"><h3>2 · Selectie</h3></div>
    <div class="sis-fld"><label>Opleiding</label>
      <select name="opleiding_id"><option value="">Alle opleidingen</option>
        @foreach ($opleidingen as $o)<option value="{{ $o->id }}">{{ $o->naam }}</option>@endforeach
      </select>
    </div>
    <div class="sis-fld-row sis-fld-row--2">
      <div class="sis-fld"><label>Periode</label>
        <select name="periode_id"><option value="">Alle perioden</option>
          @foreach ($perioden as $p)<option value="{{ $p->id }}" @selected($p->actief)>{{ $p->naam }}</option>@endforeach
        </select>
      </div>
      <div class="sis-fld"><label>Klas</label>
        <select name="klas_id"><option value="">Alle klassen</option>
          @foreach ($klassen as $k)<option value="{{ $k->id }}">{{ $k->code }} · {{ $k->opleiding?->code }}</option>@endforeach
        </select>
      </div>
    </div>
  </div>
  <div class="sis-card sis-form">
    <div class="sis-card__hd"><h3>3 · Opties &amp; genereren</h3></div>
    <label class="sis-check-inline" style="margin-bottom:14px;"><input type="checkbox" name="alleen_actief" value="1" checked> Alleen actieve studenten</label>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Klassenlijst tonen</button>
    </div>
    <p class="sis-tblnote" style="margin-top:12px;">De klassenlijst bevat <b>geen cijfers</b> en is beschikbaar voor Studentenzaken.</p>
  </div>
</form>
@endif
@endsection
