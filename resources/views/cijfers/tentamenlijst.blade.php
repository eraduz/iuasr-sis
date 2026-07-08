@extends('layouts.app')

@section('titel', 'Tentamenlijst · '.$vak->code)

@php
  $terug = auth()->user()->rolIs('docent') ? route('mijn-vakken') : route('cijferoverzicht');
  $terugLabel = auth()->user()->rolIs('docent') ? 'Mijn vakken' : 'Cijferoverzicht';
  $eindTekst = fn ($e) => match ($e['status']) {
      'cijfer' => number_format($e['cijfer'], 1, ',', ''),
      'vr' => 'VR', 'onvolledig' => 'onvolledig', default => '—',
  };
@endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ $terug }}">{{ $terugLabel }}</a><span class="sep">›</span><b>Tentamenlijst {{ $vak->code }}</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Tentamenlijst — {{ $vak->naam }}</h1>
    <div class="summary"><b>{{ $vak->code }}</b> · {{ $vak->opleiding?->naam }} · {{ $periode->naam }} · docent: {{ $vak->docent?->achternaam ?? '—' }}</div>
  </div>
  <div class="iuasr-dash-vhead__actions" style="gap:8px;flex-wrap:wrap;align-items:center;">
    <span class="iuasr-dash-status {{ $lijst->status->badge() }}">{{ $lijst->status->label() }}</span>
    <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="button" onclick="window.print()">Printen</button>
    <form method="POST" action="{{ route('vakken.tentamenlijst.pdf', $vak) }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      @csrf
      <input type="text" name="ontvanger" required value="{{ old('ontvanger') }}" placeholder="Verstrekt aan (bv. examencommissie)" style="padding:8px 10px;border:1px solid var(--borderColor,#cfcfd6);border-radius:8px;min-width:200px;">
      <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Ondertekende PDF</button>
    </form>
  </div>
</div>
@error('ontvanger')<div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $message }}</span></div>@enderror

<div class="iuasr-dash-stats" style="grid-template-columns:repeat(3,1fr);margin-bottom:16px;">
  <div class="iuasr-dash-stat"><span class="lbl">Deelnemers</span><span class="val">{{ $samenvatting['aantal'] }}</span></div>
  <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Geslaagd (EC toegekend)</span><span class="val">{{ $samenvatting['geslaagd'] }}</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Gemiddeld eindcijfer</span><span class="val">{{ $samenvatting['gemiddeld'] !== null ? number_format($samenvatting['gemiddeld'],1,',','') : '—' }}</span></div>
</div>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead>
      <tr>
        <th style="width:200px;">Student</th>
        @foreach ($vak->toetsonderdelen as $od)
          <th style="text-align:center;">{{ $od->naam }}<br><span class="sis-weegcell">{{ rtrim(rtrim(number_format($od->weging*100,0),'0'),'.') }}%</span></th>
        @endforeach
        <th style="text-align:right;">Eindcijfer</th>
        <th style="text-align:center;">EC</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($rijen as $rij)
        @php $eind = $rij['eind']; $ec = $rij['ec']; @endphp
        <tr>
          <td class="nm">{{ $rij['student']->volledigeNaam() }}<small>{{ $rij['student']->studentnummer }}</small></td>
          @foreach ($vak->toetsonderdelen as $od)
            @php $res = $rij['perOnderdeel'][$od->id] ?? null; @endphp
            <td class="tnum" style="text-align:center;">
              @if ($res && $res->vrijstelling)<span class="sis-muted">VR</span>
              @elseif ($res && $res->cijfer !== null)<span style="color:{{ (float)$res->cijfer < $grens ? 'var(--secColor100)' : 'inherit' }};">{{ number_format($res->cijfer,1,',','') }}</span>
              @else<span class="sis-muted">—</span>@endif
            </td>
          @endforeach
          <td class="tnum" style="text-align:right;font-weight:600;">
            @if ($eind['status']==='cijfer')<span style="color:{{ $eind['cijfer'] < $grens ? 'var(--secColor100)' : 'var(--heritage-groen,#285C4D)' }};">{{ $eindTekst($eind) }}</span>
            @else<span class="sis-muted">{{ $eindTekst($eind) }}</span>@endif
          </td>
          <td class="tnum" style="text-align:center;">{{ $ec !== null ? $ec : '—' }}</td>
          <td>
            @if ($eind['status']==='vr')<span class="iuasr-dash-status s-approved">Vrijstelling</span>
            @elseif (($ec ?? 0) > 0)<span class="iuasr-dash-status s-approved">Behaald</span>
            @elseif ($eind['status']==='cijfer')<span class="iuasr-dash-status s-rejected">Niet behaald</span>
            @else<span class="iuasr-dash-status s-draft">Open</span>@endif
          </td>
        </tr>
      @empty
        <tr><td colspan="{{ $vak->toetsonderdelen->count() + 4 }}"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen deelnemers</h3></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<p class="sis-tblnote" style="margin-top:12px;">Eindcijfer = gewogen gemiddelde van de beste poging per onderdeel (cesuur {{ number_format($grens,1,',','') }}). EC worden toegekend als álle meetellende onderdelen voldoende zijn.</p>
@endsection
