@extends('layouts.app')

@section('titel', 'Medewerkers')

@section('inhoud')
@php $magBeheer = auth()->user()->magHrBeheer(); @endphp

<div class="sis-crumb"><a href="{{ route('hr.dashboard') }}">HR</a><span class="sep">›</span><b>Medewerkers</b></div>

<div class="iuasr-dash-vhead">
  <div><h1>Medewerkers</h1><div class="summary">{{ $medewerkers->total() }} {{ $medewerkers->total() === 1 ? 'medewerker' : 'medewerkers' }}</div></div>
  @if ($magBeheer)
    <div class="iuasr-dash-vhead__actions"><a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('medewerkers.create') }}">Medewerker toevoegen</a></div>
  @endif
</div>

<form method="GET" action="{{ route('medewerkers') }}" class="sis-toolbar" style="margin-bottom:12px; gap:8px; flex-wrap:wrap;">
  <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op naam of personeelsnummer">
  <select name="afdeling"><option value="">Alle afdelingen</option>@foreach ($afdelingen as $a)<option value="{{ $a->id }}" @selected($afdelingFilter === $a->id)>{{ $a->naam }}</option>@endforeach</select>
  <select name="functie"><option value="">Alle functies</option>@foreach ($functies as $f)<option value="{{ $f->id }}" @selected($functieFilter === $f->id)>{{ $f->naam }}</option>@endforeach</select>
  <select name="status"><option value="">Alle statussen</option>@foreach ($statussen as $s)<option value="{{ $s->value }}" @selected($statusFilter === $s->value)>{{ $s->label() }}</option>@endforeach</select>
  <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Filteren</button>
</form>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Pers.nr.</th><th>Naam</th><th>Functie</th><th>Afdeling</th><th style="text-align:right;">FTE</th><th style="text-align:center;">Status</th><th class="row-act"></th></tr></thead>
    <tbody>
      @forelse ($medewerkers as $m)
        <tr>
          <td class="tnum">{{ $m->personeelsnummer }}</td>
          <td class="nm"><a href="{{ route('medewerkers.show', $m) }}">{{ $m->volledigeNaam() }}</a></td>
          <td>{{ $m->functie?->naam ?? '—' }}</td>
          <td>{{ $m->afdeling?->naam ?? '—' }}</td>
          <td class="tnum" style="text-align:right;">{{ $m->fte() !== null ? number_format($m->fte(), 2, ',', '.') : '—' }}</td>
          <td style="text-align:center;"><span class="iuasr-dash-status {{ $m->status?->badge() }}">{{ $m->status?->label() }}</span></td>
          <td class="row-act"><a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('medewerkers.show', $m) }}">Bekijken</a></td>
        </tr>
      @empty
        <tr><td colspan="7"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen medewerkers</h3></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div style="margin-top:12px;">{{ $medewerkers->links() }}</div>
@endsection
