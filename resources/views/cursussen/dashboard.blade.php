@extends('layouts.app')

@section('titel', 'Cursussen')

@php $euro = fn ($b) => '€ '.number_format((float) $b, 2, ',', '.'); @endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('modules.kiezen') }}">Modules</a><span class="sep">›</span><b>Cursussen Administratie</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Cursussen Administratie</h1>
    <div class="summary">Cursusbeheer, cursisten en cursusgelden</div>
  </div>
  <div class="iuasr-dash-vhead__actions">
    <a class="iuasr-dash-btn" href="{{ route('cursisten.create') }}">Cursist toevoegen</a>
    <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('cursussen.beheer') }}">Cursusbeheer</a>
  </div>
</div>

<div class="iuasr-dash-stats" style="margin-bottom:16px;">
  <div class="iuasr-dash-stat"><span class="lbl">Actieve cursussen</span><span class="val">{{ $aantalCursussen }}</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Cursisten</span><span class="val">{{ $aantalCursisten }}</span></div>
  <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Actieve inschrijvingen</span><span class="val">{{ $aantalInschrijvingen }}</span></div>
</div>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Code</th><th>Cursus</th><th style="text-align:right;">Cursusgeld</th><th style="text-align:center;">Inschrijvingen</th><th style="text-align:center;">Status</th></tr></thead>
    <tbody>
      @forelse ($cursussen as $c)
        <tr>
          <td class="tnum">{{ $c->code }}</td>
          <td class="nm">{{ $c->naam }}</td>
          <td class="tnum" style="text-align:right;">{{ $euro($c->cursusgeld) }}</td>
          <td class="tnum" style="text-align:center;">{{ $c->actieve_inschrijvingen }}</td>
          <td style="text-align:center;"><span class="iuasr-dash-status {{ $c->actief ? 's-approved' : 's-draft' }}">{{ $c->actief ? 'Actief' : 'Inactief' }}</span></td>
        </tr>
      @empty
        <tr><td colspan="5"><div class="iuasr-dash-empty" style="border:0;"><h3>Nog geen cursussen</h3><p>Voeg een cursus toe via Cursusbeheer.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
