@extends('layouts.app')

@section('titel', 'Bulk inschrijven')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Bulk inschrijven</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Bulk inschrijven</h1>
    <div class="summary">Meerdere studenten tegelijk inschrijven vanuit een CSV-export van het aanmeldportaal</div>
  </div>
</div>

<div class="sis-grid-2">
  <div class="sis-card">
    <div class="sis-card__hd"><h3>CSV uploaden</h3><span class="hint">Excel/export → Opslaan als CSV</span></div>
    @error('bestand')<div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $message }}</span></div>@enderror
    <form method="POST" action="{{ route('bulk-inschrijven.controle') }}" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
      @csrf
      <input type="file" name="bestand" accept=".csv,.txt" required style="flex:1;min-width:220px;">
      <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Controleren</button>
      <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('bulk-inschrijven.sjabloon') }}">Sjabloon downloaden</a>
    </form>
    <p class="sis-tblnote" style="margin-top:10px;">Na <b>Controleren</b> ziet u eerst welke studenten worden ingeschreven en welke regels worden overgeslagen; pas daarna schrijft u definitief in.</p>
  </div>

  <div class="sis-card">
    <div class="sis-card__hd"><h3>Kolommen</h3></div>
    <p class="sis-muted" style="font-size:13px;margin:0 0 8px;">De kopnamen worden automatisch herkend (Nederlands, hoofdletter-ongevoelig, <code>;</code> of <code>,</code> als scheidingsteken). Herkende kolommen:</p>
    <div style="display:flex;flex-wrap:wrap;gap:6px;">
      @foreach (['voornaam','tussenvoegsel','achternaam','geboortedatum','geslacht','nationaliteit','email','telefoon','straat','huisnummer','postcode','stad','provincie','land','iban','diploma','onderwijsinstelling','afstudeerjaar','opleiding','leerjaar'] as $k)
        <span class="sis-pill-soft" style="font-size:11.5px;padding:4px 10px;">{{ $k }}</span>
      @endforeach
    </div>
    <p class="sis-tblnote" style="margin-top:10px;"><b>Verplicht:</b> voornaam, achternaam en opleiding (code of naam, bv. <code>ISLTH</code>). Studentnummers worden automatisch toegekend en de vakken van het studiejaar automatisch toegewezen.</p>
    <div class="iuasr-dash-alert iuasr-dash-alert--warn" style="margin-top:12px;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <span><b>AVG:</b> het <b>BSN wordt niet geïmporteerd</b> (pas na akkoord van de Functionaris Gegevensbescherming). Gebruik in ontwikkeling uitsluitend synthetische gegevens.</span>
    </div>
  </div>
</div>
@endsection
