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
                @php $w = $rij['weken']->get($week); @endphp
                <td style="text-align:center;">
                  <select class="sis-pres-select" name="presentie[{{ $insch->id }}][{{ $week }}]"
                          data-week="{{ $week }}" aria-label="Week {{ $week }} — {{ $student->volledigeNaam() }}"
                          {{ $magRegistreren ? '' : 'disabled' }}>
                    <option value="" @selected($w === null)>–</option>
                    <option value="1" @selected($w === true)>1</option>
                    <option value="0" @selected($w === false)>0</option>
                  </select>
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
      <b>1</b> = aanwezig, <b>0</b> = afwezig, <b>–</b> = nog niet geregistreerd. Wijzigingen worden gelogd.
    </span>
    <span class="grow"></span>
    <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit" form="presentiegrid">Aanwezigheid opslaan</button>
  </div>
@endif

<p class="sis-tblnote">De aanwezigheid wordt per college vastgelegd; een blok telt {{ count($weken) }} onderwijsweken. Een week die nog niet is geregistreerd (<b>–</b>) telt <b>niet</b> als afwezigheid. De norm is <b>{{ $normStandaard }}%</b>, of <b>{{ $normRegeling }}%</b> voor studenten met de <b>50%-aanwezigheidsregeling</b> (herkenbaar aan het label 50% achter de naam). Studenten met een <b>vrijstelling</b> voor dit vak volgen geen colleges en worden niet geregistreerd.</p>

@push('scripts')
<script>
  (function () {
    function calcRow(tr) {
      var cel = tr.querySelector('.pres-final');
      if (!cel) return;
      var norm = parseInt(tr.dataset.norm, 10) || 0;
      var n = 0, a = 0;
      tr.querySelectorAll('.sis-pres-select').forEach(function (s) {
        if (s.value === '1') { n++; a++; }
        else if (s.value === '0') { n++; }
      });
      if (n === 0) { cel.innerHTML = '<span class="sis-muted">—</span>'; return; }
      var pct = Math.round((a / n) * 100);
      cel.innerHTML = '<span class="sis-grade-final' + (pct < norm ? ' is-fail' : '') + '">' + pct + '%</span>' +
        '<small class="sis-muted" style="display:block;font-size:11px;">' + a + '/' + n + '</small>';
    }

    document.querySelectorAll('#pres-tbl tbody tr[data-insch]').forEach(function (tr) {
      tr.querySelectorAll('.sis-pres-select').forEach(function (s) {
        s.addEventListener('change', function () { calcRow(tr); });
      });
    });

    // Kolomactie: hele week op 'aanwezig' zetten (alleen lege cellen blijven niet achter).
    document.querySelectorAll('.sis-weekall').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var week = btn.dataset.week;
        document.querySelectorAll('#pres-tbl tbody tr[data-insch]').forEach(function (tr) {
          var sel = tr.querySelector('.sis-pres-select[data-week="' + week + '"]');
          if (sel && !sel.disabled) { sel.value = '1'; calcRow(tr); }
        });
      });
    });
  })();
</script>
@endpush
@endsection
