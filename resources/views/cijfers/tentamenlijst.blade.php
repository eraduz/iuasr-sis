@extends('layouts.app')

@section('titel', 'Presentielijst · '.$vak->code)

@php
  $terug = auth()->user()->rolIs('docent') ? route('mijn-vakken') : route('cijferoverzicht');
  $terugLabel = auth()->user()->rolIs('docent') ? 'Mijn vakken' : 'Cijferoverzicht';
@endphp

@push('head')
<style>
  .presentie-tbl td { height: 26px; }
  .presentie-hand { width: 40%; }
</style>
@endpush

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ $terug }}">{{ $terugLabel }}</a><span class="sep">›</span><b>Presentielijst {{ $vak->code }}</b></div>

<div class="sis-toolbar">
  <span class="meta"><b>Presentielijst / tentamenlijst</b> · {{ $vak->code }} — {{ $vak->naam }} · {{ $samenvatting['aantal'] }} deelnemers</span>
  <span class="grow"></span>
  <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ $terug }}">Terug</a>
  <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="button" onclick="window.print()">Printen / PDF</button>
  <form method="POST" action="{{ route('vakken.tentamenlijst.pdf', $vak) }}" style="display:inline-flex;gap:8px;align-items:center;">
    @csrf
    <input type="text" name="ontvanger" required value="{{ old('ontvanger') }}" placeholder="Verstrekt aan (bv. examencommissie)" style="padding:7px 10px;border:1px solid var(--borderColor,#cfcfd6);border-radius:6px;min-width:180px;font-size:13px;">
    <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Ondertekende PDF</button>
  </form>
</div>
@error('ontvanger')<div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $message }}</span></div>@enderror

<div class="sis-paper-stage">
  <div class="sis-a4">
    <div class="sis-a4__head">
      <img src="{{ asset('assets/img/iuasr-logo.png') }}?v={{ filemtime(public_path('assets/img/iuasr-logo.png')) }}" alt="IUASR">
      <div class="org">Bergsingel 135 · 3037 GC Rotterdam<br>Tel: +31 (0)10 485 47 21<br>szaken@iuasr.nl</div>
    </div>
    <h1>Presentielijst</h1>
    <p class="doc-sub">{{ $vak->code }} — {{ $vak->naam }} · {{ $vak->opleiding?->naam }} · {{ $periode->naam }}</p>

    <dl class="kv" style="grid-template-columns:190px 1fr 150px 1fr;">
      <dt>Docent</dt><dd>{{ $vak->docent?->achternaam ?? '—' }}</dd>
      <dt>Aantal deelnemers</dt><dd>{{ $samenvatting['aantal'] }}</dd>
      <dt>Datum tentamen</dt><dd>………………………</dd>
      <dt>Tijd / lokaal</dt><dd>………………………</dd>
    </dl>

    <table class="sis-a4-tbl presentie-tbl">
      <thead><tr><th style="width:26px;">#</th><th style="width:110px;">Studentnr.</th><th>Naam</th><th class="presentie-hand">Handtekening</th></tr></thead>
      <tbody>
        @forelse ($rijen as $rij)
          <tr>
            <td>{{ $loop->iteration }}</td>
            <td>{{ $rij['student']->studentnummer }}</td>
            <td>{{ $rij['student']->volledigeNaam() }}</td>
            <td></td>
          </tr>
        @empty
          <tr><td colspan="4" style="text-align:center;color:var(--blackAltText);padding:18px;">Geen deelnemers voor dit vak.</td></tr>
        @endforelse
      </tbody>
    </table>

    <div class="sis-a4__sig">
      <div class="line">Naam surveillant / docent</div>
      <div class="line">Handtekening surveillant</div>
    </div>
    <div class="sis-a4__foot">
      <span>Presentielijst · bevat geen cijfers of studiepunten · {{ $vak->code }} · gegenereerd {{ now()->format('d-m-Y') }}</span>
      <span class="sis-a4__watermark">Aanwezigheid</span>
    </div>
  </div>
</div>

<p class="sis-tblnote" style="margin-top:12px;">De student tekent naast de eigen naam om aanwezigheid te bevestigen. Cijfers en studiepunten staan bewust <b>niet</b> op deze lijst (privacy). Resultaten voert u in via Cijferoverzicht.</p>
@endsection
