@extends('layouts.app')

@php $titel = $organisatie->exists ? 'Organisatie bewerken' : 'Nieuwe organisatie'; @endphp

@section('titel', $titel)

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('relaties') }}">Organisaties</a><span class="sep">›</span><b>{{ $organisatie->exists ? $organisatie->naam : 'Nieuwe organisatie' }}</b></div>

<div class="iuasr-dash-vhead"><div><h1>{{ $titel }}</h1>@if ($organisatie->exists)<div class="summary">Relatienummer <b>{{ $organisatie->relatienummer }}</b></div>@endif</div></div>

<form method="POST" action="{{ $organisatie->exists ? route('relaties.update', $organisatie) : route('relaties.store') }}" class="sis-card sis-form" style="max-width:760px;">
  @csrf
  @if ($organisatie->exists) @method('PUT') @endif

  @if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $errors->first() }}</span></div>
  @endif

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Naam <span class="req">*</span></label><input type="text" name="naam" value="{{ old('naam', $organisatie->naam) }}" maxlength="255" required></div>
    <div class="sis-fld">
      <label>Type organisatie</label>
      <select name="organisatie_type_id">
        <option value="">— kies een type —</option>
        @foreach ($types as $t)
          <option value="{{ $t->id }}" @selected((int) old('organisatie_type_id', $organisatie->organisatie_type_id) === $t->id)>{{ $t->naam }}@if($t->opleiding) ({{ $t->opleiding->code }})@endif</option>
        @endforeach
      </select>
      <small class="sis-muted">Types beheert u via Opzoektabellen (per opleiding instelbaar).</small>
    </div>
  </div>

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>KvK-nummer</label><input type="text" name="kvk_nummer" value="{{ old('kvk_nummer', $organisatie->kvk_nummer) }}" maxlength="20"></div>
    <div class="sis-fld"><label>BRIN-nummer</label><input type="text" name="brin_nummer" value="{{ old('brin_nummer', $organisatie->brin_nummer) }}" maxlength="20" placeholder="Onderwijs (optioneel)"></div>
  </div>

  <div class="sis-fld"><label>Adres</label><input type="text" name="adres" value="{{ old('adres', $organisatie->adres) }}" maxlength="255"></div>
  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Postcode</label><input type="text" name="postcode" value="{{ old('postcode', $organisatie->postcode) }}" maxlength="12"></div>
    <div class="sis-fld"><label>Plaats</label><input type="text" name="plaats" value="{{ old('plaats', $organisatie->plaats) }}" maxlength="255"></div>
  </div>
  <div class="sis-fld"><label>Provincie</label><input type="text" name="provincie" value="{{ old('provincie', $organisatie->provincie) }}" maxlength="255"></div>

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Telefoon</label><input type="text" name="telefoon" value="{{ old('telefoon', $organisatie->telefoon) }}" maxlength="30"></div>
    <div class="sis-fld"><label>Algemeen e-mailadres</label><input type="email" name="email" value="{{ old('email', $organisatie->email) }}" maxlength="255"></div>
  </div>
  <div class="sis-fld"><label>Website</label><input type="text" name="website" value="{{ old('website', $organisatie->website) }}" maxlength="255" placeholder="https://"></div>

  <div class="sis-fld">
    <label>Opleiding(en) <span class="req">*</span></label>
    <div style="display:flex; flex-wrap:wrap; gap:10px 18px; margin-top:4px;">
      @forelse ($opleidingen as $o)
        <label class="sis-check-inline"><input type="checkbox" name="opleidingen[]" value="{{ $o->id }}" @checked(in_array($o->id, old('opleidingen', $gekozenOpleidingen)))> {{ $o->code }} — {{ $o->naam }}</label>
      @empty
        <small class="sis-muted">Er zijn geen opleidingen aan uw account gekoppeld. Neem contact op met de Beheerder.</small>
      @endforelse
    </div>
    <small class="sis-muted">Bepaalt voor welke opleiding(en) deze relatie zichtbaar is.</small>
  </div>

  <div class="sis-fld"><label>Opmerkingen</label><textarea name="opmerkingen" placeholder="Optioneel">{{ old('opmerkingen', $organisatie->opmerkingen) }}</textarea></div>

  <div class="sis-fld">
    <label class="sis-check-inline"><input type="checkbox" name="actief" value="1" @checked(old('actief', $organisatie->actief ?? true))> Actief</label>
  </div>

  <div class="sis-form__actions">
    <a class="iuasr-dash-btn" href="{{ $organisatie->exists ? route('relaties.show', $organisatie) : route('relaties') }}">Annuleren</a>
    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div>
  </div>
</form>
@endsection
