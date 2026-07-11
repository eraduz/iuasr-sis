@extends('layouts.app')

@section('titel', 'Verlof')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('hr.dashboard') }}">HR</a><span class="sep">›</span><b>Verlof</b></div>

<div class="iuasr-dash-vhead"><div><h1>Verlofaanvragen</h1><div class="summary">{{ $aanvragen->total() }} aanvragen</div></div></div>

<form method="GET" action="{{ route('verlof') }}" class="sis-toolbar" style="margin-bottom:12px;">
  <select name="status"><option value="">Alle statussen</option>@foreach ($statussen as $s)<option value="{{ $s->value }}" @selected($statusFilter === $s->value)>{{ $s->label() }}</option>@endforeach</select>
  <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Filteren</button>
</form>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Medewerker</th><th>Type</th><th>Van</th><th>Tot</th><th style="text-align:right;">Uren</th><th style="text-align:center;">Status</th><th class="row-act"></th></tr></thead>
    <tbody>
      @forelse ($aanvragen as $a)
        <tr>
          <td class="nm"><a href="{{ route('medewerkers.show', $a->medewerker) }}#verlof">{{ $a->medewerker?->volledigeNaam() }}</a></td>
          <td>{{ $a->verloftype?->label() }}</td>
          <td class="dt">{{ $a->van?->format('d-m-Y') }}</td>
          <td class="dt">{{ $a->tot?->format('d-m-Y') }}</td>
          <td class="tnum" style="text-align:right;">{{ number_format((float) $a->uren, 1, ',', '.') }}</td>
          <td style="text-align:center;"><span class="iuasr-dash-status {{ $a->status?->badge() }}">{{ $a->status?->label() }}</span></td>
          <td class="row-act" style="white-space:nowrap;">
            @if ($a->beoordeelbaarVoor(auth()->user()))
              <form method="POST" action="{{ route('verlof.beoordelen', $a) }}" style="display:inline;">@csrf<input type="hidden" name="besluit" value="goedgekeurd"><button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Goedkeuren</button></form>
              <form method="POST" action="{{ route('verlof.beoordelen', $a) }}" style="display:inline;">@csrf<input type="hidden" name="besluit" value="afgewezen"><button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">Afwijzen</button></form>
            @else
              <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('medewerkers.show', $a->medewerker) }}#verlof">Bekijken</a>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="7"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen aanvragen</h3></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<div style="margin-top:12px;">{{ $aanvragen->links() }}</div>
@endsection
