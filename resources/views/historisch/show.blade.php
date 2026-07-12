@extends('layouts.app')

@section('titel', 'Historisch dossier · '.$student->studentnummer)

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ route('historisch.index') }}">Historisch dossier</a><span class="sep">›</span><b>{{ $student->studentnummer }}</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>{{ $student->volledigeNaam() }}</h1>
    <div class="summary">Studentnummer <b>{{ $student->studentnummer }}</b> · gemigreerde cijferlijst ({{ $opleiding->naam }})</div>
  </div>
  <div class="iuasr-dash-vhead__actions">
    <span class="sis-role-note"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg> Alleen-lezen</span>
  </div>
</div>

<div class="iuasr-dash-stats" style="margin-bottom:16px;">
  <div class="iuasr-dash-stat"><span class="lbl">Studiejaren</span><span class="val">{{ $jaren->count() }}</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Vakken met cijfer</span><span class="val">{{ $resultaten->count() }}</span></div>
  <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">EC behaald (voldoende)</span><span class="val">{{ rtrim(rtrim(number_format($totaalEcBehaald, 1, ',', ''), '0'), ',') }}</span></div>
</div>

<div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-bottom:16px;">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
  <span>Deze cijfers zijn <b>gemigreerd uit het oude Access-systeem</b> (0–100 omgezet naar 0–10). Het oude systeem kende geen deeltoetsen, dus er is per vak één eindcijfer. Voldoende vanaf 5,5.</span>
</div>

@foreach ($jaren as $jaar => $data)
  <div class="sis-card" style="margin-bottom:16px;">
    <div class="sis-card__hd">
      <h3>Studiejaar {{ $jaar }}</h3>
      <span class="hint">
        {{ $data['rijen']->count() }} {{ $data['rijen']->count() === 1 ? 'vak' : 'vakken' }}
        · EC behaald {{ rtrim(rtrim(number_format($data['ec_behaald'], 1, ',', ''), '0'), ',') }}/{{ rtrim(rtrim(number_format($data['ec_totaal'], 1, ',', ''), '0'), ',') }}
        @if ($data['gemiddelde'] !== null) · gemiddelde {{ number_format($data['gemiddelde'], 1, ',', '') }} @endif
      </span>
    </div>
    <div class="iuasr-dash-tbl-card" style="border:0;">
      <table class="iuasr-dash-tbl">
        <thead>
          <tr>
            <th style="width:110px;">Vakcode</th>
            <th>Vak</th>
            <th style="width:80px;">Cijfer</th>
            <th style="width:110px;">Resultaat</th>
            <th style="width:70px;">EC</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($data['rijen'] as $r)
            @php $vak = $r->toetsonderdeel->vak; @endphp
            <tr>
              <td class="tnum">{{ $vak?->code ?? '—' }}</td>
              <td class="nm">{{ $vak?->naam ?? '—' }}</td>
              <td class="tnum">{{ $r->cijfer !== null ? number_format($r->cijfer, 1, ',', '') : '—' }}</td>
              <td>
                @if ($r->vrijstelling)
                  <span class="iuasr-dash-status s-draft">Vrijstelling</span>
                @elseif ($r->voldoende)
                  <span class="iuasr-dash-status s-ok">Voldoende</span>
                @else
                  <span class="iuasr-dash-status s-alert">Onvoldoende</span>
                @endif
              </td>
              <td class="tnum">{{ $r->voldoende ? rtrim(rtrim(number_format((float) ($vak?->ec ?? 0), 1, ',', ''), '0'), ',') : '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endforeach
@endsection
