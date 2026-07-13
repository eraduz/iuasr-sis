@extends('layouts.app')

@section('titel', 'Bibliotheekrapportage')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('bibliotheek.dashboard') }}">Bibliotheek</a><span class="sep">›</span><b>Rapportage</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Rapportage</h1>
    <div class="summary">Overzichten per vakgebied, auteur en uitgavejaar, plus de meest uitgeleende titels.</div>
  </div>
  <div class="iuasr-dash-vhead__actions">
    <a class="iuasr-dash-btn" href="{{ route('bibliotheek.export') }}">Algemene lijst (CSV)</a>
  </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
  <div class="sis-card">
    <h3>Publicaties per vakgebied</h3>
    @include('partials.charts.bar', ['data' => $perVakgebied, 'leeg' => 'Nog geen publicaties.'])
  </div>
  <div class="sis-card">
    <h3>Publicaties per uitgavejaar</h3>
    @include('partials.charts.bar', ['data' => $perJaar, 'leeg' => 'Nog geen publicaties met een jaar.'])
  </div>
</div>

<h2 style="margin:22px 0 10px;">Publicaties per auteur</h2>
<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Auteur</th><th style="text-align:right;">Publicaties</th></tr></thead>
    <tbody>
      @forelse ($perAuteur as $rij)
        <tr>
          <td class="nm" dir="auto">{{ $rij['label'] }}</td>
          <td class="tnum" style="text-align:right;">{{ $rij['value'] }}</td>
        </tr>
      @empty
        <tr><td colspan="2"><div class="iuasr-dash-empty" style="border:0;"><h3>Nog geen auteurs</h3></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<p class="sis-tblnote">De 25 auteurs met de meeste publicaties.</p>

<h2 style="margin:22px 0 10px;">Meest uitgeleende publicaties</h2>
<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th style="width:60px;">#</th><th>Titel</th><th style="text-align:right;">Aantal uitleningen</th><th>Laatste uitleendatum</th></tr></thead>
    <tbody>
      @forelse ($populair as $i => $rij)
        <tr>
          <td class="tnum">{{ $i + 1 }}</td>
          <td class="nm" dir="auto"><a href="{{ route('bibliotheek.publicaties.show', $rij->id) }}">{{ $rij->titel }}</a></td>
          <td class="tnum" style="text-align:right;">{{ $rij->aantal }}</td>
          <td class="tnum">{{ \Illuminate\Support\Carbon::parse($rij->laatste)->format('d-m-Y') }}</td>
        </tr>
      @empty
        <tr><td colspan="4"><div class="iuasr-dash-empty" style="border:0;"><h3>Nog geen uitleningen</h3></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<p class="sis-tblnote">De rangschikking is de populariteitsranking: het aantal keer dat een exemplaar van deze titel is uitgeleend.</p>
@endsection
