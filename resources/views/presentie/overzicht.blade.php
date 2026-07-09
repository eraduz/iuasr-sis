@extends('layouts.app')

@section('titel', 'Aanwezigheidsoverzicht')

@php $normStandaard = (int) round(config('sis.presentie.norm') * 100); @endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Aanwezigheidsoverzicht</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Aanwezigheidsoverzicht</h1>
    <div class="summary">{{ $rijen->count() }} vakken · registratie per college, {{ $weken }} weken per blok</div>
  </div>
  <div class="iuasr-dash-vhead__actions">
    <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="button" onclick="window.print()">Printen</button>
  </div>
</div>

@if ($onvolledig > 0)
  <div class="iuasr-dash-alert iuasr-dash-alert--warn" style="margin-bottom:16px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <span>Bij <b>{{ $onvolledig }}</b> {{ $onvolledig === 1 ? 'vak is' : 'vakken zijn' }} de aanwezigheidslijsten nog niet volledig ingevuld. Registreren is voor de docent verplicht.</span>
  </div>
@endif

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead>
      <tr>
        <th>Vak</th><th>Code</th><th>Opleiding</th><th>Docent</th>
        <th>Deelnemers</th><th>50%-regeling</th><th>Registratie</th>
        <th>Gem. aanwezigheid</th><th>Onder norm</th><th class="row-act"></th>
      </tr>
    </thead>
    <tbody>
      @forelse ($rijen as $r)
        @php
          $vak = $r['vak']; $s = $r['samenvatting'];
          if ($s['deelnemers'] === 0) { $badge = 's-draft'; $tekst = 'Geen deelnemers'; }
          elseif ($s['volledig']) { $badge = 's-approved'; $tekst = 'Volledig'; }
          elseif ($s['weken_geregistreerd'] === 0) { $badge = 's-rejected'; $tekst = 'Niet gestart'; }
          else { $badge = 's-incomplete'; $tekst = $s['weken_geregistreerd'].'/'.$s['weken_totaal'].' weken'; }
        @endphp
        <tr>
          <td class="nm">{{ $vak->naam }}</td>
          <td class="tnum">{{ $vak->code }}</td>
          <td class="pg">{{ $vak->opleiding?->code }}</td>
          <td>{{ $vak->docent?->volledigeNaam() ?? '—' }}</td>
          <td class="tnum">{{ $s['deelnemers'] }}</td>
          <td class="tnum">{{ $s['met_regeling'] ?: '—' }}</td>
          <td><span class="iuasr-dash-status {{ $badge }}">{{ $tekst }}</span></td>
          <td class="tnum">
            @if ($s['gemiddeld'] === null)
              <span class="sis-muted">—</span>
            @else
              <span class="sis-grade-final {{ $s['gemiddeld'] < $normStandaard ? 'is-fail' : '' }}">{{ $s['gemiddeld'] }}%</span>
            @endif
          </td>
          <td class="tnum">{{ $s['onder_norm'] ?: '—' }}</td>
          <td class="row-act"><a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('vakken.presentie', $vak) }}">Aanwezigheidslijst</a></td>
        </tr>
      @empty
        <tr><td colspan="10"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen vakken</h3><p>Er zijn geen actieve vakken binnen uw bereik.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<p class="sis-tblnote">De gemiddelde aanwezigheid is berekend over de <b>geregistreerde</b> colleges; nog niet geregistreerde weken tellen niet mee. “Onder norm” telt de studenten die onder hun eigen norm zitten ({{ $normStandaard }}%, of {{ (int) round(config('sis.presentie.norm_regeling') * 100) }}% bij de aanwezigheidsregeling). Vrijgestelde studenten blijven buiten beschouwing.</p>
@endsection
