@extends('layouts.app')

@section('titel', 'Aanwezigheid · '.$vak->code)

@php
  $u = auth()->user();
  $isDocent = $u->rolIs('docent');
  $terug = $isDocent ? route('mijn-vakken') : route('presentieoverzicht');
  $normStandaard = (int) round(config('sis.presentie.norm') * 100);
  $normRegeling = (int) round(config('sis.presentie.norm_regeling') * 100);
@endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ $terug }}">{{ $isDocent ? 'Mijn vakken' : 'Aanwezigheidsoverzicht' }}</a><span class="sep">›</span><b>Aanwezigheid {{ $vak->code }}</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Aanwezigheid — {{ $vak->naam }}</h1>
    <div class="summary"><b>{{ $vak->code }}</b> · {{ $vak->opleiding?->naam }} · {{ $vak->docent?->volledigeNaam() }} · {{ $periode?->naam }} · blok {{ $vak->blok ?: '—' }}</div>
  </div>
  <div class="iuasr-dash-vhead__actions" style="gap:8px;align-items:center;">
    <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="button" onclick="window.print()">Printen</button>
    @if ($samenvatting['volledig'])
      <span class="iuasr-dash-status s-approved">Registratie volledig</span>
    @else
      <span class="iuasr-dash-status s-incomplete">{{ $samenvatting['weken_geregistreerd'] }}/{{ $samenvatting['weken_totaal'] }} weken geregistreerd</span>
    @endif
    @unless ($magRegistreren)<span class="sis-role-note">Alleen-lezen</span>@endunless
  </div>
</div>

@if ($magRegistreren && ! $samenvatting['volledig'] && $samenvatting['deelnemers'] > 0)
  <div class="iuasr-dash-alert iuasr-dash-alert--warn" style="margin-bottom:16px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <span>Het registreren van de aanwezigheid is <b>verplicht</b>. Nog niet volledig geregistreerd: week
      <b>{{ implode(', ', $samenvatting['weken_ontbrekend']) }}</b>.</span>
  </div>
@endif

<div class="iuasr-dash-stats" style="margin-bottom:16px;">
  <div class="iuasr-dash-stat"><span class="lbl">Deelnemers</span><span class="val">{{ $samenvatting['deelnemers'] }}</span></div>
  <div class="iuasr-dash-stat {{ $samenvatting['gemiddeld'] !== null && $samenvatting['gemiddeld'] >= $normStandaard ? 'iuasr-dash-stat--ok' : '' }}">
    <span class="lbl">Gemiddelde aanwezigheid</span><span class="val">{{ $samenvatting['gemiddeld'] !== null ? $samenvatting['gemiddeld'].'%' : '—' }}</span>
  </div>
  <div class="iuasr-dash-stat {{ $samenvatting['onder_norm'] > 0 ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Onder de norm</span><span class="val">{{ $samenvatting['onder_norm'] }}</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">50%-regeling</span><span class="val">{{ $samenvatting['met_regeling'] }}</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Vrijgesteld</span><span class="val">{{ $samenvatting['vrijgesteld'] }}</span></div>
</div>

