@extends('layouts.app')

@section('titel', 'Rapportage relatiebeheer')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('relatiebeheer.dashboard') }}">Relatiebeheer</a><span class="sep">›</span><b>Rapportage</b></div>

<div class="iuasr-dash-vhead">
  <div><h1>Rapportage</h1><div class="summary">Kerncijfers en overzicht per organisatie</div></div>
  <div class="iuasr-dash-vhead__actions">
    <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('relatiebeheer.rapport.export') }}">Exporteren (CSV)</a>
  </div>
</div>

<div class="iuasr-dash-stats" style="grid-template-columns:repeat(4,1fr);">
  <div class="iuasr-dash-stat"><span class="lbl">Organisaties</span><span class="val">{{ $kpi['organisaties'] }}</span><span class="delta">actief</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Contactpersonen</span><span class="val">{{ $kpi['contactpersonen'] }}</span><span class="delta">actief</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Bezettingsgraad</span><span class="val">{{ $kpi['bezettingsgraad'] !== null ? $kpi['bezettingsgraad'].'%' : '—' }}</span><span class="delta">van de capaciteit</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Gem. evaluatie</span><span class="val">{{ $evaluatie['percentage'] !== null ? $evaluatie['percentage'].'%' : '—' }}</span><span class="delta">voldoende ({{ $evaluatie['beoordeeld'] }} beoordeeld)</span></div>
</div>

<div class="sis-chartgrid" style="margin-top:16px;">
  <div class="sis-chart-card">
    <h3>Stages per status</h3>
    @include('partials.charts.donut', ['segments' => $stagesPerStatus, 'middenLabel' => 'stages'])
  </div>
  <div class="sis-chart-card">
    <h3>Organisaties per type</h3>
    @include('partials.charts.bar', ['data' => $organisatiesPerType, 'kleur' => 'var(--priColor200)', 'leeg' => 'Nog geen organisaties.'])
  </div>
</div>

<div class="iuasr-dash-tbl-card" style="margin-top:16px;">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Relatienr.</th><th>Organisatie</th><th>Type</th><th>Opleiding(en)</th><th>Plaats</th><th style="text-align:center;">Contactpers.</th><th style="text-align:center;">Stageplaatsen</th><th style="text-align:center;">Lopende stages</th><th style="text-align:center;">Open taken</th></tr></thead>
    <tbody>
      @forelse ($rijen as $rij)
        <tr>
          <td class="tnum">{{ $rij['relatienummer'] }}</td>
          <td class="nm">{{ $rij['naam'] }}</td>
          <td>{{ $rij['type'] ?: '—' }}</td>
          <td>{{ $rij['opleidingen'] ?: '—' }}</td>
          <td>{{ $rij['plaats'] ?: '—' }}</td>
          <td style="text-align:center;">{{ $rij['contactpersonen'] }}</td>
          <td style="text-align:center;">{{ $rij['stageplaatsen'] }}</td>
          <td style="text-align:center;">{{ $rij['lopende_stages'] }}</td>
          <td style="text-align:center;">{{ $rij['open_taken'] }}</td>
        </tr>
      @empty
        <tr><td colspan="9"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen organisaties</h3></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<p class="sis-tblnote">De cijfers zijn beperkt tot de opleiding(en) waarvoor u bevoegd bent. De CSV-export bevat dezelfde regels.</p>
@endsection
