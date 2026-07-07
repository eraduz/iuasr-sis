@extends('layouts.app')

@section('titel', 'Financiën')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Betalingen &amp; achterstand</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Betalingen &amp; achterstand</h1>
    <div class="summary">Registreer betalingen en zie welke studenten een openstaande schuld hebben</div>
  </div>
</div>

<div class="iuasr-dash-stats" style="grid-template-columns:repeat(3,1fr);">
  <div class="iuasr-dash-stat iuasr-dash-stat--alert"><span class="lbl">Studenten met achterstand</span><span class="val">{{ $achterstanden->count() }}</span><span class="delta">openstaande schuld</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Totaal openstaand</span><span class="val" style="font-size:22px;">€ {{ number_format($achterstanden->sum(fn($r)=>$r['status']['openstaand']), 0, ',', '.') }}</span><span class="delta">over alle studenten</span></div>
  <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Rol</span><span class="val" style="font-size:15px;line-height:2;">Financiën</span><span class="delta">registratie &amp; inzage</span></div>
</div>

<form method="GET" action="{{ route('financien') }}" class="iuasr-dash-filters">
  <div class="search" style="grid-column:1 / -1;">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek een student om een betaling te registreren (studentnummer of naam)…">
  </div>
</form>

@if ($zoek !== '')
  <div class="iuasr-dash-tbl-card" style="margin-bottom:16px;">
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Studentnr.</th><th>Naam</th><th class="row-act"></th></tr></thead>
      <tbody>
        @forelse ($resultaten as $s)
          <tr><td class="tnum">{{ $s->studentnummer }}</td><td class="nm">{{ $s->volledigeNaam() }}</td><td class="row-act"><a class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" href="{{ route('financien.student', $s) }}">Openen</a></td></tr>
        @empty
          <tr><td colspan="3"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen studenten gevonden</h3></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endif

<div class="sis-card">
  <div class="sis-card__hd"><h3>Openstaande schulden</h3><span class="hint">automatisch bepaald uit collegegeld − betalingen</span></div>
  <div class="iuasr-dash-tbl-card" style="border:0;">
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Studentnr.</th><th>Naam</th><th>Verschuldigd</th><th>Betaald</th><th>Openstaand</th><th class="row-act"></th></tr></thead>
      <tbody>
        @forelse ($achterstanden as $r)
          @php $s = $r['student']; $st = $r['status']; @endphp
          <tr>
            <td class="tnum">{{ $s->studentnummer }}</td>
            <td class="nm">{{ $s->volledigeNaam() }}</td>
            <td class="tnum">€ {{ number_format($st['verschuldigd'], 2, ',', '.') }}</td>
            <td class="tnum">€ {{ number_format($st['betaald'], 2, ',', '.') }}</td>
            <td><span class="iuasr-dash-status s-rejected">€ {{ number_format($st['openstaand'], 2, ',', '.') }}</span></td>
            <td class="row-act"><a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('financien.student', $s) }}">Openen</a></td>
          </tr>
        @empty
          <tr><td colspan="6"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen achterstanden</h3><p>Alle studenten met een tarief hebben hun collegegeld voldaan.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
