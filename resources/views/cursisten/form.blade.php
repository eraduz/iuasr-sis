@extends('layouts.app')

@section('titel', $cursist->exists ? 'Cursist bewerken' : 'Nieuwe cursist')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('cursussen.dashboard') }}">Cursussen</a><span class="sep">›</span><a href="{{ route('cursisten') }}">Cursisten</a><span class="sep">›</span><b>{{ $cursist->exists ? $cursist->volledigeNaam() : 'Nieuwe cursist' }}</b></div>

<div class="iuasr-dash-vhead"><div><h1>{{ $cursist->exists ? 'Cursist bewerken' : 'Nieuwe cursist' }}</h1></div></div>

<form method="POST" action="{{ $cursist->exists ? route('cursisten.update', $cursist) : route('cursisten.store') }}" class="sis-card sis-form" style="max-width:720px;">
  @csrf
  @if ($cursist->exists) @method('PUT') @endif

  @if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $errors->first() }}</span></div>
  @endif

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Voornaam <span class="req">*</span></label><input type="text" name="voornaam" value="{{ old('voornaam', $cursist->voornaam) }}" required></div>
    <div class="sis-fld"><label>Achternaam <span class="req">*</span></label>
      <div class="sis-fld-row" style="display:flex;gap:8px;">
        <input type="text" name="tussenvoegsel" value="{{ old('tussenvoegsel', $cursist->tussenvoegsel) }}" placeholder="tv." style="max-width:70px;">
        <input type="text" name="achternaam" value="{{ old('achternaam', $cursist->achternaam) }}" required style="flex:1;">
      </div>
    </div>
  </div>
  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Geboortedatum</label><input type="date" name="geboortedatum" value="{{ old('geboortedatum', $cursist->geboortedatum?->toDateString()) }}"></div>
    <div class="sis-fld"><label>Geslacht</label>
      <select name="geslacht">
        <option value="">—</option>
        @foreach (['man' => 'Man', 'vrouw' => 'Vrouw', 'anders' => 'Anders'] as $w => $l)
          <option value="{{ $w }}" @selected(old('geslacht', $cursist->geslacht) === $w)>{{ $l }}</option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>E-mail</label><input type="email" name="email" value="{{ old('email', $cursist->email) }}"></div>
    <div class="sis-fld"><label>Telefoon</label><input type="text" name="telefoon" value="{{ old('telefoon', $cursist->telefoon) }}"></div>
  </div>
  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Adres</label><input type="text" name="adres" value="{{ old('adres', $cursist->adres) }}"></div>
    <div class="sis-fld" style="display:flex;gap:8px;">
      <div style="max-width:110px;"><label>Postcode</label><input type="text" name="postcode" value="{{ old('postcode', $cursist->postcode) }}"></div>
      <div style="flex:1;"><label>Woonplaats</label><input type="text" name="woonplaats" value="{{ old('woonplaats', $cursist->woonplaats) }}"></div>
    </div>
  </div>

  @unless ($cursist->exists)
    <div class="sis-fld">
      <label>Direct inschrijven op cursus</label>
      <select name="cursus_id">
        <option value="">— niet inschrijven —</option>
        @foreach ($cursussen as $cursus)
          <option value="{{ $cursus->id }}" @selected(old('cursus_id') == $cursus->id)>{{ $cursus->naam }} (€ {{ number_format($cursus->cursusgeld, 2, ',', '.') }})</option>
        @endforeach
      </select>
    </div>
  @endunless

  <div class="sis-fld"><label>Opmerkingen</label><textarea name="opmerkingen">{{ old('opmerkingen', $cursist->opmerkingen) }}</textarea></div>

  <div class="sis-form__actions">
    <a class="iuasr-dash-btn" href="{{ $cursist->exists ? route('cursisten.show', $cursist) : route('cursisten') }}">Annuleren</a>
    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div>
  </div>
</form>
@endsection
