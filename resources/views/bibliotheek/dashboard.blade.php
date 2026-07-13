@extends('layouts.app')

@section('titel', 'Bibliotheek')

@section('inhoud')
@php $magBeheer = auth()->user()->magBibliotheekBeheren(); @endphp

<div class="sis-crumb"><b>Bibliotheek</b><span class="sep">›</span><b>Overzicht</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Bibliotheek</h1>
    <div class="summary">{{ now()->translatedFormat('l j F Y') }}</div>
  </div>
  @if ($magBeheer)
    <div class="iuasr-dash-vhead__actions">
      <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('bibliotheek.uitlenen') }}">Uitlenen</a>
      <a class="iuasr-dash-btn" href="{{ route('bibliotheek.publicaties.create') }}">Publicatie toevoegen</a>
    </div>
  @endif
</div>

<div class="iuasr-dash-stats">
  <div class="iuasr-dash-stat"><span class="lbl">Boeken</span><span class="val">{{ $kpi['boeken'] }}</span><span class="delta">titels</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Tijdschriften</span><span class="val">{{ $kpi['tijdschriften'] }}</span><span class="delta">titels</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Digitale documenten</span><span class="val">{{ $kpi['digitaal'] }}</span><span class="delta">titels</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Nu uitgeleend</span><span class="val">{{ $kpi['uitgeleend'] }}</span><span class="delta">exemplaren</span></div>
  <div class="iuasr-dash-stat {{ $kpi['telaat'] > 0 ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Te laat</span><span class="val">{{ $kpi['telaat'] }}</span><span class="delta">niet retour</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Vandaag uitgeleend</span><span class="val">{{ $kpi['vandaag_uit'] }}</span><span class="delta">vandaag</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Vandaag geretourneerd</span><span class="val">{{ $kpi['vandaag_retour'] }}</span><span class="delta">vandaag</span></div>
</div>

@if ($telaat->isNotEmpty())
  <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin:18px 0 10px;">
    <span>{{ $telaat->count() }} {{ $telaat->count() === 1 ? 'publicatie is' : 'publicaties zijn' }} te laat.</span>
  </div>
  <div class="iuasr-dash-tbl-card">
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Lener</th><th>Publicatie</th><th>Retourdatum</th><th style="text-align:right;">Dagen te laat</th><th class="row-act"></th></tr></thead>
      <tbody>
        @foreach ($telaat as $u)
          <tr>
            <td class="nm">{{ $u->lenerNaam() }}<br><small class="sis-muted">{{ $u->isStudentlening() ? 'Student' : 'Medewerker' }}</small></td>
            <td>{{ $u->exemplaar->publicatie->volledigeTitel() }}<br><small class="sis-muted">{{ $u->exemplaar->serienummer }}</small></td>
            <td class="tnum">{{ $u->verwachte_retour_op->format('d-m-Y') }}</td>
            <td class="tnum" style="text-align:right;"><span class="iuasr-dash-status s-rejected">{{ $u->dagenTeLaat() }}</span></td>
            <td class="row-act">
              @if ($magBeheer)
                <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('bibliotheek.innemen', $u) }}">Innemen</a>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif

<h2 style="margin:22px 0 10px;">Binnen {{ $venster }} dagen terug</h2>
<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Lener</th><th>Publicatie</th><th>Retourdatum</th><th style="text-align:right;">Dagen</th></tr></thead>
    <tbody>
      @forelse ($binnenkort as $u)
        <tr>
          <td class="nm">{{ $u->lenerNaam() }}</td>
          <td>{{ $u->exemplaar->publicatie->volledigeTitel() }}</td>
          <td class="tnum">{{ $u->verwachte_retour_op->format('d-m-Y') }}</td>
          <td class="tnum" style="text-align:right;">{{ $u->dagenTotVervaldatum() }}</td>
        </tr>
      @empty
        <tr><td colspan="4"><div class="iuasr-dash-empty" style="border:0;"><h3>Niets op de valreep</h3><p class="sis-muted">Er hoeft de komende {{ $venster }} dagen niets terug.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="iuasr-dash-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:22px;">
  <div class="sis-card">
    <h3>Uitleningen per maand</h3>
    @include('partials.charts.bar', ['data' => $perMaand, 'leeg' => 'Nog geen uitleningen.'])
  </div>
  <div class="sis-card">
    <h3>Uitleningen per vakgebied</h3>
    @include('partials.charts.bar', ['data' => $perVakgebied, 'leeg' => 'Nog geen uitleningen.'])
  </div>
  <div class="sis-card">
    <h3>Uitleningen per taal</h3>
    @include('partials.charts.bar', ['data' => $perTaal, 'leeg' => 'Nog geen uitleningen.'])
  </div>
  <div class="sis-card">
    <h3>Meest uitgeleend</h3>
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Titel</th><th style="text-align:right;">Uitleningen</th><th>Laatst</th></tr></thead>
      <tbody>
        @forelse ($populair as $rij)
          <tr>
            <td class="nm"><a href="{{ route('bibliotheek.publicaties.show', $rij->id) }}">{{ $rij->titel }}</a></td>
            <td class="tnum" style="text-align:right;">{{ $rij->aantal }}</td>
            <td class="tnum">{{ \Illuminate\Support\Carbon::parse($rij->laatste)->format('d-m-Y') }}</td>
          </tr>
        @empty
          <tr><td colspan="3"><p class="sis-muted">Nog geen uitleningen.</p></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
