@extends('layouts.app')

@section('titel', $titel)

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>{{ $titel }}</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>{{ $titel }}</h1>
    <div class="summary">Kies eerst een student</div>
  </div>
</div>

<form method="GET" action="{{ route($sleutel) }}" class="iuasr-dash-filters">
  <div class="search" style="grid-column:1 / -1;">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op studentnummer of naam…">
  </div>
</form>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th style="width:110px;">Studentnr.</th><th>Naam</th><th>Opleiding</th><th>Status</th><th class="row-act"></th></tr></thead>
    <tbody>
      @forelse ($studenten as $student)
        @php $insch = $student->inschrijvingen->first(); @endphp
        <tr>
          <td class="tnum">{{ $student->studentnummer }}</td>
          <td class="nm">{{ $student->volledigeNaam() }}</td>
          <td class="pg">{{ $insch?->opleiding?->naam ?? '—' }}</td>
          <td>@if($insch?->status)<span class="iuasr-dash-status {{ $insch->status->badge() }}">{{ $insch->status->label() }}</span>@else — @endif</td>
          <td class="row-act"><a class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" href="{{ route($doelRoute, $student) }}">{{ $titel }}</a></td>
        </tr>
      @empty
        <tr><td colspan="5"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen studenten gevonden</h3></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
