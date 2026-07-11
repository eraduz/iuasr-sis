@extends('layouts.app')

@php $titel = $contactpersoon->exists ? 'Contactpersoon bewerken' : 'Nieuwe contactpersoon'; @endphp

@section('titel', $titel)

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('relaties') }}">Organisaties</a><span class="sep">›</span><a href="{{ route('relaties.show', $organisatie) }}">{{ $organisatie->naam }}</a><span class="sep">›</span><b>{{ $contactpersoon->exists ? $contactpersoon->volledigeNaam() : 'Nieuwe contactpersoon' }}</b></div>

<div class="iuasr-dash-vhead"><div><h1>{{ $titel }}</h1><div class="summary">Bij <b>{{ $organisatie->naam }}</b> ({{ $organisatie->relatienummer }})</div></div></div>

<form method="POST" action="{{ $contactpersoon->exists ? route('contactpersonen.update', $contactpersoon) : route('contactpersonen.store', $organisatie) }}" class="sis-card sis-form" style="max-width:760px;">
  @csrf
  @if ($contactpersoon->exists) @method('PUT') @endif

  @if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $errors->first() }}</span></div>
  @endif

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Voornaam <span class="req">*</span></label><input type="text" name="voornaam" value="{{ old('voornaam', $contactpersoon->voornaam) }}" maxlength="255" required></div>
    <div class="sis-fld"><label>Achternaam <span class="req">*</span></label><input type="text" name="achternaam" value="{{ old('achternaam', $contactpersoon->achternaam) }}" maxlength="255" required></div>
  </div>

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Functie</label><input type="text" name="functie" value="{{ old('functie', $contactpersoon->functie) }}" maxlength="255"></div>
    <div class="sis-fld"><label>Afdeling</label><input type="text" name="afdeling" value="{{ old('afdeling', $contactpersoon->afdeling) }}" maxlength="255"></div>
  </div>

  <div class="sis-fld"><label>E-mail</label><input type="email" name="email" value="{{ old('email', $contactpersoon->email) }}" maxlength="255"></div>

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Mobiel</label><input type="text" name="mobiel" value="{{ old('mobiel', $contactpersoon->mobiel) }}" maxlength="30"></div>
    <div class="sis-fld"><label>Telefoon</label><input type="text" name="telefoon" value="{{ old('telefoon', $contactpersoon->telefoon) }}" maxlength="30"></div>
  </div>

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld">
      <label>Voorkeur communicatie</label>
      @php $vk = old('voorkeur_communicatie', $contactpersoon->voorkeur_communicatie); @endphp
      <select name="voorkeur_communicatie">
        <option value="">— geen voorkeur —</option>
        <option value="e-mail" @selected($vk === 'e-mail')>E-mail</option>
        <option value="telefoon" @selected($vk === 'telefoon')>Telefoon</option>
        <option value="teams" @selected($vk === 'teams')>Teams</option>
      </select>
    </div>
    <div class="sis-fld"><label>LinkedIn</label><input type="text" name="linkedin" value="{{ old('linkedin', $contactpersoon->linkedin) }}" maxlength="255" placeholder="Optioneel"></div>
  </div>

  <div class="sis-fld">
    <label class="sis-check-inline"><input type="checkbox" name="actief" value="1" @checked(old('actief', $contactpersoon->actief ?? true))> Actief</label>
  </div>

  <div class="sis-form__actions">
    <a class="iuasr-dash-btn" href="{{ route('relaties.show', $organisatie) }}">Annuleren</a>
    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div>
  </div>
</form>
@endsection
