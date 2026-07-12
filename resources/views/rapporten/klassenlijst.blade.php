@extends('layouts.app')

@section('titel', 'Klassenlijst')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ route('rapporten') }}">Rapporten</a><span class="sep">›</span><b>Klassenlijst</b></div>

<div class="sis-toolbar">
  <span class="meta"><b>Klassenlijst</b> · {{ $opleiding?->naam ?? 'alle opleidingen' }}{{ $klas ? ' · '.$klas->code : '' }}{{ $periode ? ' · '.$periode->naam : '' }}</span>
  <form method="GET" action="{{ route('rapporten.klassenlijst') }}" style="display:flex;gap:6px;align-items:center;">
    @if($opleiding)<input type="hidden" name="opleiding_id" value="{{ $opleiding->id }}">@endif
    @if($periode)<input type="hidden" name="periode_id" value="{{ $periode->id }}">@endif
    @if($klas)<input type="hidden" name="klas_id" value="{{ $klas->id }}">@endif
    <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op studentnummer of naam…" style="padding:7px 11px;border:1px solid var(--borderColor,#cfcfd6);border-radius:6px;font-size:13px;min-width:220px;">
  </form>
  <span class="grow"></span>
  <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('rapporten') }}">Terug</a>
  <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="button" onclick="window.print()">Printen / PDF</button>
</div>

<div class="sis-paper-stage">
  <div class="sis-a4">
    <div class="sis-a4__head">
      <img src="{{ asset('assets/img/iuasr-logo.png') }}?v={{ filemtime(public_path('assets/img/iuasr-logo.png')) }}" alt="IUASR">
      <div class="org">Bergsingel 135 · 3037 GC Rotterdam<br>Tel: +31 (0)10 485 47 21<br>szaken@iuasr.nl</div>
    </div>
    <h1>Klassenlijst</h1>
    <p class="doc-sub">{{ $opleiding?->naam ?? 'Alle opleidingen' }}{{ $klas ? ' · klas '.$klas->code : '' }}{{ $periode ? ' · '.$periode->naam : '' }}</p>

    <dl class="kv">
      <dt>Aantal studenten</dt><dd>{{ $inschrijvingen->count() }}</dd>
      <dt>Gegenereerd</dt><dd>{{ now()->format('d-m-Y') }} · {{ auth()->user()->naam }}</dd>
    </dl>

    <table class="sis-a4-tbl">
      <thead><tr><th style="width:26px;">#</th><th>Studentnr.</th><th>Naam</th><th>Opleiding</th><th>Klas</th><th>Status</th></tr></thead>
      <tbody>
        @forelse ($inschrijvingen as $i)
          <tr>
            <td>{{ $loop->iteration }}</td>
            <td>{{ $i->student->studentnummer }}</td>
            <td>{{ $i->student->volledigeNaam() }}</td>
            <td>{{ $i->opleiding?->code ?? '—' }}</td>
            <td>{{ $i->klas?->code ?? '—' }}</td>
            <td>{{ $i->status->label() }}</td>
          </tr>
        @empty
          <tr><td colspan="6" style="text-align:center;color:var(--blackAltText);padding:18px;">Geen studenten gevonden voor deze selectie.</td></tr>
        @endforelse
      </tbody>
    </table>

    <div class="sis-a4__foot">
      <span>Klassenlijst · bevat geen cijfers · gegenereerd via IUASR Management Systeem op {{ now()->format('d-m-Y') }}</span>
      <span class="sis-a4__watermark">Intern document</span>
    </div>
  </div>
</div>
@endsection
