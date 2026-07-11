@extends('layouts.app')

@section('titel', 'Gesprekken')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('hr.dashboard') }}">HR</a><span class="sep">›</span><b>Gesprekken</b></div>

<div class="iuasr-dash-vhead"><div><h1>Gesprekken</h1><div class="summary">{{ $gesprekken->total() }} gesprekken</div></div></div>

<form method="GET" action="{{ route('gesprekken') }}" class="sis-toolbar" style="margin-bottom:12px;">
  <select name="status"><option value="">Alle statussen</option>@foreach ($statussen as $s)<option value="{{ $s->value }}" @selected($statusFilter === $s->value)>{{ $s->label() }}</option>@endforeach</select>
  <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Filteren</button>
</form>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Datum</th><th>Type</th><th>Medewerker</th><th>Gespreksvoerder</th><th style="text-align:center;">Status</th><th class="row-act"></th></tr></thead>
    <tbody>
      @forelse ($gesprekken as $g)
        <tr>
          <td class="dt">{{ $g->datum?->format('d-m-Y') }}</td>
          <td>{{ $g->type?->label() }}</td>
          <td class="nm"><a href="{{ route('medewerkers.show', $g->medewerker) }}#gesprekken">{{ $g->medewerker?->volledigeNaam() }}</a></td>
          <td>{{ $g->gespreksvoerder?->naam ?? '—' }}</td>
          <td style="text-align:center;"><span class="iuasr-dash-status {{ $g->status?->badge() }}">{{ $g->status?->label() }}</span></td>
          <td class="row-act"><a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('gesprekken.show', $g) }}">Openen</a></td>
        </tr>
      @empty
        <tr><td colspan="6"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen gesprekken</h3><p class="sis-muted">Plan een gesprek vanaf de medewerkerkaart.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<div style="margin-top:12px;">{{ $gesprekken->links() }}</div>
@endsection
