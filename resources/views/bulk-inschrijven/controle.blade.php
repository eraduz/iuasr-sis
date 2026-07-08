@extends('layouts.app')

@section('titel', 'Bulk inschrijven — controle')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ route('bulk-inschrijven') }}">Bulk inschrijven</a><span class="sep">›</span><b>Controle</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Controleer de inschrijvingen</h1>
    <div class="summary">Bestand <b>{{ $bestandsnaam }}</b> · studiejaar {{ $periode->naam }} · controleer voordat u definitief inschrijft</div>
  </div>
</div>

<div class="iuasr-dash-stats" style="grid-template-columns:repeat(2,1fr);margin-bottom:16px;">
  <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Wordt ingeschreven</span><span class="val">{{ count($geldig) }}</span><span class="delta">nieuwe studenten</span></div>
  <div class="iuasr-dash-stat {{ $fouten ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Wordt overgeslagen</span><span class="val">{{ count($fouten) }}</span><span class="delta">regels met een fout/duplicaat</span></div>
</div>

@if ($geldig)
  <div class="sis-card">
    <div class="sis-card__hd"><h3>Nieuwe inschrijvingen</h3><span class="hint">{{ count($geldig) }} · studentnummer wordt automatisch toegekend</span></div>
    <div class="iuasr-dash-tbl-card" style="border:0;max-height:440px;overflow:auto;">
      <table class="iuasr-dash-tbl">
        <thead><tr><th>Naam</th><th>Geb.datum</th><th>Opleiding</th><th>Leerjaar</th><th>E-mail</th><th>Woonplaats</th></tr></thead>
        <tbody>
          @foreach ($geldig as $r)
            <tr>
              <td class="nm">{{ $r['naam'] }}</td>
              <td class="dt">{{ $r['geboortedatum'] ? \Carbon\Carbon::parse($r['geboortedatum'])->format('d-m-Y') : '—' }}</td>
              <td>{{ $r['opleiding'] }}</td>
              <td class="tnum">{{ $r['leerjaar'] }}</td>
              <td>{{ $r['email_prive'] ?? '—' }}</td>
              <td>{{ $r['woonplaats'] ?? '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@else
  <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:16px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg>
    <span>Er zijn geen geldige regels om in te schrijven. Corrigeer het bestand en probeer opnieuw.</span>
  </div>
@endif

@if ($fouten)
  <div class="sis-card" style="margin-top:16px;">
    <div class="sis-card__hd"><h3>Overgeslagen regels</h3><span class="hint">{{ count($fouten) }}</span></div>
    <ul style="margin:0;padding:12px 12px 12px 28px;font-size:13px;color:var(--blackAltText);">
      @foreach ($fouten as $f)<li>{{ $f }}</li>@endforeach
    </ul>
  </div>
@endif

<div class="sis-savebar" style="margin-top:16px;">
  <span class="status">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
    Er is nog niets opgeslagen. Bevestig om definitief in te schrijven.
  </span>
  <span class="grow"></span>
  <a class="iuasr-dash-btn" href="{{ route('bulk-inschrijven') }}">Annuleren</a>
  @if ($geldig)
    <form method="POST" action="{{ route('bulk-inschrijven.importeer') }}" style="display:inline;">
      @csrf
      <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Definitief inschrijven ({{ count($geldig) }})</button>
    </form>
  @endif
</div>
@endsection
