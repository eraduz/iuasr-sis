@extends('layouts.app')

@section('titel', 'Cijferinvoer · '.$vak->code)

@php
  use App\Enums\CijferlijstStatus;
  $u = auth()->user();
  $isDocent = $u->rolIs('docent');
  $isExamen = $u->rolIs('examencommissie');
  $terug = $isDocent ? route('mijn-vakken') : route('cijferoverzicht');
  $status = $lijst->status;
  $correctieMode = $isExamen && $status === CijferlijstStatus::Vastgesteld;
@endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ $terug }}">{{ auth()->user()->rolIs('docent') ? 'Mijn vakken' : 'Cijferoverzicht' }}</a><span class="sep">›</span><b>{{ $vak->code }}</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>{{ $vak->naam }}</h1>
    <div class="summary"><b>{{ $vak->code }}</b> · {{ $vak->opleiding?->naam }} · {{ $vak->ec }} EC · {{ $rijen->count() }} studenten</div>
  </div>
  <div class="iuasr-dash-vhead__actions" style="gap:8px;align-items:center;">
    <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('vakken.tentamenlijst', $vak) }}">Tentamenlijst</a>
    <span class="iuasr-dash-status {{ $status->badge() }}">{{ $status->label() }}</span>
    @unless ($magInvoeren)<span class="sis-role-note"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg> Alleen-lezen</span>@endunless
  </div>
</div>

<div class="sis-card" style="margin-bottom:16px;">
  <div class="sis-card__hd"><h3>Toetsopbouw</h3><span class="hint">Weging telt op tot 100%</span></div>
  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    @foreach ($vak->toetsonderdelen as $od)
      <span class="sis-pill-soft" style="font-size:12px;padding:5px 12px;">{{ $od->naam }} · <b style="color:var(--priColor100);">{{ rtrim(rtrim(number_format($od->weging*100,0),'0'),'.') }}%</b></span>
    @endforeach
    <span class="sis-pill-soft" style="font-size:12px;padding:5px 12px;">Cesuur · <b style="color:var(--priColor100);">{{ number_format($grens,1,',','') }}</b></span>
  </div>
</div>

@if ($vak->toetsonderdelen->isEmpty())
  <div class="iuasr-dash-alert iuasr-dash-alert--warn" style="margin-bottom:16px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <span>Dit vak heeft nog <b>geen toetsonderdelen</b>. Cijfers kunnen pas worden ingevoerd zodra de toetsopbouw is vastgelegd.</span>
  </div>
@endif

<form id="cijfergrid" method="POST" action="{{ route('vakken.cijfers.opslaan', $vak) }}">
  @csrf
  <div class="iuasr-dash-tbl-card">
    <table class="iuasr-dash-tbl" id="grade-tbl">
      <thead>
        <tr>
          <th style="width:190px;">Student</th>
          @foreach ($vak->toetsonderdelen as $od)
            <th style="text-align:center;">{{ $od->naam }}<br><span class="sis-weegcell">{{ rtrim(rtrim(number_format($od->weging*100,0),'0'),'.') }}% · 1e / herk.</span></th>
          @endforeach
          <th style="text-align:center;">Vrijstelling</th>
          <th style="text-align:right;">Eindcijfer</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($rijen as $rij)
          @php $insch = $rij['inschrijving']; $student = $rij['student']; $eind = $rij['eind']; @endphp
          <tr data-insch="{{ $insch->id }}">
            <td class="nm">{{ $student->volledigeNaam() }}<small>{{ $student->studentnummer }}</small></td>
            @foreach ($vak->toetsonderdelen as $od)
              @php
                $res = $rij['resultaten'][$od->id] ?? [];
                $p1 = ($res['tentamen'] ?? null) && $res['tentamen']->cijfer !== null ? number_format($res['tentamen']->cijfer,1,',','') : '';
                $ph = ($res['herkansing'] ?? null) && $res['herkansing']->cijfer !== null ? number_format($res['herkansing']->cijfer,1,',','') : '';
              @endphp
              <td style="text-align:center;">
                <div class="sis-od-cell" style="display:flex;flex-direction:column;gap:3px;align-items:center;">
                  <input class="sis-grade-input g1" inputmode="decimal" title="1e poging" name="cijfer[{{ $insch->id }}][{{ $od->id }}]" value="{{ $p1 }}" placeholder="1e" {{ $magInvoeren ? '' : 'disabled' }}>
                  <input class="sis-grade-input gh" inputmode="decimal" title="Herkansing" name="herkansing[{{ $insch->id }}][{{ $od->id }}]" value="{{ $ph }}" placeholder="herk." style="opacity:.9;" {{ $magInvoeren ? '' : 'disabled' }}>
                </div>
              </td>
            @endforeach
            <td style="text-align:center;">
              <label class="sis-check-inline" style="justify-content:center;"><input type="checkbox" class="vr-check" name="vrijstelling[{{ $insch->id }}]" value="1" @checked($rij['vrijstelling']) {{ $magInvoeren ? '' : 'disabled' }}></label>
            </td>
            <td style="text-align:right;" class="final">
              @if ($eind['status']==='vr')<span class="sis-pill-soft">VR</span>
              @elseif ($eind['status']==='cijfer')<span class="sis-grade-final {{ $eind['cijfer']<$grens ? 'is-fail' : '' }}">{{ number_format($eind['cijfer'],1,',','') }}</span>
              @elseif ($eind['status']==='onvolledig')<span class="sis-muted" style="font-size:12px;">onvolledig</span>
              @else<span class="sis-muted">—</span>@endif
            </td>
          </tr>
        @empty
          <tr><td colspan="{{ $vak->toetsonderdelen->count() + 3 }}"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen deelnemers</h3><p>Er zijn geen actieve studenten voor dit vak in de huidige periode.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

