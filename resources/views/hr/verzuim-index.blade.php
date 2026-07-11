@extends('layouts.app')

@section('titel', 'Verzuim')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('hr.dashboard') }}">HR</a><span class="sep">›</span><b>Verzuim</b></div>

<div class="iuasr-dash-vhead"><div><h1>Verzuim</h1><div class="summary">{{ $meldingen->total() }} meldingen</div></div></div>

<div class="sis-card" style="margin-bottom:16px;">
  <div class="sis-card__hd"><b>Ziek melden</b></div>
  <div style="padding:14px 16px;">
    <form method="POST" action="{{ route('ziekmeldingen.store') }}">
      @csrf
      <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:end;">
        <div class="sis-fld" style="min-width:220px;"><label>Medewerker <span class="req">*</span></label><select name="medewerker_id" required><option value="">— kies —</option>@foreach ($medewerkers as $m)<option value="{{ $m->id }}">{{ $m->volledigeNaam() }}</option>@endforeach</select></div>
        <div class="sis-fld" style="min-width:160px;"><label>Ziek vanaf <span class="req">*</span></label><input type="date" name="ziek_van" value="{{ now()->toDateString() }}" required></div>
        <div class="sis-fld" style="min-width:110px;"><label>Percentage</label><input type="number" name="percentage" min="1" max="100" value="100"></div>
        <div class="sis-fld" style="flex:1; min-width:180px;"><label>Opmerking</label><input type="text" name="opmerking" maxlength="1000"></div>
        <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Registreren</button>
      </div>
    </form>
  </div>
</div>

<form method="GET" action="{{ route('verzuim') }}" class="sis-toolbar" style="margin-bottom:12px;">
  <select name="status"><option value="open" @selected($statusFilter==='open')>Alleen open</option><option value="alle" @selected($statusFilter==='alle')>Alle</option></select>
  <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Filteren</button>
</form>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Medewerker</th><th>Ziek van</th><th>Hersteld</th><th style="text-align:right;">%</th><th style="text-align:right;">Dagen</th><th>Gemeld door</th><th class="row-act"></th></tr></thead>
    <tbody>
      @forelse ($meldingen as $z)
        <tr>
          <td class="nm"><a href="{{ route('medewerkers.show', $z->medewerker) }}#verzuim">{{ $z->medewerker?->volledigeNaam() }}</a></td>
          <td class="dt">{{ $z->ziek_van?->format('d-m-Y') }}</td>
          <td class="dt">@if($z->hersteld_op){{ $z->hersteld_op->format('d-m-Y') }}@else<span class="iuasr-dash-status s-rejected">ziek</span>@endif</td>
          <td class="tnum" style="text-align:right;">{{ $z->percentage }}%</td>
          <td class="tnum" style="text-align:right;">{{ $z->dagen() }}</td>
          <td>{{ $z->gemeldDoor?->naam ?? '—' }}</td>
          <td class="row-act">
            @if ($z->isOpen())
              <form method="POST" action="{{ route('ziekmeldingen.herstel', $z) }}" style="display:inline; white-space:nowrap;">@csrf<input type="date" name="hersteld_op" value="{{ now()->toDateString() }}" style="width:140px;"><button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Hersteld</button></form>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="7"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen meldingen</h3></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<div style="margin-top:12px;">{{ $meldingen->links() }}</div>
@endsection
