@extends('layouts.app')

@section('titel', 'Dashboard')

@php $rol = auth()->user()->rol; @endphp

@section('inhoud')

@if ($rol === App\Enums\Rol::Studentenzaken)
  <div class="iuasr-dash-vhead">
    <div>
      <h1>Studentenzaken</h1>
      <div class="summary">Identiteit &amp; inschrijving · <b>geen</b> cijferinzage voor deze rol</div>
    </div>
    <div class="iuasr-dash-vhead__actions">
      <a class="iuasr-dash-btn" href="{{ route('collegegeld') }}">Collegegeld</a>
      <a class="iuasr-dash-btn" href="{{ route('verklaringen') }}">Verklaring opstellen</a>
      <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('inschrijven') }}">Student inschrijven</a>
    </div>
  </div>

  <div class="iuasr-dash-stats">
    <div class="iuasr-dash-stat"><span class="lbl">Studenten</span><span class="val">{{ $kpi['studenten'] }}</span><span class="delta">in het systeem</span></div>
    <div class="iuasr-dash-stat"><span class="lbl">Actieve inschrijvingen</span><span class="val">{{ $kpi['inschrijvingen'] }}</span><span class="delta">huidige periode</span></div>
    <div class="iuasr-dash-stat"><span class="lbl">Vakken</span><span class="val">{{ $kpi['vakken'] }}</span><span class="delta">referentiedata</span></div>
    <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Databron</span><span class="val" style="font-size:15px;line-height:2;">Synthetisch</span><span class="delta">AVG-veilig</span></div>
  </div>

  <div class="iuasr-dash-alert iuasr-dash-alert--info">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    <span>Als Studentenzaken heeft u <b>geen inzage in cijfers</b>. Dit wordt server-side afgedwongen (rolscheiding), niet alleen in de interface.</span>
  </div>

@elseif ($rol === App\Enums\Rol::Financien)
  <div class="iuasr-dash-vhead">
    <div>
      <h1>Financiële Administratie</h1>
      <div class="summary">Collegegeldbetalingen registreren en betalingsachterstanden bewaken</div>
    </div>
    <div class="iuasr-dash-vhead__actions">
      <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('financien') }}">Betalingen &amp; achterstand</a>
    </div>
  </div>
  <div class="iuasr-dash-alert iuasr-dash-alert--info">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
    <span>U registreert betalingen per student. Het systeem bepaalt automatisch de achterstand; studenten met een schuld worden gesignaleerd bij Studentenzaken.</span>
  </div>

@elseif ($rol === App\Enums\Rol::Docent)
  <div class="iuasr-dash-vhead">
    <div>
      <h1>Docent</h1>
      <div class="summary">Cijferinvoer voor <b>uw eigen vakken</b></div>
    </div>
    <div class="iuasr-dash-vhead__actions">
      <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('cijferinvoer') }}">Cijfers invoeren</a>
    </div>
  </div>
  <div class="iuasr-dash-alert iuasr-dash-alert--info">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <span>U ziet en muteert <b>alleen uw eigen vakken</b>. Andere vakken en persoonsdossiers zijn niet toegankelijk.</span>
  </div>

@elseif ($rol === App\Enums\Rol::Examencommissie || $rol === App\Enums\Rol::Directie)
  <div class="iuasr-dash-vhead">
    <div>
      <h1>{{ $rol->label() }}</h1>
      <div class="summary">Volledige inzage in resultaten · wijzigen strikt &amp; gelogd</div>
    </div>
    <div class="iuasr-dash-vhead__actions">
      <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('cijferoverzicht') }}">Cijferoverzicht</a>
    </div>
  </div>
  <div class="iuasr-dash-stats">
    <div class="iuasr-dash-stat"><span class="lbl">Studenten</span><span class="val">{{ $kpi['studenten'] }}</span><span class="delta">in het systeem</span></div>
    <div class="iuasr-dash-stat"><span class="lbl">Vakken</span><span class="val">{{ $kpi['vakken'] }}</span><span class="delta">referentiedata</span></div>
    <div class="iuasr-dash-stat"><span class="lbl">Actieve inschrijvingen</span><span class="val">{{ $kpi['inschrijvingen'] }}</span><span class="delta">huidige periode</span></div>
    <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Databron</span><span class="val" style="font-size:15px;line-height:2;">Synthetisch</span><span class="delta">AVG-veilig</span></div>
  </div>

@elseif ($rol === App\Enums\Rol::Beheerder)
  <div class="iuasr-dash-vhead">
    <div>
      <h1>Beheer</h1>
      <div class="summary">Gebruikers, rollen en referentiedata · audit-logging actief</div>
    </div>
    <div class="iuasr-dash-vhead__actions">
      <a class="iuasr-dash-btn" href="{{ route('audit-log') }}">Audit-log</a>
      <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('gebruikers') }}">Gebruikers beheren</a>
    </div>
  </div>
  <div class="iuasr-dash-stats">
    <div class="iuasr-dash-stat"><span class="lbl">Gebruikers</span><span class="val">{{ $kpi['gebruikers'] ?? '—' }}</span><span class="delta">5 rollen</span></div>
    <div class="iuasr-dash-stat"><span class="lbl">Studenten</span><span class="val">{{ $kpi['studenten'] }}</span><span class="delta">synthetisch</span></div>
    <div class="iuasr-dash-stat"><span class="lbl">Vakken</span><span class="val">{{ $kpi['vakken'] }}</span><span class="delta">referentiedata</span></div>
    <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Audit-events</span><span class="val">{{ $kpi['audit'] ?? 0 }}</span><span class="delta">volledig gelogd</span></div>
  </div>
@endif

<div class="iuasr-dash-alert iuasr-dash-alert--warn" style="margin-top:16px;">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
  <span><b>Fase 3 — kern-CRUD (in opbouw).</b> Studentenlijst, studentdetail en inschrijven werken. Cijfers (Fase 4) en de overige schermen volgen. Alle data is synthetisch.</span>
</div>

@endsection
