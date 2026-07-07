@extends('layouts.app')

@section('titel', 'Wijzig gegevens')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ route('studenten.index') }}">Studenten</a><span class="sep">›</span><a href="{{ route('studenten.show', $student) }}">{{ $student->studentnummer }}</a><span class="sep">›</span><b>Wijzigen</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Wijzig gegevens</h1>
    <div class="summary">{{ $student->volledigeNaam() }} · <b>{{ $student->studentnummer }}</b></div>
  </div>
</div>

@if ($errors->any())
  <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:16px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <span>Controleer de invoer: {{ $errors->first() }}</span>
  </div>
@endif

<form method="POST" action="{{ route('studenten.update', $student) }}">
  @csrf
  @method('PUT')
  <div class="sis-grid-2">
    <div class="sis-card sis-form">
      <fieldset class="sis-fieldset">
        <legend>Persoonsgegevens</legend>
        <div class="sis-fld-row sis-fld-row--2">
          <div class="sis-fld"><label>Voornaam <span class="req">*</span></label><input type="text" name="voornaam" value="{{ old('voornaam', $student->voornaam) }}" required></div>
          <div class="sis-fld"><label>Achternaam <span class="req">*</span></label><input type="text" name="achternaam" value="{{ old('achternaam', $student->achternaam) }}" required></div>
        </div>
        <div class="sis-fld-row sis-fld-row--3">
          <div class="sis-fld"><label>Tussenvoegsel</label><input type="text" name="tussenvoegsel" value="{{ old('tussenvoegsel', $student->tussenvoegsel) }}"></div>
          <div class="sis-fld"><label>Roepnaam</label><input type="text" name="roepnaam" value="{{ old('roepnaam', $student->roepnaam) }}"></div>
          <div class="sis-fld"><label>Geboortedatum</label><input type="date" name="geboortedatum" value="{{ old('geboortedatum', $student->geboortedatum?->format('Y-m-d')) }}"></div>
        </div>
        <div class="sis-fld"><label>Geboorteplaats</label><input type="text" name="geboorteplaats" value="{{ old('geboorteplaats', $student->geboorteplaats) }}"></div>
      </fieldset>
      <fieldset class="sis-fieldset" style="margin-top:8px;">
        <legend>Taalbeheersing</legend>
        <div class="sis-fld-row sis-fld-row--2">
          <div class="sis-fld"><label>Nederlandse taal</label>
            <select name="taal_nederlands">
              <option value="">— niet bekend —</option>
              @foreach (App\Enums\TaalNiveau::cases() as $n)
                <option value="{{ $n->value }}" @selected(old('taal_nederlands', $student->taal_nederlands?->value) === $n->value)>{{ $n->label() }}</option>
              @endforeach
            </select>
          </div>
          <div class="sis-fld"><label>Arabische taal <span class="sis-muted" style="font-weight:400;">(info)</span></label>
            <select name="taal_arabisch">
              <option value="">— niet bekend —</option>
              @foreach (App\Enums\TaalNiveau::cases() as $n)
                <option value="{{ $n->value }}" @selected(old('taal_arabisch', $student->taal_arabisch?->value) === $n->value)>{{ $n->label() }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="sis-fld"><label class="sis-check-inline"><input type="checkbox" name="nt2_examen_vereist" value="1" @checked(old('nt2_examen_vereist', $student->nt2_examen_vereist))> Student moet nog een NT2-examen afleggen</label></div>
      </fieldset>
    </div>
    <div class="sis-card sis-form">
      <fieldset class="sis-fieldset">
        <legend>Contact</legend>
        <div class="sis-fld"><label>E-mail (IUASR)</label><input type="email" name="email" value="{{ old('email', $student->email) }}"></div>
        <div class="sis-fld"><label>E-mail privé</label><input type="email" name="email_prive" value="{{ old('email_prive', $student->email_prive) }}"></div>
        <div class="sis-fld"><label>Telefoon</label><input type="text" name="telefoon" value="{{ old('telefoon', $student->telefoon) }}"></div>
        <div class="sis-fld-row sis-fld-row--21">
          <div class="sis-fld"><label>Adres</label><input type="text" name="adres" value="{{ old('adres', $student->adres) }}"></div>
          <div class="sis-fld"><label>Postcode</label><input type="text" name="postcode" value="{{ old('postcode', $student->postcode) }}"></div>
        </div>
        <div class="sis-fld"><label>Woonplaats</label><input type="text" name="woonplaats" value="{{ old('woonplaats', $student->woonplaats) }}"></div>
      </fieldset>
    </div>
  </div>
  <div class="sis-card" style="margin-top:16px;">
    <div class="sis-form__actions" style="margin:0;padding:0;border:0;">
      <a class="iuasr-dash-btn" href="{{ route('studenten.show', $student) }}">Annuleren</a>
      <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Wijzigingen opslaan</button></div>
    </div>
    <p class="sis-tblnote" style="margin-top:10px;">Wijzigingen worden gelogd in de audit-log. Het BSN wordt hier niet gemuteerd.</p>
  </div>
</form>
@endsection
