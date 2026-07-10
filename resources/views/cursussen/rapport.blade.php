@extends('layouts.app')

@section('titel', 'Cursusrapportage')

@php
    use App\Enums\CursusinschrijvingStatus;
    use App\Support\Statistiek;
    $euro = fn ($b) => '€ '.number_format((float) $b, 2, ',', '.');
    $bereik = auth()->user()->isCursusBeperkt() ? ' · uw cursus(sen)' : '';
@endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('cursussen.dashboard') }}">Cursussen</a><span class="sep">›</span><b>Rapportage</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Cursusrapportage</h1>
    <div class="summary">Inschrijvingen en cursusgelden per cursus. Alleen-lezen{{ $bereik }}.</div>
  </div>
  <div class="iuasr-dash-vhead__actions">
    <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('cursussen.rapport.export') }}">Exporteren (CSV)</a>
  </div>
</div>

<div class="iuasr-dash-stats" style="grid-template-columns:repeat(5,1fr);">
  <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Inschrijvingen</span><span class="val">{{ $totalen['inschrijvingen'] }}</span><span class="delta">{{ $totalen['voldaan'] }} volledig voldaan</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Verschuldigd</span><span class="val" style="font-size:19px;">{{ $euro($totalen['verschuldigd']) }}</span><span class="delta">excl. geannuleerd</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Betaald</span><span class="val" style="font-size:19px;">{{ $euro($totalen['betaald']) }}</span></div>
  <div class="iuasr-dash-stat {{ $totalen['openstaand'] > 0 ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Openstaand</span><span class="val" style="font-size:19px;">{{ $euro($totalen['openstaand']) }}</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Betaalgraad</span><span class="val">{{ $totalen['betaalgraad'] }}%</span></div>
</div>

<div class="sis-chartgrid" style="margin-top:16px;">
  <div class="sis-chart-card">
    <h3>Inschrijvingen per cursus</h3><p class="sub">Alle statussen</p>
    @include('partials.charts.bar', ['data' => $inschrijvingenPerCursus, 'kleur' => 'var(--priColor200)', 'leeg' => 'Nog geen inschrijvingen.'])
  </div>
  <div class="sis-chart-card">
    <h3>Openstaand per cursus</h3><p class="sub">Nog te ontvangen cursusgeld</p>
    @include('partials.charts.bar', ['data' => $openstaandPerCursus, 'kleur' => Statistiek::ROOD, 'eenheid' => ' €', 'leeg' => 'Niets openstaand.'])
  </div>
  <div class="sis-chart-card">
    <h3>Betaalmethode</h3><p class="sub">Verdeling van het betaalde bedrag</p>
    @include('partials.charts.donut', ['segments' => $methoden, 'middenLabel' => 'betaald', 'leeg' => 'Nog geen betalingen.'])
  </div>
</div>

<div class="iuasr-dash-tbl-card" style="margin-top:16px;">
  <table class="iuasr-dash-tbl">
    <thead>
      <tr>
        <th>Code</th><th>Cursus</th>
        <th style="text-align:center;">Aangemeld</th><th style="text-align:center;">Actief</th>
        <th style="text-align:center;">Afgerond</th><th style="text-align:center;">Geannuleerd</th>
        <th style="text-align:right;">Verschuldigd</th><th style="text-align:right;">Betaald</th>
        <th style="text-align:right;">Openstaand</th><th style="text-align:center;">Betaalgraad</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($rijen as $r)
        @php $c = $r['cursus']; @endphp
        <tr>
          <td class="tnum">{{ $c->code }}</td>
          <td class="nm">{{ $c->naam }}</td>
          <td class="tnum" style="text-align:center;">{{ $r['per_status'][CursusinschrijvingStatus::Aangemeld->value] }}</td>
          <td class="tnum" style="text-align:center;">{{ $r['per_status'][CursusinschrijvingStatus::Actief->value] }}</td>
          <td class="tnum" style="text-align:center;">{{ $r['per_status'][CursusinschrijvingStatus::Afgerond->value] }}</td>
          <td class="tnum" style="text-align:center;">{{ $r['per_status'][CursusinschrijvingStatus::Geannuleerd->value] }}</td>
          <td class="tnum" style="text-align:right;">{{ $euro($r['verschuldigd']) }}</td>
          <td class="tnum" style="text-align:right;">{{ $euro($r['betaald']) }}</td>
          <td class="tnum" style="text-align:right;">{{ $euro($r['openstaand']) }}</td>
          <td style="text-align:center;"><span class="iuasr-dash-status {{ $r['betaalgraad'] >= 100 ? 's-approved' : ($r['betaalgraad'] > 0 ? 's-incomplete' : 's-rejected') }}">{{ $r['betaalgraad'] }}%</span></td>
        </tr>
      @empty
        <tr><td colspan="10"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen cursussen</h3><p>Er zijn geen cursussen onder uw beheer.</p></div></td></tr>
      @endforelse
    </tbody>
    @if ($rijen->isNotEmpty())
      <tfoot>
        <tr style="font-weight:600;border-top:2px solid var(--lineColor,#e3e3ea);">
          <td colspan="2">Totaal ({{ $rijen->count() }} cursussen)</td>
          <td colspan="4" style="text-align:center;">{{ $totalen['inschrijvingen'] }} inschrijvingen</td>
          <td class="tnum" style="text-align:right;">{{ $euro($totalen['verschuldigd']) }}</td>
          <td class="tnum" style="text-align:right;">{{ $euro($totalen['betaald']) }}</td>
          <td class="tnum" style="text-align:right;">{{ $euro($totalen['openstaand']) }}</td>
          <td style="text-align:center;">{{ $totalen['betaalgraad'] }}%</td>
        </tr>
      </tfoot>
    @endif
  </table>
</div>

<p class="sis-tblnote" style="margin-top:10px;">De financiële cijfers tellen de niet-geannuleerde inschrijvingen mee; alleen betalingen met status <b>Betaald</b> gelden als voldaan. De CSV-export bevat één regel per cursist.</p>
@endsection
