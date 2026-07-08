@extends('layouts.app')

@section('titel', 'Cijferoverzicht')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Cijferoverzicht</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Cijferoverzicht</h1>
    <div class="summary">Volledige inzage in resultaten · {{ $vakken->count() }} vakken · <b>{{ $terVaststelling }}</b> ter vaststelling</div>
  </div>
</div>

@if ($terVaststelling > 0)
  <div class="iuasr-dash-alert iuasr-dash-alert--warn" style="margin-bottom:16px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
    <span><b>{{ $terVaststelling }}</b> ingediende cijferlijst(en) wachten op vaststelling door de examencommissie.</span>
  </div>
@endif

<form method="GET" action="{{ route('cijferoverzicht') }}" class="iuasr-dash-filters" style="margin-bottom:16px;">
  <div class="search" style="grid-column:1 / -1;">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op vakcode, vaknaam of docent… (Enter)">
  </div>
</form>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Vak</th><th>Code</th><th>Opleiding</th><th>Docent</th><th>Studenten</th><th>Gem.</th><th>Geslaagd</th><th>Status</th><th class="row-act"></th></tr></thead>
    <tbody>
      @forelse ($vakken as $r)
        @php $vak = $r['vak']; @endphp
        <tr>
          <td class="nm">{{ $vak->naam }}</td>
          <td class="tnum">{{ $vak->code }}</td>
          <td class="pg">{{ $vak->opleiding?->code }}</td>
          <td>{{ $vak->docent?->achternaam ? ($vak->docent->aanhef ? $vak->docent->aanhef.' ' : '').$vak->docent->achternaam : '—' }}</td>
          <td class="tnum">{{ $r['aantal'] }}</td>
          <td class="tnum">{{ $r['gemiddeld'] !== null ? number_format($r['gemiddeld'],1,',','') : '—' }}</td>
          <td class="tnum">{{ $r['geslaagd'] }}/{{ $r['aantal'] }}</td>
          <td><span class="iuasr-dash-status {{ $r['status']->badge() }}">{{ $r['status']->label() }}</span></td>
          @php $terVast = $r['status'] === App\Enums\CijferlijstStatus::Ingediend; @endphp
          <td class="row-act" style="white-space:nowrap;text-align:right;">
            <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('vakken.tentamenlijst', $vak) }}">Tentamenlijst</a>
            <a class="iuasr-dash-btn iuasr-dash-btn--sm {{ $terVast ? 'iuasr-dash-btn--primary' : '' }}" href="{{ route('vakken.cijfers', $vak) }}">{{ $terVast ? 'Beoordelen' : 'Bekijken' }}</a>
          </td>
        </tr>
      @empty
        <tr><td colspan="9"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen vakken</h3></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<p class="sis-tblnote">Inzage wordt gelogd in de audit-log. Wijzigen van vastgestelde cijfers verloopt onder strikte, gelogde voorwaarden (examencommissie).</p>
@endsection
