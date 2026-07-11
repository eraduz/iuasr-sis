@extends('layouts.app')

@section('titel', $gesprek->type?->label())

@section('inhoud')
@php $medewerker = $gesprek->medewerker; @endphp

<div class="sis-crumb"><a href="{{ route('medewerkers') }}">Medewerkers</a><span class="sep">›</span><a href="{{ route('medewerkers.show', $medewerker) }}">{{ $medewerker->volledigeNaam() }}</a><span class="sep">›</span><b>{{ $gesprek->type?->label() }}</b></div>

<div class="iuasr-dash-vhead">
  <div><h1>{{ $gesprek->type?->label() }}</h1><div class="summary">{{ $medewerker->volledigeNaam() }} · {{ $gesprek->datum?->format('d-m-Y') }} · <span class="iuasr-dash-status {{ $gesprek->status?->badge() }}">{{ $gesprek->status?->label() }}</span></div></div>
  <div class="iuasr-dash-vhead__actions">
    <form method="POST" action="{{ route('gesprekken.destroy', $gesprek) }}" onsubmit="return confirm('Gesprek verwijderen?');">@csrf @method('DELETE')<button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">Verwijderen</button></form>
  </div>
</div>

<form method="POST" action="{{ route('gesprekken.update', $gesprek) }}" class="sis-card sis-form" style="max-width:820px; margin-bottom:16px;">
  @csrf @method('PUT')
  @if ($errors->any())<div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><span>{{ $errors->first() }}</span></div>@endif
  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Type</label>@php $ty = old('type', $gesprek->type?->value); @endphp<select name="type">@foreach ($types as $w => $l)<option value="{{ $w }}" @selected($ty===$w)>{{ $l }}</option>@endforeach</select></div>
    <div class="sis-fld"><label>Status</label>@php $st = old('status', $gesprek->status?->value); @endphp<select name="status">@foreach ($statussen as $w => $l)<option value="{{ $w }}" @selected($st===$w)>{{ $l }}</option>@endforeach</select></div>
  </div>
  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Datum</label><input type="date" name="datum" value="{{ old('datum', $gesprek->datum?->toDateString()) }}" required></div>
    <div class="sis-fld"><label>Gespreksvoerder</label>@php $gv = old('gespreksvoerder_id', $gesprek->gespreksvoerder_id); @endphp<select name="gespreksvoerder_id"><option value="">— geen —</option>@foreach ($gespreksvoerders as $u)<option value="{{ $u->id }}" @selected((int)$gv===$u->id)>{{ $u->naam }}</option>@endforeach</select></div>
  </div>
  <div class="sis-fld"><label>Samenvatting</label><textarea name="samenvatting" rows="3">{{ old('samenvatting', $gesprek->samenvatting) }}</textarea></div>
  <div class="sis-fld"><label>Feedback</label><textarea name="feedback" rows="3">{{ old('feedback', $gesprek->feedback) }}</textarea></div>
  <div class="sis-form__actions"><a class="iuasr-dash-btn" href="{{ route('medewerkers.show', $medewerker) }}">Terug</a><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div></div>
</form>

{{-- Doelen / KPI's. --}}
<div class="sis-card" style="margin-bottom:16px;">
  <div class="sis-card__hd"><b>Doelen / KPI's ({{ $gesprek->doelen->count() }})</b></div>
  <div style="padding:14px 16px;">
    <form method="POST" action="{{ route('gesprekken.doel.store', $gesprek) }}" style="margin-bottom:12px;">
      @csrf
      <div style="display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
        <div class="sis-fld" style="flex:1; min-width:240px;"><label>Doel</label><input type="text" name="omschrijving" maxlength="255" required></div>
        <div class="sis-fld" style="min-width:150px;"><label>Status</label><select name="status">@foreach ($doelStatussen as $w => $l)<option value="{{ $w }}">{{ $l }}</option>@endforeach</select></div>
        <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Toevoegen</button>
      </div>
    </form>
    @forelse ($gesprek->doelen as $doel)
      <div style="display:flex; justify-content:space-between; padding:8px 0; border-top:1px solid var(--border,#e5e5e5);">
        <div>{{ $doel->omschrijving }} <span class="iuasr-dash-status {{ $doel->status==='behaald' ? 's-approved' : ($doel->status==='niet_behaald' ? 's-rejected' : 's-requested') }}">{{ $doel->statusLabel() }}</span></div>
        <form method="POST" action="{{ route('gesprekken.doel.destroy', $doel) }}" style="display:inline;">@csrf @method('DELETE')<button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">×</button></form>
      </div>
    @empty
      <p class="sis-muted" style="margin:0;">Nog geen doelen.</p>
    @endforelse
  </div>
</div>

{{-- Competenties. --}}
<div class="sis-card">
  <div class="sis-card__hd"><b>Competenties ({{ $gesprek->competentiescores->count() }})</b></div>
  <div style="padding:14px 16px;">
    <form method="POST" action="{{ route('gesprekken.competentie.store', $gesprek) }}" style="margin-bottom:12px;">
      @csrf
      <div style="display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
        <div class="sis-fld" style="min-width:200px;"><label>Competentie</label><input type="text" name="competentie" maxlength="255" required></div>
        <div class="sis-fld" style="min-width:150px;"><label>Score</label><select name="score">@foreach ($scores as $w => $l)<option value="{{ $w }}">{{ $l }}</option>@endforeach</select></div>
        <div class="sis-fld" style="flex:1; min-width:180px;"><label>Toelichting</label><input type="text" name="toelichting" maxlength="255"></div>
        <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Toevoegen</button>
      </div>
    </form>
    @forelse ($gesprek->competentiescores as $score)
      <div style="display:flex; justify-content:space-between; padding:8px 0; border-top:1px solid var(--border,#e5e5e5);">
        <div><b>{{ $score->competentie }}</b> — {{ $score->scoreLabel() }}@if($score->toelichting)<br><small class="sis-muted">{{ $score->toelichting }}</small>@endif</div>
        <form method="POST" action="{{ route('gesprekken.competentie.destroy', $score) }}" style="display:inline;">@csrf @method('DELETE')<button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">×</button></form>
      </div>
    @empty
      <p class="sis-muted" style="margin:0;">Nog geen competenties beoordeeld.</p>
    @endforelse
  </div>
</div>
@endsection
