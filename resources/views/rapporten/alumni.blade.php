@extends('layouts.app')

@section('titel', 'Alumni')

@php
  // Terugverwijzing naar een rapportenpagina die deze rol ook mág openen:
  // Directie heeft 'rapporten.inzage', Studentenzaken 'rapporten'. Het
  // Schoolbestuur heeft geen rapportenoverzicht en gaat terug naar het dashboard.
  $u = auth()->user();
  $terug = match (true) {
    $u->rolIs('directie') => route('rapporten.inzage'),
    $u->rolIs('bestuur') => route('dashboard'),
    default => route('rapporten'),
  };
  $terugLabel = $u->rolIs('bestuur') ? 'Dashboard' : 'Rapporten';
@endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a>@unless ($u->rolIs('bestuur'))<span class="sep">›</span><a href="{{ $terug }}">Rapporten</a>@endunless<span class="sep">›</span><b>Alumni</b></div>

<div class="sis-toolbar">
  <span class="meta"><b>Alumni-rapport</b> · afgestudeerde studenten · contactgegevens</span>
  <form method="GET" action="{{ route('rapporten.alumni') }}" style="display:flex;gap:6px;align-items:center;">
    <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op studentnummer of naam…" style="padding:7px 11px;border:1px solid var(--borderColor,#cfcfd6);border-radius:6px;font-size:13px;min-width:220px;">
  </form>
  <span class="grow"></span>
  <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ $terug }}" title="Terug naar {{ $terugLabel }}">Terug</a>
  <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="button" onclick="window.print()">Printen / PDF</button>
</div>

<div class="sis-paper-stage">
  <div class="sis-a4">
    <div class="sis-a4__head">
      <img src="{{ asset('assets/img/iuasr-logo.png') }}?v={{ filemtime(public_path('assets/img/iuasr-logo.png')) }}" alt="IUASR">
      <div class="org">Bergsingel 135 · 3037 GC Rotterdam<br>Tel: +31 (0)10 485 47 21<br>szaken@iuasr.nl</div>
    </div>
    <h1>Alumni</h1>
    <p class="doc-sub">Afgestudeerde studenten · contactgegevens</p>

    <dl class="kv">
      <dt>Aantal alumni</dt><dd>{{ $alumni->count() }}</dd>
      <dt>Gegenereerd</dt><dd>{{ now()->format('d-m-Y') }} · {{ auth()->user()->naam }}</dd>
    </dl>

    <table class="sis-a4-tbl">
      <thead><tr><th style="width:26px;">#</th><th>Studentnr.</th><th>Naam</th><th>Telefoon</th><th>E-mail</th><th>Opleiding</th></tr></thead>
      <tbody>
        @forelse ($alumni as $i)
          <tr>
            <td>{{ $loop->iteration }}</td>
            <td>{{ $i->student->studentnummer }}</td>
            <td>{{ $i->student->volledigeNaam() }}</td>
            <td>{{ $i->student->telefoon ?? '—' }}</td>
            <td>{{ $i->student->email ?? $i->student->email_prive ?? '—' }}</td>
            <td>{{ $i->opleiding?->code ?? '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="6" style="text-align:center;color:var(--blackAltText);padding:18px;">Nog geen afgestudeerde studenten.</td></tr>
        @endforelse
      </tbody>
    </table>

    <div class="sis-a4__foot">
      <span>Alumni-rapport · bevat geen cijfers of BSN · gegenereerd via IUASR Management Systeem op {{ now()->format('d-m-Y') }}</span>
      <span class="sis-a4__watermark">Intern document</span>
    </div>
  </div>
</div>
@endsection
