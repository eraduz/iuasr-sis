@extends('layouts.app')

@section('titel', 'Gesprek plannen')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('medewerkers') }}">Medewerkers</a><span class="sep">›</span><a href="{{ route('medewerkers.show', $medewerker) }}">{{ $medewerker->volledigeNaam() }}</a><span class="sep">›</span><b>Gesprek plannen</b></div>

<div class="iuasr-dash-vhead"><div><h1>Gesprek plannen</h1><div class="summary">Voor <b>{{ $medewerker->volledigeNaam() }}</b> ({{ $medewerker->personeelsnummer }})</div></div></div>

<form method="POST" action="{{ route('gesprekken.store', $medewerker) }}" class="sis-card sis-form" style="max-width:640px;">
  @csrf
  @if ($errors->any())<div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><span>{{ $errors->first() }}</span></div>@endif

  <div class="sis-fld">
    <label>Type <span class="req">*</span></label>
    @php $ty = old('type', $gesprek->type?->value); @endphp
    <select name="type" required>@foreach ($types as $w => $l)<option value="{{ $w }}" @selected($ty===$w)>{{ $l }}</option>@endforeach</select>
  </div>
  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Datum <span class="req">*</span></label><input type="date" name="datum" value="{{ old('datum', $gesprek->datum?->toDateString()) }}" required></div>
    <div class="sis-fld"><label>Status</label>@php $st = old('status', $gesprek->status?->value ?? 'gepland'); @endphp<select name="status">@foreach ($statussen as $w => $l)<option value="{{ $w }}" @selected($st===$w)>{{ $l }}</option>@endforeach</select></div>
  </div>
  <div class="sis-fld">
    <label>Gespreksvoerder</label>
    @php $gv = old('gespreksvoerder_id', $gesprek->gespreksvoerder_id); @endphp
    <select name="gespreksvoerder_id"><option value="">— geen —</option>@foreach ($gespreksvoerders as $u)<option value="{{ $u->id }}" @selected((int)$gv===$u->id)>{{ $u->naam }}</option>@endforeach</select>
  </div>

  <div class="sis-form__actions">
    <a class="iuasr-dash-btn" href="{{ route('medewerkers.show', $medewerker) }}">Annuleren</a>
    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Plannen</button></div>
  </div>
</form>
@endsection
