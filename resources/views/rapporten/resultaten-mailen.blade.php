@extends('layouts.app')

@section('titel', 'Resultaten mailen')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ route('cijferlijst', ['opleiding_id' => $opleiding->id]) }}">Cijferlijst</a><span class="sep">›</span><b>Resultaten mailen</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Definitieve resultaten mailen</h1>
    <div class="summary">{{ $opleiding->naam }} · controleer vóór verzenden</div>
  </div>
</div>

<div class="iuasr-dash-alert iuasr-dash-alert--warn" style="margin-bottom:16px;">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
  <span>Elke student ontvangt <b>individueel</b> zijn/haar eigen (vastgestelde) cijferlijst als ondertekende PDF. Studenten zonder vastgestelde resultaten of zonder e-mailadres worden overgeslagen. Verzending wordt gelogd.</span>
</div>

<div class="iuasr-dash-stats" style="grid-template-columns:repeat(2,1fr);margin-bottom:16px;">
  <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Ontvangers</span><span class="val">{{ count($teVersturen) }}</span><span class="delta">krijgen een e-mail</span></div>
  <div class="iuasr-dash-stat {{ count($overgeslagen) > 0 ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Overgeslagen</span><span class="val">{{ count($overgeslagen) }}</span><span class="delta">geen resultaten / e-mail</span></div>
</div>

<div class="sis-card" style="margin-bottom:16px;">
  <div class="sis-card__hd"><h3>Ontvangers</h3><span class="hint">{{ count($teVersturen) }} student(en)</span></div>
  @if (empty($teVersturen))
    <p class="sis-muted" style="font-size:13px;margin:0;">Geen studenten met vastgestelde resultaten én een e-mailadres.</p>
  @else
    <div class="iuasr-dash-tbl-card" style="border:0;">
      <table class="iuasr-dash-tbl">
        <thead><tr><th>Studentnr.</th><th>Naam</th><th>E-mailadres</th></tr></thead>
        <tbody>
          @foreach ($teVersturen as $r)
            <tr><td class="tnum">{{ $r['student']->studentnummer }}</td><td class="nm">{{ $r['student']->volledigeNaam() }}</td><td>{{ $r['email'] }}</td></tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

@if (! empty($overgeslagen))
  <div class="sis-card" style="margin-bottom:16px;">
    <div class="sis-card__hd"><h3>Overgeslagen</h3><span class="hint">{{ count($overgeslagen) }} student(en)</span></div>
    <div class="iuasr-dash-tbl-card" style="border:0;">
      <table class="iuasr-dash-tbl">
        <thead><tr><th>Studentnr.</th><th>Naam</th><th>Reden</th></tr></thead>
        <tbody>
          @foreach ($overgeslagen as $r)
            <tr><td class="tnum">{{ $r['student']->studentnummer }}</td><td class="nm">{{ $r['student']->volledigeNaam() }}</td><td><span class="iuasr-dash-status s-draft">{{ $r['reden'] }}</span></td></tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif

<div style="display:flex;gap:10px;align-items:center;">
  <a class="iuasr-dash-btn" href="{{ route('cijferlijst', ['opleiding_id' => $opleiding->id]) }}">Terug</a>
  @if (! empty($teVersturen))
    <form method="POST" action="{{ route('resultaten-mailen.versturen') }}" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Bezig met verzenden…';">
      @csrf
      <input type="hidden" name="opleiding_id" value="{{ $opleiding->id }}">
      <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Definitief versturen naar {{ count($teVersturen) }} student(en)</button>
    </form>
  @endif
</div>
@endsection
