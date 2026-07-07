@extends('layouts.app')

@section('titel', 'Cijferoverzicht')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Cijferoverzicht</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Cijferoverzicht</h1>
    <div class="summary">Volledige inzage in resultaten · {{ $vakken->count() }} vakken</div>
  </div>
</div>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Vak</th><th>Code</th><th>Opleiding</th><th>Docent</th><th>Studenten</th><th>Gem.</th><th>Geslaagd</th><th class="row-act"></th></tr></thead>
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
          <td class="row-act"><a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('vakken.cijfers', $vak) }}">Bekijken</a></td>
        </tr>
      @empty
        <tr><td colspan="8"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen vakken</h3></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<p class="sis-tblnote">Inzage wordt gelogd in de audit-log. Wijzigen van vastgestelde cijfers verloopt onder strikte, gelogde voorwaarden (examencommissie).</p>
@endsection
