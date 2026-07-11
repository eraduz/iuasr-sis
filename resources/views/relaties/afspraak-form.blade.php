@extends('layouts.app')

@php $titel = $afspraak->exists ? 'Afspraak bewerken' : 'Afspraak plannen'; @endphp

@section('titel', $titel)

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('relaties') }}">Organisaties</a><span class="sep">›</span><a href="{{ route('relaties.show', $organisatie) }}">{{ $organisatie->naam }}</a><span class="sep">›</span><b>{{ $titel }}</b></div>

<div class="iuasr-dash-vhead"><div><h1>{{ $titel }}</h1><div class="summary">Bij <b>{{ $organisatie->naam }}</b> ({{ $organisatie->relatienummer }})</div></div></div>

<form method="POST" action="{{ $afspraak->exists ? route('afspraken.update', $afspraak) : route('afspraken.store', $organisatie) }}" class="sis-card sis-form" style="max-width:760px;">
  @csrf
  @if ($afspraak->exists) @method('PUT') @endif

  @if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $errors->first() }}</span></div>
  @endif

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld">
      <label>Type <span class="req">*</span></label>
      @php $ty = old('type', $afspraak->type?->value); @endphp
      <select name="type" required>
        <option value="">— kies een type —</option>
        @foreach ($types as $w => $l)<option value="{{ $w }}" @selected($ty===$w)>{{ $l }}</option>@endforeach
      </select>
    </div>
    <div class="sis-fld">
      <label>Status</label>
      @php $st = old('status', $afspraak->status ?? 'gepland'); @endphp
      <select name="status">
        <option value="gepland" @selected($st==='gepland')>Gepland</option>
        <option value="afgerond" @selected($st==='afgerond')>Afgerond</option>
        <option value="geannuleerd" @selected($st==='geannuleerd')>Geannuleerd</option>
      </select>
    </div>
  </div>

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Datum <span class="req">*</span></label><input type="date" name="datum" value="{{ old('datum', $afspraak->datum?->toDateString()) }}" required></div>
    <div class="sis-fld">
      <label>Gekoppelde stage</label>
      @php $sid = old('stage_id', $afspraak->stage_id); @endphp
      <select name="stage_id"><option value="">— geen —</option>@foreach ($stages as $s)<option value="{{ $s->id }}" @selected((int)$sid===$s->id)>{{ $s->stagenummer }} — {{ $s->student?->achternaam }}</option>@endforeach</select>
    </div>
  </div>

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Tijd van</label><input type="time" name="tijd_van" value="{{ old('tijd_van', $afspraak->tijd_van ? \Illuminate\Support\Str::of($afspraak->tijd_van)->substr(0,5) : '') }}"></div>
    <div class="sis-fld"><label>Tijd tot</label><input type="time" name="tijd_tot" value="{{ old('tijd_tot', $afspraak->tijd_tot ? \Illuminate\Support\Str::of($afspraak->tijd_tot)->substr(0,5) : '') }}"></div>
  </div>

  <div class="sis-fld"><label>Locatie</label><input type="text" name="locatie" value="{{ old('locatie', $afspraak->locatie) }}" maxlength="255"></div>
  <div class="sis-fld"><label>Onderwerp / omschrijving</label><textarea name="omschrijving" rows="3">{{ old('omschrijving', $afspraak->omschrijving) }}</textarea></div>

  <div class="sis-form__actions">
    <a class="iuasr-dash-btn" href="{{ route('relaties.show', $organisatie) }}">Annuleren</a>
    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div>
  </div>
</form>
@endsection
