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
    <div class="sis-choice is-selected">
      <span class="sis-choice__ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
      <h4>Klassenlijst</h4>
      <p>Alle studenten per klas met studentnummer en status. Voor presentie en administratie.</p>
      <span class="tag">geen cijfers · beschikbaar</span>
    </div>
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
    <div class="sis-choice" style="opacity:.55;">
      <span class="sis-choice__ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span>
      <h4>Cijferrapport</h4>
      <p>Volledig cijferoverzicht per student (transcript).</p>
      <span class="tag">volgt in Fase 5</span>
    </div>
  </div>
</div>

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
@endsection
