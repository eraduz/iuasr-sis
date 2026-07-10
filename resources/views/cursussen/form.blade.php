@extends('layouts.app')

@php
    $bron = $bron ?? null;
    $titel = $bron ? 'Cursus kopiëren' : ($cursus->exists ? 'Cursus bewerken' : 'Nieuwe cursus');
@endphp

@section('titel', $titel)

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('cursussen.dashboard') }}">Cursussen</a><span class="sep">›</span><a href="{{ route('cursussen.beheer') }}">Cursusbeheer</a><span class="sep">›</span><b>{{ $bron ? 'Kopiëren' : ($cursus->exists ? $cursus->naam : 'Nieuwe cursus') }}</b></div>

<div class="iuasr-dash-vhead"><div><h1>{{ $titel }}</h1>@if ($bron)<div class="summary">Kopie van <b>{{ $bron->naam }}</b> ({{ $bron->code }}). Geef een nieuwe, unieke cursuscode en pas de naam aan; het cursusgeld, de omschrijving, de looptijd en de directeur zijn overgenomen. Cursisten worden niet meegekopieerd.</div>@endif</div></div>

<form method="POST" action="{{ $cursus->exists ? route('cursussen.update', $cursus) : route('cursussen.store') }}" class="sis-card sis-form" style="max-width:640px;">
  @csrf
  @if ($cursus->exists) @method('PUT') @endif

  @if ($bron)
    <div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><span>U maakt een kopie van <b>{{ $bron->naam }}</b>. Vul een nieuwe code en naam in.</span></div>
  @endif

  @if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $errors->first() }}</span></div>
  @endif

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Code <span class="req">*</span></label><input type="text" name="code" value="{{ old('code', $cursus->code) }}" maxlength="30" placeholder="Bijv. ARAB-TAAL" required></div>
    <div class="sis-fld"><label>Cursusgeld (€) <span class="req">*</span></label><input type="number" step="0.01" min="0" name="cursusgeld" value="{{ old('cursusgeld', $cursus->cursusgeld) }}" required></div>
  </div>
  <div class="sis-fld"><label>Naam <span class="req">*</span></label><input type="text" name="naam" value="{{ old('naam', $cursus->naam) }}" maxlength="255" required></div>
  <div class="sis-fld"><label>Omschrijving</label><textarea name="omschrijving" placeholder="Optioneel">{{ old('omschrijving', $cursus->omschrijving) }}</textarea></div>
  @if (auth()->user()->rolIs('beheerder'))
    <div class="sis-fld">
      <label>Cursusdirecteur</label>
      <select name="directeur_id">
        <option value="">— geen directeur —</option>
        @foreach ($directeuren as $d)
          <option value="{{ $d->id }}" @selected((int) old('directeur_id', $cursus->directeur_id) === $d->id)>{{ $d->naam }}</option>
        @endforeach
      </select>
      <small class="sis-muted">De directeur ziet en beheert uitsluitend deze cursus. Alleen de Beheerder kan dit toewijzen.</small>
    </div>
  @endif
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
