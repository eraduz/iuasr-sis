@extends('layouts.app')

@section('titel', 'HR-rapportage')

@section('inhoud')
@php
  $barAantal = collect($perAfdeling)->map(fn ($r) => ['label' => $r['afdeling'], 'value' => $r['aantal']])->all();
  $barFte = collect($perAfdeling)->map(fn ($r) => ['label' => $r['afdeling'], 'value' => $r['fte']])->all();
@endphp

<div class="sis-crumb"><a href="{{ route('hr.dashboard') }}">HR</a><span class="sep">›</span><b>Rapportage</b></div>

<div class="iuasr-dash-vhead">
  <div><h1>HR-rapportage</h1><div class="summary">Kerncijfers, FTE en verzuim{{ auth()->user()->isHrTeamBeperkt() ? ' (eigen team)' : '' }}</div></div>
  <div class="iuasr-dash-vhead__actions"><a class="iuasr-dash-btn" href="{{ route('hr.verzuimverlof') }}">Verzuim &amp; verlof per medewerker</a><a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('hr.rapport.export') }}">Exporteren (CSV)</a></div>
</div>

<div class="iuasr-dash-stats" style="grid-template-columns:repeat(4,1fr);">
  <div class="iuasr-dash-stat"><span class="lbl">Medewerkers</span><span class="val">{{ $kpi['actief'] }}</span><span class="delta">van {{ $kpi['medewerkers'] }} totaal</span></div>
  <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Totaal FTE</span><span class="val">{{ number_format($kpi['fte'], 2, ',', '.') }}</span><span class="delta">gemiddeld {{ number_format($kpi['gem_fte'], 2, ',', '.') }} per medewerker</span></div>
  <div class="iuasr-dash-stat {{ $kpi['verzuim'] > 0 ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Actueel verzuim</span><span class="val">{{ number_format($kpi['verzuim'], 1, ',', '.') }}%</span><span class="delta">{{ $kpi['ziek'] }} ziek gemeld</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Ziektedagen (dit jaar)</span><span class="val">{{ $kpi['verzuim_dagen'] }}</span><span class="delta">binnen uw bereik</span></div>
</div>

<div class="sis-chartgrid" style="margin-top:16px;">
  <div class="sis-chart-card">
    <h3>Medewerkers per afdeling</h3>
    @include('partials.charts.bar', ['data' => $barAantal, 'kleur' => 'var(--priColor200)', 'leeg' => 'Geen medewerkers.'])
  </div>
  <div class="sis-chart-card">
    <h3>FTE per afdeling</h3>
    @include('partials.charts.bar', ['data' => $barFte, 'kleur' => '#285C4D', 'leeg' => 'Geen dienstverbanden.'])
  </div>
</div>

<div class="iuasr-dash-tbl-card" style="margin-top:16px;">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Afdeling</th><th style="text-align:right;">Medewerkers</th><th style="text-align:right;">FTE</th><th style="text-align:right;">Ziek</th><th style="text-align:right;">Verzuim %</th></tr></thead>
    <tbody>
      @forelse ($perAfdeling as $r)
        <tr>
          <td class="nm">{{ $r['afdeling'] }}</td>
          <td class="tnum" style="text-align:right;">{{ $r['aantal'] }}</td>
          <td class="tnum" style="text-align:right;">{{ number_format($r['fte'], 2, ',', '.') }}</td>
          <td class="tnum" style="text-align:right;">{{ $r['ziek'] }}</td>
          <td class="tnum" style="text-align:right;">{{ number_format($r['verzuim'], 1, ',', '.') }}%</td>
        </tr>
      @empty
        <tr><td colspan="5"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen gegevens</h3></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<p class="sis-tblnote">Het verzuimpercentage is een momentopname (aandeel medewerkers met een open ziekmelding). De CSV-export bevat één regel per medewerker.</p>
@endsection
