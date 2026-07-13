@extends('layouts.app')

@section('titel', $reeks->titel)

@section('inhoud')
@php $magBeheer = auth()->user()->magBibliotheekBeheren(); @endphp

<div class="sis-crumb"><a href="{{ route('bibliotheek.dashboard') }}">Bibliotheek</a><span class="sep">›</span><a href="{{ route('bibliotheek.reeksen') }}">Boekreeksen</a><span class="sep">›</span><b>{{ $reeks->titel }}</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1 dir="auto">{{ $reeks->titel }}</h1>
    <div class="summary">{{ $reeks->delen->count() }} {{ $reeks->delen->count() === 1 ? 'deel' : 'delen' }}</div>
  </div>
</div>

@if ($reeks->opmerking)
  <p class="sis-muted" dir="auto">{{ $reeks->opmerking }}</p>
@endif

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th style="width:80px;">Deel</th><th>Titel</th><th>Auteur(s)</th><th style="text-align:center;">Exemplaren</th><th class="row-act"></th></tr></thead>
    <tbody>
      @forelse ($reeks->delen as $deel)
        <tr>
          <td class="tnum">{{ $deel->deelnummer }}</td>
          <td class="nm" dir="auto"><a href="{{ route('bibliotheek.publicaties.show', $deel) }}">{{ $deel->titel }}</a></td>
          <td>{{ $deel->auteursTekst() }}</td>
          <td style="text-align:center;"><span class="iuasr-dash-status {{ $deel->aantalBeschikbaar() > 0 ? 's-approved' : 's-submitted' }}">{{ $deel->aantalBeschikbaar() }} / {{ $deel->exemplaren->count() }}</span></td>
          <td class="row-act"><a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('bibliotheek.publicaties.show', $deel) }}">Bekijken</a></td>
        </tr>
      @empty
        <tr><td colspan="5"><div class="iuasr-dash-empty" style="border:0;"><h3>Nog geen delen</h3></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

@if ($magBeheer)
  <form method="POST" action="{{ route('bibliotheek.reeksen.deel', $reeks) }}" class="sis-card sis-form" style="margin-top:12px; max-width:760px;">
    @csrf
    <h3>Deel toevoegen</h3>
    <p class="sis-muted">Auteurs, talen, vakgebied en jaar worden overgenomen van het eerste deel.</p>
    <div class="sis-fld-row sis-fld-row--3">
      <div class="sis-fld"><label>Deelnummer <span class="req">*</span></label><input type="number" name="deelnummer" min="1" max="999" required></div>
      <div class="sis-fld"><label>Eigen titel van het deel</label><input type="text" name="titel" maxlength="255" dir="auto" placeholder="Optioneel"></div>
      <div class="sis-fld"><label>Serienummer</label><input type="text" name="serienummer" maxlength="40" placeholder="Optioneel"></div>
    </div>
    <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Deel toevoegen</button></div></div>
  </form>
@endif
@endsection
