@extends('layouts.app')

@section('titel', 'Verlof aanvragen')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('verlof.mijn') }}">Mijn verlof</a><span class="sep">›</span><b>Aanvragen</b></div>

<div class="iuasr-dash-vhead"><div><h1>Verlof aanvragen</h1></div></div>

<form method="POST" action="{{ route('verlof.store') }}" class="sis-card sis-form" style="max-width:640px;">
  @csrf

  @if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $errors->first() }}</span></div>
  @endif

  <div class="sis-fld">
    <label>Verloftype <span class="req">*</span></label>
    @php $ty = old('verloftype'); @endphp
    <select name="verloftype" required><option value="">— kies —</option>@foreach ($types as $w => $l)<option value="{{ $w }}" @selected($ty===$w)>{{ $l }}</option>@endforeach</select>
  </div>
  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Van <span class="req">*</span></label><input type="date" name="van" value="{{ old('van') }}" required></div>
    <div class="sis-fld"><label>Tot <span class="req">*</span></label><input type="date" name="tot" value="{{ old('tot') }}" required></div>
  </div>
  <div class="sis-fld"><label>Aantal uren <span class="req">*</span></label><input type="number" step="0.5" min="0.5" max="2000" name="uren" value="{{ old('uren') }}" required><small class="sis-muted">Het aantal verlofuren dat u wilt opnemen.</small></div>
  <div class="sis-fld"><label>Reden / toelichting</label><textarea name="reden" rows="2">{{ old('reden') }}</textarea></div>

  <div class="sis-form__actions">
    <a class="iuasr-dash-btn" href="{{ route('verlof.mijn') }}">Annuleren</a>
    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Indienen</button></div>
  </div>
</form>
@endsection
