@extends('layouts.app')

@section('titel', 'Alumni')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ auth()->user()->rolIs('directie') ? route('rapporten.inzage') : route('rapporten') }}">Rapporten</a><span class="sep">›</span><b>Alumni</b></div>

<div class="sis-toolbar">
  <span class="meta"><b>Alumni-rapport</b> · afgestudeerde studenten · contactgegevens</span>
  <span class="grow"></span>
  <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ auth()->user()->rolIs('directie') ? route('rapporten.inzage') : route('rapporten') }}">Terug</a>
  <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="button" onclick="window.print()">Printen / PDF</button>
</div>

<div class="sis-paper-stage">
  <div class="sis-a4">
    <div class="sis-a4__head">
      <img src="{{ asset('assets/img/logo-dark.png') }}" alt="IUASR">
      <div class="org"><b>Islamic University of Applied Sciences Rotterdam</b>Bureau Studentenzaken<br>Postbus 12345 · 3000 AB Rotterdam</div>
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
      <span>Alumni-rapport · bevat geen cijfers of BSN · gegenereerd via IUASR SIS op {{ now()->format('d-m-Y') }}</span>
      <span class="sis-a4__watermark">Intern document</span>
    </div>
  </div>
</div>
@endsection