</form>

@if ($lijst->opmerking && $status === CijferlijstStatus::Concept)
  <div class="iuasr-dash-alert iuasr-dash-alert--warn" style="margin:12px 0;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <span><b>Teruggestuurd door de examencommissie:</b> {{ $lijst->opmerking }}</span>
  </div>
@endif

{{-- Workflow-formulieren buiten het grid-formulier (form-attribuut koppelt de knoppen) --}}
@if ($isDocent && $status === CijferlijstStatus::Concept)
  <form id="indienenForm" method="POST" action="{{ route('vakken.cijfers.indienen', $vak) }}" hidden>@csrf</form>
@endif
@if ($isExamen && $status === CijferlijstStatus::Ingediend)
  <form id="vaststellenForm" method="POST" action="{{ route('vakken.cijfers.vaststellen', $vak) }}" hidden>@csrf</form>
@endif

@if ($rijen->isNotEmpty())
  <div class="sis-savebar">
    <span class="status">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
      @if ($magInvoeren)
        {{ $correctieMode ? 'Correctie op een vastgestelde lijst wordt gelogd.' : 'Eindcijfer wordt live berekend; wijzigingen worden gelogd.' }}
      @else
        Vergrendeld — status {{ $status->label() }}.
      @endif
    </span>
    <span class="grow"></span>
    @if ($magInvoeren)
      <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit" form="cijfergrid">{{ $correctieMode ? 'Correctie opslaan' : 'Cijfers opslaan' }}</button>
      @if ($isDocent && $status === CijferlijstStatus::Concept)
        <button class="iuasr-dash-btn" type="submit" form="indienenForm">Indienen bij examencommissie</button>
      @endif
      @if ($isExamen && $status === CijferlijstStatus::Ingediend)
        <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit" form="vaststellenForm">Vaststellen</button>
      @endif
    @endif
  </div>

  @if ($isExamen && $status === CijferlijstStatus::Ingediend)
    <form method="POST" action="{{ route('vakken.cijfers.terugsturen', $vak) }}" class="sis-card sis-form" style="margin-top:12px;">
      @csrf
      <div class="sis-card__hd"><h3>Terugsturen naar docent</h3></div>
      <div class="sis-fld"><textarea name="opmerking" placeholder="Reden / opmerking voor de docent (optioneel)"></textarea></div>
      <div style="display:flex;justify-content:flex-end;"><button class="iuasr-dash-btn iuasr-dash-btn--danger" type="submit">Terugsturen naar docent</button></div>
    </form>
  @endif
@endif

<p class="sis-tblnote">Per onderdeel vult u de <b>1e poging</b> en (indien van toepassing) de <b>herkansing</b> in; de <b>beste</b> van beide telt mee. Eindcijfer = gewogen gemiddelde van de deelresultaten (1 decimaal). Bij <b>Vrijstelling</b> vervallen de deelvelden en geldt “VR”. EC worden toegekend als álle meetellende onderdelen voldoende zijn.</p>

@push('scripts')
<script>
  var WEGING = @json($vak->toetsonderdelen->pluck('weging')->map(fn($w)=>(float)$w)->values());
  var CESUUR = {{ $grens }};

  function num(el){ var v = parseFloat((el.value || '').replace(',', '.')); return isNaN(v) ? null : v; }

  function calcRow(tr) {
    var vr = tr.querySelector('.vr-check').checked;
    var cells = tr.querySelectorAll('.sis-od-cell');
    var finalCell = tr.querySelector('.final');
    tr.querySelectorAll('.sis-grade-input').forEach(function (i) { i.disabled = vr || i.dataset.locked === '1'; i.classList.remove('is-pass','is-fail'); });
    if (vr) { finalCell.innerHTML = '<span class="sis-pill-soft">VR</span>'; return; }

    var som = 0, gew = 0, compleet = true, any = false;
    cells.forEach(function (cell, idx) {
      var g1 = cell.querySelector('.g1'), gh = cell.querySelector('.gh');
      var v1 = num(g1), vh = num(gh);
      if (v1 !== null) g1.classList.add(v1 >= CESUUR ? 'is-pass' : 'is-fail');
      if (vh !== null) gh.classList.add(vh >= CESUUR ? 'is-pass' : 'is-fail');
      // Beste poging telt mee voor het eindcijfer.
      var best = (v1 === null) ? vh : (vh === null ? v1 : Math.max(v1, vh));
      if (best !== null) { som += best * WEGING[idx]; gew += WEGING[idx]; any = true; }
      else { compleet = false; }
    });
    if (!any) { finalCell.innerHTML = '<span class="sis-muted">—</span>'; return; }
    if (!compleet) { finalCell.innerHTML = '<span class="sis-muted" style="font-size:12px;">onvolledig</span>'; return; }
    var f = Math.round((som / gew) * 10) / 10;
    finalCell.innerHTML = '<span class="sis-grade-final' + (f < CESUUR ? ' is-fail' : '') + '">' + f.toFixed(1).replace('.', ',') + '</span>';
  }

  document.querySelectorAll('#grade-tbl tbody tr[data-insch]').forEach(function (tr) {
    tr.querySelectorAll('.sis-grade-input').forEach(function (i) {
      i.dataset.locked = i.disabled ? '1' : '0';
      i.addEventListener('input', function () { calcRow(tr); });
    });
    var vr = tr.querySelector('.vr-check');
    if (vr) vr.addEventListener('change', function () { calcRow(tr); });
  });
</script>
@endpush
@endsection
