@extends('layouts.app')

@section('titel', 'Vak bewerken')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ route('vakstructuur', ['opleiding' => $vak->opleiding_id]) }}">Vakstructuur</a><span class="sep">›</span><b>{{ $vak->code }}</b></div>

<div class="iuasr-dash-vhead"><div><h1>Vak bewerken</h1><div class="summary">{{ $vak->naam }}</div></div></div>

@if ($errors->any())
  <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:16px;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $errors->first() }}</span></div>
@endif

<form method="POST" action="{{ route('vakstructuur.update', $vak) }}" class="sis-card sis-form" style="max-width:640px;">
  @csrf @method('PUT')
  <div class="sis-fld"><label>Opleiding <span class="req">*</span></label>
    <select name="opleiding_id" required>@foreach ($opleidingen as $o)<option value="{{ $o->id }}" @selected($vak->opleiding_id === $o->id)>{{ $o->naam }}</option>@endforeach</select>
  </div>
  <div class="sis-fld-row sis-fld-row--3">
    <div class="sis-fld"><label>Studiejaar</label><select name="leerjaar">@for($j=1;$j<=10;$j++)<option value="{{ $j }}" @selected($vak->leerjaar===$j)>Jaar {{ $j }}</option>@endfor</select></div>
    <div class="sis-fld"><label>Periode (blok)</label><select name="blok">@for($b=1;$b<=4;$b++)<option value="{{ $b }}" @selected($vak->blok===$b)>Blok {{ $b }}</option>@endfor</select></div>
    <div class="sis-fld"><label>EC</label><input type="number" name="ec" min="0" max="60" value="{{ old('ec', $vak->ec) }}"></div>
  </div>
  <div class="sis-fld-row sis-fld-row--21">
    <div class="sis-fld"><label>Vaknaam <span class="req">*</span></label><input type="text" name="naam" value="{{ old('naam', $vak->naam) }}" required></div>
    <div class="sis-fld"><label>Code <span class="req">*</span></label><input type="text" name="code" value="{{ old('code', $vak->code) }}" required></div>
  </div>
  <div class="sis-fld"><label>Docent</label>
    <select name="docent_id"><option value="">— nog niet toegewezen —</option>@foreach ($docenten as $d)<option value="{{ $d->id }}" @selected($vak->docent_id===$d->id)>{{ $d->volledigeNaam() }}</option>@endforeach</select>
  </div>
  <div class="sis-form__actions">
    <a class="iuasr-dash-btn" href="{{ route('vakstructuur', ['opleiding' => $vak->opleiding_id]) }}">Annuleren</a>
    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div>
  </div>
</form>
@endsection
