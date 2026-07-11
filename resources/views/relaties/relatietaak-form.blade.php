@extends('layouts.app')

@section('titel', 'Taak bewerken')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('relaties') }}">Organisaties</a><span class="sep">›</span><a href="{{ route('relaties.show', $organisatie) }}">{{ $organisatie->naam }}</a><span class="sep">›</span><b>Taak bewerken</b></div>

<div class="iuasr-dash-vhead"><div><h1>Taak bewerken</h1><div class="summary">Bij <b>{{ $organisatie->naam }}</b> ({{ $organisatie->relatienummer }})</div></div></div>

<form method="POST" action="{{ route('relatietaken.update', $taak) }}" class="sis-card sis-form" style="max-width:760px;">
  @csrf @method('PUT')

  @if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $errors->first() }}</span></div>
  @endif

  <div class="sis-fld"><label>Titel <span class="req">*</span></label><input type="text" name="titel" value="{{ old('titel', $taak->titel) }}" maxlength="255" required></div>
  <div class="sis-fld"><label>Omschrijving</label><textarea name="omschrijving" rows="3">{{ old('omschrijving', $taak->omschrijving) }}</textarea></div>

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld">
      <label>Toegewezen aan</label>
      @php $tid = old('toegewezen_aan_id', $taak->toegewezen_aan_id); @endphp
      <select name="toegewezen_aan_id"><option value="">— vrij op te pakken —</option>@foreach ($medewerkers as $m)<option value="{{ $m->id }}" @selected((int)$tid===$m->id)>{{ $m->naam }}</option>@endforeach</select>
    </div>
    <div class="sis-fld">
      <label>Gekoppelde stage</label>
      @php $sid = old('stage_id', $taak->stage_id); @endphp
      <select name="stage_id"><option value="">— geen —</option>@foreach ($stages as $s)<option value="{{ $s->id }}" @selected((int)$sid===$s->id)>{{ $s->stagenummer }} — {{ $s->student?->achternaam }}</option>@endforeach</select>
    </div>
  </div>

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Prioriteit</label>@php $pr = old('prioriteit', $taak->prioriteit->value); @endphp<select name="prioriteit">@foreach ($prioriteiten as $w => $l)<option value="{{ $w }}" @selected($pr===$w)>{{ $l }}</option>@endforeach</select></div>
    <div class="sis-fld"><label>Status</label>@php $st = old('status', $taak->status->value); @endphp<select name="status">@foreach ($statussen as $w => $l)<option value="{{ $w }}" @selected($st===$w)>{{ $l }}</option>@endforeach</select></div>
  </div>

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Startdatum</label><input type="date" name="startdatum" value="{{ old('startdatum', $taak->startdatum?->toDateString()) }}"></div>
    <div class="sis-fld"><label>Vervaldatum</label><input type="date" name="vervaldatum" value="{{ old('vervaldatum', $taak->vervaldatum?->toDateString()) }}"></div>
  </div>

  <div class="sis-form__actions">
    <a class="iuasr-dash-btn" href="{{ route('relaties.show', $organisatie) }}">Annuleren</a>
    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div>
  </div>
</form>
@endsection