<style>
  .sis-pres-cell{width:30px;height:30px;border-radius:8px;border:2px solid rgba(30,20,70,.55);
    background:var(--surface,#fff);cursor:pointer;display:inline-flex;align-items:center;justify-content:center;
    padding:0;color:transparent;transition:background .12s,border-color .12s,transform .05s;}
  .sis-pres-cell .ic{display:none;}
  .sis-pres-cell[data-state=""]{border-style:dashed;border-color:rgba(30,20,70,.5);}
  .sis-pres-cell[data-state=""]:hover{border-color:var(--heritage-groen,#285C4D);background:#eef4f1;}
  .sis-pres-cell[data-state="1"]{background:var(--heritage-groen,#285C4D);border-color:var(--heritage-groen,#285C4D);color:#fff;}
  .sis-pres-cell[data-state="1"] .ic-check{display:block;}
  .sis-pres-cell[data-state="0"]{background:#fbe9ec;border-color:var(--secColor100,#C8102E);color:var(--secColor100,#C8102E);}
  .sis-pres-cell[data-state="0"] .ic-cross{display:block;}
  .sis-pres-cell:not(:disabled):active{transform:scale(.92);}
  .sis-pres-cell:disabled{cursor:default;opacity:.9;}
  .sis-pres-cell:focus-visible{outline:2px solid var(--priColor100,#1E1446);outline-offset:2px;}
  .sis-pres-legend{display:flex;flex-wrap:wrap;gap:14px;align-items:center;font-size:12.5px;color:var(--muted,#5c5873);margin-bottom:12px;}
  .sis-pres-legend .k{display:inline-flex;align-items:center;gap:6px;}
  .sis-pres-legend .box{width:18px;height:18px;border-radius:5px;border:2px solid rgba(30,20,70,.55);display:inline-block;}
  .sis-pres-legend .box.leeg{border-style:dashed;}
  .sis-pres-legend .box.aanwezig{background:var(--heritage-groen,#285C4D);border-color:var(--heritage-groen,#285C4D);}
  .sis-pres-legend .box.afwezig{background:#fbe9ec;border-color:var(--secColor100,#C8102E);}
</style>

@if ($magRegistreren)
  <div class="sis-pres-legend">
    <span>Klik op een vakje om te wisselen:</span>
    <span class="k"><span class="box leeg"></span> leeg = nog niet geregistreerd</span>
    <span class="k"><span class="box aanwezig"></span> aanwezig</span>
    <span class="k"><span class="box afwezig"></span> afwezig</span>
    <span>— of gebruik <b>alle 1</b> boven een week.</span>
  </div>
@endif

<form id="presentiegrid" method="POST" action="{{ route('vakken.presentie.opslaan', $vak) }}">
  @csrf
  <div class="iuasr-dash-tbl-card">
    <table class="iuasr-dash-tbl" id="pres-tbl">
      <thead>
        <tr>
          <th style="width:210px;">Student</th>
          @foreach ($weken as $week)
            <th style="text-align:center;">
              Week {{ $week }}
              @if ($magRegistreren)
                <br><button type="button" class="sis-weekall" data-week="{{ $week }}" title="Alle studenten in week {{ $week }} op aanwezig (1)">alle 1</button>
              @endif
            </th>
          @endforeach
          <th style="text-align:center;">Norm</th>
          <th style="text-align:right;">Aanwezigheid</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($rijen as $rij)
          @php $insch = $rij['inschrijving']; $student = $rij['student']; $st = $rij['status']; @endphp
          <tr data-insch="{{ $insch->id }}" data-norm="{{ $rij['vrijgesteld'] ? 0 : $st['norm'] }}">
            <td class="nm">
              {{ $student->volledigeNaam() }}
              @if ($rij['regeling'])<span class="sis-pill-soft sis-pill-regeling" title="50%-aanwezigheidsregeling">50%</span>@endif
              <small>{{ $student->studentnummer }}</small>
            </td>

            @if ($rij['vrijgesteld'])
              <td colspan="{{ count($weken) }}" style="text-align:center;">
                <span class="sis-pill-soft">Vrijgesteld — geen aanwezigheidsplicht</span>
              </td>
              <td style="text-align:center;"><span class="sis-muted">—</span></td>
              <td style="text-align:right;"><span class="sis-pill-soft">VR</span></td>
            @else
              @foreach ($weken as $week)
                @php $w = $rij['weken']->get($week); $stateVal = $w === true ? '1' : ($w === false ? '0' : ''); @endphp
                <td style="text-align:center;">
                  <button type="button" class="sis-pres-cell" data-week="{{ $week }}" data-state="{{ $stateVal }}"
                          title="Klik: leeg → aanwezig → afwezig"
                          aria-label="Week {{ $week }} — {{ $student->volledigeNaam() }}"
                          {{ $magRegistreren ? '' : 'disabled' }}>
                    <svg class="ic ic-check" viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    <svg class="ic ic-cross" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                  </button>
                  <input type="hidden" class="sis-pres-input" name="presentie[{{ $insch->id }}][{{ $week }}]" value="{{ $stateVal }}">
                </td>
              @endforeach
              <td style="text-align:center;"><span class="sis-muted" style="font-size:12px;">{{ $st['norm'] }}%</span></td>
              <td style="text-align:right;" class="pres-final">
                @if ($st['percentage'] === null)
                  <span class="sis-muted">—</span>
                @else
                  <span class="sis-grade-final {{ $st['status'] === 'onvoldoende' ? 'is-fail' : '' }}">{{ $st['percentage'] }}%</span>
                  <small class="sis-muted" style="display:block;font-size:11px;">{{ $st['aanwezig'] }}/{{ $st['geregistreerd'] }}</small>
                @endif
              </td>
            @endif
          </tr>
        @empty
          <tr><td colspan="{{ count($weken) + 3 }}"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen deelnemers</h3><p>Er zijn geen actieve studenten voor dit vak in de huidige periode.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</form>

@if ($rijen->isNotEmpty() && $magRegistreren)
  <div class="sis-savebar">
    <span class="status">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
      Klik een vakje: <b>groen ✓</b> = aanwezig, <b>rood ✗</b> = afwezig, <b>leeg</b> = nog niet geregistreerd. Wijzigingen worden gelogd.
    </span>
    <span class="grow"></span>
    <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit" form="presentiegrid">Aanwezigheid opslaan</button>
  </div>
@endif

<p class="sis-tblnote">De aanwezigheid wordt per college vastgelegd; een blok telt {{ count($weken) }} onderwijsweken. Een week die nog niet is geregistreerd (<b>leeg vakje</b>) telt <b>niet</b> als afwezigheid. De norm is <b>{{ $normStandaard }}%</b>, of <b>{{ $normRegeling }}%</b> voor studenten met de <b>50%-aanwezigheidsregeling</b> (herkenbaar aan het label 50% achter de naam). Studenten met een <b>vrijstelling</b> voor dit vak volgen geen colleges en worden niet geregistreerd.</p>

@push('scripts')
<script>
  (function () {
    // Drie standen per vakje: '' (leeg) -> '1' (aanwezig) -> '0' (afwezig) -> ''.
    function volgende(state) { return state === '' ? '1' : (state === '1' ? '0' : ''); }

    function zet(btn, val) {
      btn.dataset.state = val;
      var input = btn.parentElement.querySelector('.sis-pres-input');
      if (input) input.value = val;
    }

    function calcRow(tr) {
      var cel = tr.querySelector('.pres-final');
      if (!cel) return;
      var norm = parseInt(tr.dataset.norm, 10) || 0;
      var n = 0, a = 0;
      tr.querySelectorAll('.sis-pres-input').forEach(function (inp) {
        if (inp.value === '1') { n++; a++; }
        else if (inp.value === '0') { n++; }
      });
      if (n === 0) { cel.innerHTML = '<span class="sis-muted">—</span>'; return; }
      var pct = Math.round((a / n) * 100);
      cel.innerHTML = '<span class="sis-grade-final' + (pct < norm ? ' is-fail' : '') + '">' + pct + '%</span>' +
        '<small class="sis-muted" style="display:block;font-size:11px;">' + a + '/' + n + '</small>';
    }

    // Klik op een vakje wisselt de stand.
    document.querySelectorAll('.sis-pres-cell').forEach(function (btn) {
      if (btn.disabled) return;
      btn.addEventListener('click', function () {
        zet(btn, volgende(btn.dataset.state || ''));
        calcRow(btn.closest('tr'));
      });
    });

    // Kolomactie: hele week op 'aanwezig' zetten.
    document.querySelectorAll('.sis-weekall').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var week = btn.dataset.week;
        document.querySelectorAll('.sis-pres-cell[data-week="' + week + '"]').forEach(function (cell) {
          if (!cell.disabled) { zet(cell, '1'); calcRow(cell.closest('tr')); }
        });
      });
    });
  })();
</script>
@endpush
@endsection
