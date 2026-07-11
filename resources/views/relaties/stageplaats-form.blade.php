@extends('layouts.app')

@php $titel = $stageplaats->exists ? 'Stageplaats bewerken' : 'Nieuwe stageplaats'; @endphp

@section('titel', $titel)

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('relaties') }}">Organisaties</a><span class="sep">›</span><a href="{{ route('relaties.show', $organisatie) }}">{{ $organisatie->naam }}</a><span class="sep">›</span><b>{{ $stageplaats->exists ? 'Stageplaats bewerken' : 'Nieuwe stageplaats' }}</b></div>

<div class="iuasr-dash-vhead"><div><h1>{{ $titel }}</h1><div class="summary">Aanbod bij <b>{{ $organisatie->naam }}</b> ({{ $organisatie->relatienummer }})</div></div></div>

<form method="POST" action="{{ $stageplaats->exists ? route('stageplaatsen.update', $stageplaats) : route('stageplaatsen.store', $organisatie) }}" class="sis-card sis-form" style="max-width:760px;">
  @csrf
  @if ($stageplaats->exists) @method('PUT') @endif

  @if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $errors->first() }}</span></div>
  @endif

  @if ($opleidingen->isEmpty())
    <div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-bottom:12px;"><span>Aan deze organisatie is (voor u) geen opleiding gekoppeld. Koppel eerst een opleiding aan de organisatie.</span></div>
  @endif

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld">
      <label>Opleiding <span class="req">*</span></label>
      @php $oid = old('opleiding_id', $stageplaats->opleiding_id); @endphp
      <select name="opleiding_id" required>
        <option value="">— kies een opleiding —</option>
        @foreach ($opleidingen as $o)
          <option value="{{ $o->id }}" @selected((int) $oid === $o->id)>{{ $o->code }} — {{ $o->naam }}</option>
        @endforeach
      </select>
    </div>
    <div class="sis-fld">
      <label>Studiejaar (periode)</label>
      @php $pid = old('periode_id', $stageplaats->periode_id); @endphp
      <select name="periode_id">
        <option value="">— n.v.t. —</option>
        @foreach ($perioden as $p)
          <option value="{{ $p->id }}" @selected((int) $pid === $p->id)>{{ $p->code }}</option>
        @endforeach
      </select>
    </div>
  </div>

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Leerjaar</label><input type="number" name="leerjaar" min="1" max="10" value="{{ old('leerjaar', $stageplaats->leerjaar) }}"></div>
    <div class="sis-fld"><label>Werkdagen</label><input type="text" name="werkdagen" maxlength="255" value="{{ old('werkdagen', $stageplaats->werkdagen) }}" placeholder="bv. ma, di, do"></div>
  </div>

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Aantal plaatsen <span class="req">*</span></label><input type="number" name="aantal_plaatsen" min="0" max="999" value="{{ old('aantal_plaatsen', $stageplaats->aantal_plaatsen ?? 1) }}" required></div>
    <div class="sis-fld"><label>Max. studenten</label><input type="number" name="max_studenten" min="0" max="999" value="{{ old('max_studenten', $stageplaats->max_studenten) }}" placeholder="optioneel"><small class="sis-muted">Bepaalt de bezettingswaarschuwing.</small></div>
  </div>

  <div class="sis-fld"><label>Specialisaties</label><input type="text" name="specialisaties" maxlength="255" value="{{ old('specialisaties', $stageplaats->specialisaties) }}" placeholder="bv. onderbouw, VVE"></div>
  <div class="sis-fld"><label>Eisen</label><textarea name="eisen" rows="3" placeholder="Optioneel">{{ old('eisen', $stageplaats->eisen) }}</textarea></div>

  <div class="sis-fld"><label class="sis-check-inline"><input type="checkbox" name="actief" value="1" @checked(old('actief', $stageplaats->actief ?? true))> Actief (beschikbaar voor plaatsing)</label></div>

  <div class="sis-form__actions">
    <a class="iuasr-dash-btn" href="{{ route('relaties.show', $organisatie) }}">Annuleren</a>
    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div>
  </div>
</form>
@endsection
