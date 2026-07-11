@extends('layouts.app')

@php $titel = $contactmoment->exists ? 'Contactmoment bewerken' : 'Contactmoment vastleggen'; @endphp

@section('titel', $titel)

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('relaties') }}">Organisaties</a><span class="sep">›</span><a href="{{ route('relaties.show', $organisatie) }}">{{ $organisatie->naam }}</a><span class="sep">›</span><b>{{ $contactmoment->exists ? 'Bewerken' : 'Contactmoment' }}</b></div>

<div class="iuasr-dash-vhead"><div><h1>{{ $titel }}</h1><div class="summary">Bij <b>{{ $organisatie->naam }}</b> ({{ $organisatie->relatienummer }})</div></div></div>

<form method="POST" action="{{ $contactmoment->exists ? route('contactmomenten.update', $contactmoment) : route('contactmomenten.store', $organisatie) }}" class="sis-card sis-form" style="max-width:760px;">
  @csrf
  @if ($contactmoment->exists) @method('PUT') @endif

  @if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $errors->first() }}</span></div>
  @endif

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld">
      <label>Type</label>
      @php $tid = old('contactmoment_type_id', $contactmoment->contactmoment_type_id); @endphp
      <select name="contactmoment_type_id">
        <option value="">— kies een type —</option>
        @foreach ($types as $t)
          <option value="{{ $t->id }}" @selected((int) $tid === $t->id)>{{ $t->naam }}</option>
        @endforeach
      </select>
    </div>
    <div class="sis-fld">
      <label>Contactpersoon</label>
      @php $cpid = old('contactpersoon_id', $contactmoment->contactpersoon_id); @endphp
      <select name="contactpersoon_id">
        <option value="">— geen / algemeen —</option>
        @foreach ($contactpersonen as $cp)
          <option value="{{ $cp->id }}" @selected((int) $cpid === $cp->id)>{{ $cp->volledigeNaam() }}</option>
        @endforeach
      </select>
    </div>
  </div>

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Datum <span class="req">*</span></label><input type="date" name="datum" value="{{ old('datum', $contactmoment->datum?->toDateString()) }}" required></div>
    <div class="sis-fld"><label>Tijd</label><input type="time" name="tijd" value="{{ old('tijd', $contactmoment->tijd) }}"></div>
  </div>

  <div class="sis-fld"><label>Onderwerp <span class="req">*</span></label><input type="text" name="onderwerp" value="{{ old('onderwerp', $contactmoment->onderwerp) }}" maxlength="255" required></div>
  <div class="sis-fld"><label>Samenvatting</label><textarea name="samenvatting" rows="4" placeholder="Wat is er besproken?">{{ old('samenvatting', $contactmoment->samenvatting) }}</textarea></div>
  <div class="sis-fld"><label>Vervolgdatum</label><input type="date" name="vervolgdatum" value="{{ old('vervolgdatum', $contactmoment->vervolgdatum?->toDateString()) }}"><small class="sis-muted">Optioneel: afgesproken opvolging.</small></div>

  <div class="sis-form__actions">
    <a class="iuasr-dash-btn" href="{{ route('relaties.show', $organisatie) }}">Annuleren</a>
    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div>
  </div>
</form>
@endsection
