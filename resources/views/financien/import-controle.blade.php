@extends('layouts.app')

@section('titel', 'Import controleren')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ route('financien') }}">Financiën</a><span class="sep">›</span><b>Import controleren</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Import controleren</h1>
    <div class="summary">Bestand <b>{{ $bestandsnaam }}</b> · controleer voordat u definitief importeert</div>
  </div>
</div>

<div class="iuasr-dash-stats" style="grid-template-columns:repeat(2,1fr);margin-bottom:16px;">
  <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Wordt geïmporteerd</span><span class="val">{{ count($geldig) }}</span><span class="delta">geldige regels</span></div>
  <div class="iuasr-dash-stat {{ $fouten ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Wordt overgeslagen</span><span class="val">{{ count($fouten) }}</span><span class="delta">regels met een fout</span></div>
</div>

@if ($geldig)
  <div class="sis-card">
    <div class="sis-card__hd"><h3>Te importeren betalingen</h3><span class="hint">{{ count($geldig) }} regel(s)</span></div>
    <div class="iuasr-dash-tbl-card" style="border:0;max-height:420px;overflow:auto;">
      <table class="iuasr-dash-tbl">
        <thead><tr><th>Studentnr.</th><th>Naam</th><th style="text-align:right;">Bedrag</th><th>Datum</th><th>Betaalwijze</th><th>Opmerking</th></tr></thead>
        <tbody>
          @foreach ($geldig as $r)
            <tr>
              <td class="tnum">{{ $r['studentnummer'] }}</td>
              <td class="nm">{{ $r['naam'] }}</td>
              <td class="tnum" style="text-align:right;">€ {{ number_format($r['bedrag'], 2, ',', '.') }}</td>
              <td class="dt">{{ \Carbon\Carbon::parse($r['datum'])->format('d-m-Y') }}</td>
              <td>{{ $r['betaalwijze'] ?? '—' }}</td>
              <td>{{ $r['opmerking'] ?? '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@else
  <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:16px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <span>Er zijn geen geldige regels om te importeren. Corrigeer het bestand en probeer opnieuw.</span>
  </div>
@endif

@if ($fouten)
  <div class="sis-card" style="margin-top:16px;">
    <div class="sis-card__hd"><h3>Overgeslagen regels</h3><span class="hint">{{ count($fouten) }} regel(s) met een fout</span></div>
    <ul style="margin:0;padding:12px 12px 12px 28px;font-size:13px;color:var(--blackAltText);">
      @foreach ($fouten as $f)<li>{{ $f }}</li>@endforeach
    </ul>
  </div>
@endif

<div class="sis-savebar" style="margin-top:16px;">
  <span class="status">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
    Er is nog niets opgeslagen. Bevestig om definitief te importeren.
  </span>
  <span class="grow"></span>
  <a class="iuasr-dash-btn" href="{{ route('financien') }}">Annuleren</a>
  @if ($geldig)
    <form method="POST" action="{{ route('financien.import') }}" style="display:inline;">
      @csrf
      <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Definitief importeren ({{ count($geldig) }})</button>
    </form>
  @endif
</div>
@endsection
