@extends('layouts.app')

@section('titel', 'Leerjaar-herbeoordeling')

@php
  $badge = ['positief' => 's-approved', 'voorwaardelijk' => 's-incomplete', 'negatief' => 's-rejected', 'onbekend' => 's-draft'];
  $adviesLabel = ['positief' => 'Positief (mag door)', 'voorwaardelijk' => 'Voorwaardelijk', 'negatief' => 'Negatief', 'onbekend' => 'Drempel niet ingesteld'];
@endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Leerjaar-herbeoordeling</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Leerjaar-herbeoordeling</h1>
    <div class="summary">Behaalde EC t.o.v. de EC-overgangsdrempel per opleiding · overgangsadvies</div>
  </div>
</div>

<div class="iuasr-dash-stats" style="grid-template-columns:repeat(4,1fr);margin-bottom:16px;">
  <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Positief</span><span class="val">{{ $telling['positief'] ?? 0 }}</span><span class="delta">mag door</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Voorwaardelijk</span><span class="val">{{ $telling['voorwaardelijk'] ?? 0 }}</span><span class="delta">bijna aan norm</span></div>
  <div class="iuasr-dash-stat iuasr-dash-stat--alert"><span class="lbl">Negatief</span><span class="val">{{ $telling['negatief'] ?? 0 }}</span><span class="delta">onder de norm</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Drempel niet ingesteld</span><span class="val">{{ $telling['onbekend'] ?? 0 }}</span><span class="delta">EC-drempel = leeg</span></div>
</div>

<form method="GET" action="{{ route('overgang') }}" class="iuasr-dash-filters" style="margin-bottom:16px;">
  <div class="search" style="grid-column:1 / -1;">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op studentnummer of naam… (Enter)">
  </div>
  <div class="sis-fld" style="margin:0;">
    <label>Opleiding</label>
    <select name="opleiding_id" onchange="this.form.submit()">
      <option value="">Alle opleidingen</option>
      @foreach ($opleidingen as $o)
        <option value="{{ $o->id }}" @selected($gekozenOpleiding == $o->id)>{{ $o->naam }}{{ $o->ec_overgang_drempel !== null ? ' (drempel '.$o->ec_overgang_drempel.' EC)' : ' (geen drempel)' }}</option>
      @endforeach
    </select>
  </div>
  <div class="sis-fld" style="margin:0;">
    <label>Leerjaar</label>
    <select name="leerjaar" onchange="this.form.submit()">
      <option value="">Alle</option>
      @for ($j = 1; $j <= 4; $j++)<option value="{{ $j }}" @selected($gekozenLeerjaar == $j)>Jaar {{ $j }}</option>@endfor
    </select>
  </div>
</form>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Studentnr.</th><th>Naam</th><th>Opleiding</th><th style="text-align:center;">Leerjaar</th><th style="text-align:right;">Behaald EC</th><th style="text-align:right;">Drempel</th><th>Overgangsadvies</th></tr></thead>
    <tbody>
      @forelse ($rijen as $r)
        @php $i = $r['inschrijving']; $a = $r['advies']; @endphp
        <tr>
          <td class="tnum">{{ $i->student->studentnummer }}</td>
          <td class="nm">{{ $i->student->volledigeNaam() }}</td>
          <td>{{ $i->opleiding?->code }}</td>
          <td class="tnum" style="text-align:center;">{{ $i->leerjaar }}</td>
          <td class="tnum" style="text-align:right;"><b>{{ $a['behaald'] }}</b> <span class="sis-muted" style="font-size:11px;">/ {{ $a['mogelijk'] }}</span></td>
          <td class="tnum" style="text-align:right;">{{ $a['drempel'] ?? '—' }}</td>
          <td><span class="iuasr-dash-status {{ $badge[$a['status']] }}">{{ $adviesLabel[$a['status']] }}</span></td>
        </tr>
      @empty
        <tr><td colspan="7"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen actieve studenten</h3><p>Er zijn geen actieve inschrijvingen voor deze selectie.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<p class="sis-tblnote" style="margin-top:12px;">De EC-overgangsdrempel is per opleiding instelbaar (Beheer → Opzoektabellen → Opleidingen). Advies <b>voorwaardelijk</b> = minstens 75% van de drempel behaald. Standaard staat de drempel op <b>30 EC</b> (landelijke BSA-norm vanaf studiejaar 2026-2027); pas dit per opleiding aan conform de OER.</p>
@endsection
