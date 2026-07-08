@extends('layouts.app')

@section('titel', 'Cijferlijst')

@php
  $grens = 5.5;
  $eindTekst = function ($eind) {
      return match ($eind['status']) {
          'cijfer' => number_format($eind['cijfer'], 1, ',', ''),
          'vr' => 'VR',
          'onvolledig' => 'onvolledig',
          default => '—',
      };
  };
@endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Cijferlijst</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Cijferlijst</h1>
    <div class="summary">Officieel cijferoverzicht per student · per studiejaar met eindcijfer en EC</div>
  </div>
  @if ($student && $transcript)
    <div class="iuasr-dash-vhead__actions" style="gap:8px;flex-wrap:wrap;align-items:center;">
      <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('cijferlijst') }}">Andere student</a>
      <form method="POST" action="{{ route('cijferlijst.pdf', $student) }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        @csrf
        <input type="text" name="ontvanger" required value="{{ old('ontvanger') }}" placeholder="Verstrekt aan (bv. student / DUO)" style="padding:8px 10px;border:1px solid var(--borderColor,#cfcfd6);border-radius:8px;min-width:220px;">
        <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Ondertekende PDF</button>
      </form>
    </div>
  @endif
</div>
@error('ontvanger')<div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $message }}</span></div>@enderror

@if (! $student)
  <div class="sis-card">
    <div class="sis-card__hd"><h3>Kies een student</h3></div>
    <form method="GET" action="{{ route('cijferlijst') }}" style="display:flex;gap:8px;margin-bottom:12px;">
      <div class="search" style="position:relative;flex:1;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--blackAltText);pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op studentnummer of naam…" autofocus style="width:100%;height:38px;padding:7px 11px 7px 34px;font-size:13.5px;border:1px solid var(--borderColor);border-radius:4px;">
      </div>
      <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Zoek</button>
    </form>
    @if ($zoek !== '')
      <div class="sis-worklist">
        @forelse ($resultaten as $r)
          <a class="sis-work" href="{{ route('cijferlijst', ['student' => $r->id]) }}">
            <span class="sis-work__ic"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
            <div class="sis-work__bd"><b>{{ $r->volledigeNaam() }}</b><small>{{ $r->studentnummer }}</small></div>
            <span class="sis-work__meta">Kies →</span>
          </a>
        @empty
          <p class="sis-muted" style="font-size:13px;margin:4px 2px;">Geen studenten gevonden voor “{{ $zoek }}”.</p>
        @endforelse
      </div>
    @else
      <p class="sis-muted" style="font-size:12.5px;margin:0;">Typ een studentnummer of naam en klik op Zoek.</p>
    @endif
  </div>
@else
  <div class="sis-card" style="margin-bottom:16px;">
    <div class="iuasr-dash-candidate__hd" style="margin:0;padding:0;border:0;">
      <span class="iuasr-dash-candidate__avatar" aria-hidden="true">{{ mb_substr($student->voornaam,0,1) }}</span>
      <div class="iuasr-dash-candidate__body">
        <h2 class="iuasr-dash-candidate__name">{{ $student->volledigeNaam() }}</h2>
        <div class="iuasr-dash-candidate__meta"><span>Studentnr. <b>{{ $student->studentnummer }}</b></span><span class="dot"></span><span>Behaald: <b>{{ $transcript['behaaldeEc'] }}</b>@if($transcript['ecTotaal']) / {{ $transcript['ecTotaal'] }}@endif EC</span></div>
      </div>
    </div>
  </div>

  @forelse ($transcript['studiejaren'] as $sj)
    <div class="sis-card" style="margin-bottom:16px;">
      <div class="sis-card__hd"><h3>{{ $sj['inschrijving']->periode?->naam ?? 'Studiejaar' }} · leerjaar {{ $sj['inschrijving']->leerjaar }}</h3><span class="hint">{{ $sj['inschrijving']->opleiding?->naam }} · {{ $sj['behaaldeEc'] }}/{{ $sj['mogelijkeEc'] }} EC</span></div>
      <div class="iuasr-dash-tbl-card" style="border:0;">
        <table class="iuasr-dash-tbl">
          <thead><tr><th>Code</th><th>Vak</th><th style="text-align:right;">Eindcijfer</th><th style="text-align:center;">EC</th><th>Status</th></tr></thead>
          <tbody>
            @forelse ($sj['regels'] as $r)
              @php $eind = $r['eind']; $ec = $r['ec']; @endphp
              <tr>
                <td class="tnum">{{ $r['vak']->code }}</td>
                <td class="nm">{{ $r['vak']->naam }}</td>
                <td class="tnum" style="text-align:right;">
                  @if ($eind['status']==='cijfer')<span class="{{ $eind['cijfer'] < $grens ? '' : '' }}" style="font-weight:600;color:{{ $eind['cijfer'] < $grens ? 'var(--secColor100)' : 'var(--heritage-groen,#285C4D)' }};">{{ $eindTekst($eind) }}</span>
                  @else <span class="sis-muted">{{ $eindTekst($eind) }}</span>@endif
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
              <tr><td colspan="5" style="color:var(--blackAltText);padding:14px;">Geen vakken voor dit leerjaar.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  @empty
    <div class="iuasr-dash-alert iuasr-dash-alert--info"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>Deze student heeft nog geen inschrijvingen.</span></div>
  @endforelse
@endif
@endsection
