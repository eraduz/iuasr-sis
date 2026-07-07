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
  <div class="iuasr-dash-stat iuasr-dash-stat--alert"><span class="lbl">Studenten met achterstand</span><span class="val">{{ $achterstanden->count() }}</span><span class="delta">€ {{ number_format($achterstanden->sum(fn($r)=>$r['status']['openstaand']), 0, ',', '.') }} openstaand</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Terugbetalingen</span><span class="val">{{ $terugbetalingen->count() }}</span><span class="delta">€ {{ number_format($terugbetalingen->sum(fn($r)=>$r['status']['terugbetaling']), 0, ',', '.') }} teveel betaald</span></div>
  <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Berekening</span><span class="val" style="font-size:15px;line-height:2;">Pro rata</span><span class="delta">jaartarief ÷ 12 × maanden</span></div>
</div>

@if (session('import_resultaat'))
  @php $imp = session('import_resultaat'); @endphp
  <div class="iuasr-dash-alert {{ $imp['fouten'] ? 'iuasr-dash-alert--warn' : 'iuasr-dash-alert--info' }}" style="margin-bottom:16px;display:block;">
    <div style="display:flex;gap:8px;align-items:center;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><b>{{ $imp['aantal'] }} betaling(en) geïmporteerd@if($imp['fouten']), {{ count($imp['fouten']) }} regel(s) overgeslagen@endif.</b></div>
    @if ($imp['fouten'])
      <ul style="margin:8px 0 0 24px;font-size:13px;">
        @foreach (array_slice($imp['fouten'], 0, 25) as $f)<li>{{ $f }}</li>@endforeach
        @if (count($imp['fouten']) > 25)<li>… en {{ count($imp['fouten']) - 25 }} meer.</li>@endif
      </ul>
    @endif
  </div>
@endif

<div class="sis-card" style="margin-bottom:16px;">
  <div class="sis-card__hd"><h3>Betalingen importeren (CSV)</h3><span class="hint">Excel → Opslaan als CSV → hier uploaden</span></div>
  @if ($errors->has('bestand'))
    <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $errors->first('bestand') }}</span></div>
  @endif
  <form method="POST" action="{{ route('financien.import') }}" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
    @csrf
    <input type="file" name="bestand" accept=".csv,.txt" required style="flex:1;min-width:220px;">
    <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Importeren</button>
    <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('financien.import.sjabloon') }}">Sjabloon downloaden</a>
  </form>
  <p class="sis-tblnote" style="margin-top:10px;">Kolommen: <b>studentnummer; bedrag; datum; betaalwijze; opmerking</b>. Datum bijv. 15-09-2025, bedrag bijv. 4000,00. Elke betaling wordt gekoppeld aan de meest recente inschrijving van de student.</p>
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

@if ($terugbetalingen->isNotEmpty())
  <div class="sis-card" style="margin-top:16px;">
    <div class="sis-card__hd"><h3>Terug te betalen</h3><span class="hint">teveel betaald t.o.v. het pro rata verschuldigde bedrag</span></div>
    <div class="iuasr-dash-tbl-card" style="border:0;">
      <table class="iuasr-dash-tbl">
        <thead><tr><th>Studentnr.</th><th>Naam</th><th>Verschuldigd</th><th>Betaald</th><th>Terugbetaling</th><th class="row-act"></th></tr></thead>
        <tbody>
          @foreach ($terugbetalingen as $r)
            @php $s = $r['student']; $st = $r['status']; @endphp
            <tr>
              <td class="tnum">{{ $s->studentnummer }}</td>
              <td class="nm">{{ $s->volledigeNaam() }}</td>
              <td class="tnum">€ {{ number_format($st['verschuldigd'], 2, ',', '.') }}</td>
              <td class="tnum">€ {{ number_format($st['betaald'], 2, ',', '.') }}</td>
              <td><span class="iuasr-dash-status s-submitted">€ {{ number_format($st['terugbetaling'], 2, ',', '.') }}</span></td>
              <td class="row-act"><a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('financien.student', $s) }}">Openen</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif
@endsection
