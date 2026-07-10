@extends('layouts.app')

@section('titel', $cursus->exists ? 'Cursus bewerken' : 'Nieuwe cursus')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('cursussen.dashboard') }}">Cursussen</a><span class="sep">›</span><a href="{{ route('cursussen.beheer') }}">Cursusbeheer</a><span class="sep">›</span><b>{{ $cursus->exists ? $cursus->naam : 'Nieuwe cursus' }}</b></div>

<div class="iuasr-dash-vhead"><div><h1>{{ $cursus->exists ? 'Cursus bewerken' : 'Nieuwe cursus' }}</h1></div></div>

<form method="POST" action="{{ $cursus->exists ? route('cursussen.update', $cursus) : route('cursussen.store') }}" class="sis-card sis-form" style="max-width:640px;">
  @csrf
  @if ($cursus->exists) @method('PUT') @endif

  @if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $errors->first() }}</span></div>
  @endif

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Code <span class="req">*</span></label><input type="text" name="code" value="{{ old('code', $cursus->code) }}" maxlength="30" placeholder="Bijv. ARAB-TAAL" required></div>
    <div class="sis-fld"><label>Cursusgeld (€) <span class="req">*</span></label><input type="number" step="0.01" min="0" name="cursusgeld" value="{{ old('cursusgeld', $cursus->cursusgeld) }}" required></div>
  </div>
  <div class="sis-fld"><label>Naam <span class="req">*</span></label><input type="text" name="naam" value="{{ old('naam', $cursus->naam) }}" maxlength="255" required></div>
  <div class="sis-fld"><label>Omschrijving</label><textarea name="omschrijving" placeholder="Optioneel">{{ old('omschrijving', $cursus->omschrijving) }}</textarea></div>
  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Startdatum</label><input type="date" name="startdatum" value="{{ old('startdatum', $cursus->startdatum?->toDateString()) }}"></div>
    <div class="sis-fld"><label>Einddatum</label><input type="date" name="einddatum" value="{{ old('einddatum', $cursus->einddatum?->toDateString()) }}"></div>
  </div>
  <div class="sis-fld">
    <label class="sis-check-inline"><input type="checkbox" name="actief" value="1" @checked(old('actief', $cursus->actief ?? true))> Actief (kan worden ingeschreven)</label>
  </div>

  <div class="sis-form__actions">
    <a class="iuasr-dash-btn" href="{{ route('cursussen.beheer') }}">Annuleren</a>
    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div>
  </div>
</form>
@endsection
