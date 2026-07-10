@extends('layouts.app')

@section('titel', 'Bestuur')

@php
    use App\Support\Statistiek;
    $euro0 = fn ($b) => '€ '.number_format((float) $b, 0, ',', '.');
@endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('modules.kiezen') }}">Platform</a><span class="sep">›</span><b>Bestuur</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Bestuur — globaal overzicht</h1>
    <div class="summary">Instellingsbreed beeld van studenten, onderwijs, aanwezigheid, financiën en cursussen. Alleen-lezen.</div>
  </div>
  <div class="iuasr-dash-vhead__actions">
    <a class="iuasr-dash-btn" href="{{ route('rapporten.alumni') }}">Alumni</a>
    <a class="iuasr-dash-btn" href="{{ route('presentieoverzicht') }}">Aanwezigheid</a>
    <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('cursussen.dashboard') }}">Cursussen</a>
  </div>
</div>

{{-- Kerncijfers --}}
<div class="iuasr-dash-stats" style="grid-template-columns:repeat(4,1fr);">
  <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Actief ingeschreven</span><span class="val">{{ $kern['actief'] }}</span><span class="delta">van {{ $kern['studenten'] }} studenten · {{ $aantalOpleidingen }} opleidingen</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Afgestudeerd</span><span class="val">{{ $kern['afgestudeerd'] }}</span><span class="delta">alumni · {{ $kern['uitgeschreven'] }} uitgeschreven</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Studiesucces</span><span class="val">{{ $slaag['percentage'] }}%</span><span class="delta">toetsen voldoende</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Aanwezigheid</span><span class="val">{{ $presentie['percentage'] }}%</span><span class="delta">{{ $presentie['onder_norm'] }} onder de norm</span></div>
</div>
<div class="iuasr-dash-stats" style="grid-template-columns:repeat(4,1fr);margin-top:12px;">
  <div class="iuasr-dash-stat"><span class="lbl">Cursussen</span><span class="val">{{ $aantalCursussen }}</span><span class="delta">{{ $cursusInschrijvingen }} actieve inschrijvingen</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Cursisten</span><span class="val">{{ $aantalCursisten }}</span><span class="delta">los van de studenten</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Collegegeld voldaan</span><span class="val">{{ $financieel['betaalgraad'] }}%</span><span class="delta">{{ $euro0($financieel['openstaand']) }} openstaand</span></div>
  <div class="iuasr-dash-stat {{ $financieel['achterstand_aantal'] > 0 ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Betaalachterstand</span><span class="val">{{ $financieel['achterstand_aantal'] }}</span><span class="delta">studenten</span></div>
</div>

{{-- Studenten & onderwijs --}}
<div class="sis-chartgrid" style="margin-top:16px;">
  <div class="sis-chart-card">
    <h3>Studenten per opleiding</h3><p class="sub">Actieve inschrijvingen</p>
    @include('partials.charts.bar', ['data' => $perOpleiding, 'kleur' => 'var(--priColor200)', 'leeg' => 'Nog geen inschrijvingen.'])
  </div>
  <div class="sis-chart-card">
    <h3>Instroom per studiejaar</h3><p class="sub">Nieuwe inschrijvingen</p>
    @include('partials.charts.spark', ['data' => $instroom])
  </div>
  <div class="sis-chart-card">
    <h3>Inschrijvingsstatus</h3><p class="sub">Rendement &amp; uitval</p>
    @include('partials.charts.donut', ['segments' => $status, 'middenLabel' => 'totaal'])
  </div>
</div>

{{-- Aanwezigheid --}}
<div class="sis-chartgrid" style="margin-top:16px;">
  <div class="sis-chart-card">
    <h3>Aanwezigheid per opleiding</h3><p class="sub">Gemiddeld · onderwijskwaliteit</p>
    @include('partials.charts.bar', ['data' => $presentiePerOpleiding, 'kleur' => Statistiek::GROEN, 'eenheid' => '%', 'leeg' => 'Nog geen registraties.'])
  </div>
  <div class="sis-chart-card">
    <h3>Verdeling aanwezigheid</h3><p class="sub">Per student per vak · norm 80% (50% bij regeling)</p>
    @include('partials.charts.donut', ['segments' => $presentieVerdeling, 'middenLabel' => 'metingen'])
  </div>
</div>

{{-- Financiën & cursussen --}}
<div class="sis-chartgrid" style="margin-top:16px;">
  <div class="sis-chart-card">
    <h3>Collegegeld</h3><p class="sub">Betaald t.o.v. openstaand</p>
    @include('partials.charts.donut', [
      'segments' => [
        ['label' => 'Betaald', 'value' => (int) round($financieel['betaald']), 'kleur' => Statistiek::GROEN],
        ['label' => 'Openstaand', 'value' => (int) round($financieel['openstaand']), 'kleur' => Statistiek::ROOD],
      ],
      'midden' => $financieel['betaalgraad'].'%', 'middenLabel' => 'voldaan',
    ])
  </div>
  <div class="sis-chart-card">
    <h3>Cursussen</h3><p class="sub">Actieve inschrijvingen per cursus</p>
    @include('partials.charts.bar', ['data' => $cursusPerCursus, 'kleur' => 'var(--heritage-green, #285C4D)', 'leeg' => 'Nog geen cursusinschrijvingen.'])
  </div>
</div>

<p class="sis-tblnote" style="margin-top:14px;">Dit overzicht is instellingsbreed en alleen-lezen. Voor detailrapporten kunt u naar <a href="{{ route('rapporten.alumni') }}">Alumni</a>, de <a href="{{ route('cursussen.dashboard') }}">Cursussen-module</a> of de <a href="{{ route('ondertekening') }}">ondertekende documenten</a>.</p>
@endsection
