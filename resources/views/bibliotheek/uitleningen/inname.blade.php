@extends('layouts.app')

@section('titel', 'Innemen')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('bibliotheek.dashboard') }}">Bibliotheek</a><span class="sep">›</span><a href="{{ route('bibliotheek.uitleningen') }}">Uitleningen</a><span class="sep">›</span><b>Innemen</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Innemen</h1>
    <div class="summary" dir="auto">{{ $uitlening->exemplaar->publicatie->volledigeTitel() }} ({{ $uitlening->exemplaar->serienummer }}) — {{ $uitlening->lenerNaam() }}</div>
  </div>
</div>

@if ($uitlening->isTeLaat())
  <div class="iuasr-dash-alert iuasr-dash-alert--warn" style="margin-bottom:12px;">
    <span>Deze publicatie is {{ $uitlening->dagenTeLaat() }} dagen te laat (verwacht op {{ $uitlening->verwachte_retour_op->format('d-m-Y') }}).</span>
  </div>
@endif

<form method="POST" action="{{ route('bibliotheek.innemen.store', $uitlening) }}" class="sis-card sis-form" style="max-width:680px;">
  @csrf @method('PUT')

  @if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><span>{{ $errors->first() }}</span></div>
  @endif

  <div class="sis-fld"><label>Retourdatum <span class="req">*</span></label><input type="date" name="retour_op" value="{{ old('retour_op', now()->format('Y-m-d')) }}" required></div>

  <div class="sis-fld">
    <label>Staat van het materiaal <span class="req">*</span></label>
    <select name="staat" required>
      @foreach (\App\Enums\Materiaalstaat::opties() as $waarde => $label)
        <option value="{{ $waarde }}" @selected(old('staat', 'goed') === $waarde)>{{ $label }}</option>
      @endforeach
    </select>
    <small class="sis-muted">Bij "Beschadigd" of "Ernstig beschadigd" volgt een schademelding en gaat het exemplaar uit de uitleen.</small>
  </div>

  <div class="sis-fld"><label>Opmerkingen</label><textarea name="retour_opmerking" maxlength="2000" placeholder="Optioneel">{{ old('retour_opmerking') }}</textarea></div>

  <div class="sis-form__actions">
    <a class="iuasr-dash-btn" href="{{ route('bibliotheek.uitleningen') }}">Annuleren</a>
    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Innemen</button></div>
  </div>
</form>
@endsection
