@extends('layouts.app')

@section('titel', 'Cursisten')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('cursussen.dashboard') }}">Cursussen</a><span class="sep">›</span><b>Cursisten</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Cursisten</h1>
    <div class="summary">{{ $cursisten->total() }} cursisten</div>
  </div>
  <div class="iuasr-dash-vhead__actions">
    <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('cursisten.create') }}">Cursist toevoegen</a>
  </div>
</div>

{{-- Bulk-import (Excel/CSV) --}}
<div class="sis-card" style="margin-bottom:16px;">
  <div class="sis-card__hd"><h3>Bulk-import</h3><span class="hint">Excel (.xlsx) of CSV</span></div>
  @if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $errors->first() }}</span></div>
  @endif
  <form method="POST" action="{{ route('cursisten.import.controle') }}" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    @csrf
    <input type="file" name="bestand" accept=".xlsx,.csv,.txt" required>
    <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Controleren</button>
    <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('cursisten.import.sjabloon') }}">Sjabloon downloaden</a>
  </form>
  <p class="sis-tblnote" style="margin-top:8px;">Kolommen: <b>voornaam; tussenvoegsel; achternaam; geboortedatum; email; telefoon; adres; postcode; woonplaats; cursuscode</b>. De kolom <b>cursuscode</b> is optioneel: staat er een geldige code, dan wordt de cursist meteen op die cursus ingeschreven. Na <b>Controleren</b> ziet u eerst een overzicht.</p>
</div>

<form method="GET" action="{{ route('cursisten') }}" class="sis-toolbar" style="margin-bottom:12px;">
  <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op cursistnummer, naam of e-mail" style="min-width:260px;">
  <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Zoeken</button>
</form>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Cursistnr.</th><th>Naam</th><th>E-mail</th><th>Telefoon</th><th style="text-align:center;">Inschrijvingen</th><th class="row-act"></th></tr></thead>
    <tbody>
      @forelse ($cursisten as $c)
        <tr>
          <td class="tnum">{{ $c->cursistnummer }}</td>
          <td class="nm"><a href="{{ route('cursisten.show', $c) }}">{{ $c->volledigeNaam() }}</a></td>
          <td>{{ $c->email ?? '—' }}</td>
          <td>{{ $c->telefoon ?? '—' }}</td>
          <td class="tnum" style="text-align:center;">{{ $c->inschrijvingen_count }}</td>
          <td class="row-act"><a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('cursisten.show', $c) }}">Bekijken</a></td>
        </tr>
      @empty
        <tr><td colspan="6"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen cursisten</h3><p>Voeg een cursist toe of importeer een bestand.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div style="margin-top:12px;">{{ $cursisten->links() }}</div>
@endsection
