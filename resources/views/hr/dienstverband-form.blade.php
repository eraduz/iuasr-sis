@extends('layouts.app')

@php $titel = $dienstverband->exists ? 'Dienstverband bewerken' : 'Nieuw dienstverband'; @endphp

@section('titel', $titel)

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('medewerkers') }}">Medewerkers</a><span class="sep">›</span><a href="{{ route('medewerkers.show', $medewerker) }}">{{ $medewerker->volledigeNaam() }}</a><span class="sep">›</span><b>{{ $titel }}</b></div>

<div class="iuasr-dash-vhead"><div><h1>{{ $titel }}</h1><div class="summary">Voor <b>{{ $medewerker->volledigeNaam() }}</b> ({{ $medewerker->personeelsnummer }})</div></div></div>

<form method="POST" action="{{ $dienstverband->exists ? route('dienstverbanden.update', $dienstverband) : route('dienstverbanden.store', $medewerker) }}" class="sis-card sis-form" style="max-width:760px;">
  @csrf
  @if ($dienstverband->exists) @method('PUT') @endif

  @if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $errors->first() }}</span></div>
  @endif

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Contracttype <span class="req">*</span></label>@php $ct = old('contracttype', $dienstverband->contracttype?->value ?? 'tijdelijk'); @endphp<select name="contracttype">@foreach ($contracttypes as $w => $l)<option value="{{ $w }}" @selected($ct===$w)>{{ $l }}</option>@endforeach</select></div>
    <div class="sis-fld"><label>Uren per week <span class="req">*</span></label><input type="number" step="0.1" min="0" max="80" name="uren_per_week" value="{{ old('uren_per_week', $dienstverband->uren_per_week) }}" required><small class="sis-muted">FTE = uren ÷ {{ (int) config('sis.hr.voltijd_uren', 40) }} (automatisch).</small></div>
  </div>
  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Startdatum <span class="req">*</span></label><input type="date" name="startdatum" value="{{ old('startdatum', $dienstverband->startdatum?->toDateString()) }}" required></div>
    <div class="sis-fld"><label>Einddatum</label><input type="date" name="einddatum" value="{{ old('einddatum', $dienstverband->einddatum?->toDateString()) }}"><small class="sis-muted">Leeg = onbepaalde tijd.</small></div>
  </div>
  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Functie</label>@php $fid = old('functie_id', $dienstverband->functie_id); @endphp<select name="functie_id"><option value="">— geen —</option>@foreach ($functies as $f)<option value="{{ $f->id }}" @selected((int)$fid===$f->id)>{{ $f->naam }}</option>@endforeach</select></div>
    <div class="sis-fld"><label>Afdeling</label>@php $aid = old('afdeling_id', $dienstverband->afdeling_id); @endphp<select name="afdeling_id"><option value="">— geen —</option>@foreach ($afdelingen as $a)<option value="{{ $a->id }}" @selected((int)$aid===$a->id)>{{ $a->naam }}</option>@endforeach</select></div>
  </div>
  <div class="sis-fld"><label>Opmerking</label><textarea name="opmerking" rows="2">{{ old('opmerking', $dienstverband->opmerking) }}</textarea></div>

  <div class="sis-form__actions">
    <a class="iuasr-dash-btn" href="{{ route('medewerkers.show', $medewerker) }}">Annuleren</a>
    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div>
  </div>
</form>
@endsection
