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
        <div class="sis-fld-row sis-fld-row--2">
          <div class="sis-fld"><label class="sis-check-inline" style="margin-top:26px;"><input type="checkbox" name="nt2_examen_vereist" value="1" @checked(old('nt2_examen_vereist', $student->nt2_examen_vereist))> NT2-examen vereist</label></div>
          <div class="sis-fld"><label>NT2 behaald op</label>
            <input type="date" name="nt2_behaald_op" value="{{ old('nt2_behaald_op', $student->nt2_behaald_op?->format('Y-m-d')) }}">
            <div class="help">Leeg laten zolang het examen nog niet is gehaald. De deadline is 1 jaar na de inschrijfdatum.</div>
          </div>
        </div>
      </fieldset>
    </div>
    <div class="sis-card sis-form">
      <fieldset class="sis-fieldset">
        <legend>Contact</legend>
        <div class="sis-fld"><label>E-mail (IUASR)</label><input type="email" name="email" value="{{ old('email', $student->email) }}"></div>
        <div class="sis-fld"><label>E-mail privé</label><input type="email" name="email_prive" value="{{ old('email_prive', $student->email_prive) }}"></div>
        <div class="sis-fld"><label>Telefoon</label><input type="text" name="telefoon" value="{{ old('telefoon', $student->telefoon) }}"></div>
        <div class="sis-fld-row sis-fld-row--21">
          <div class="sis-fld"><label>Straat</label><input type="text" name="adres" value="{{ old('adres', $student->adres) }}"></div>
          <div class="sis-fld"><label>Huisnummer</label><input type="text" name="huisnummer" value="{{ old('huisnummer', $student->huisnummer) }}"></div>
        </div>
        <div class="sis-fld-row sis-fld-row--2">
          <div class="sis-fld"><label>Postcode</label><input type="text" name="postcode" value="{{ old('postcode', $student->postcode) }}"></div>
          <div class="sis-fld"><label>Stad</label><input type="text" name="woonplaats" value="{{ old('woonplaats', $student->woonplaats) }}"></div>
        </div>
        <div class="sis-fld-row sis-fld-row--2">
          <div class="sis-fld"><label>Provincie</label><input type="text" name="provincie" value="{{ old('provincie', $student->provincie) }}"></div>
          <div class="sis-fld"><label>Land</label>
            <select name="land_id">
              <option value="">— niet bekend —</option>
              @foreach (App\Models\Land::orderBy('naam')->get() as $land)
                <option value="{{ $land->id }}" @selected(old('land_id', $student->land_id) == $land->id)>{{ $land->naam }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </fieldset>
    </div>

    <div class="sis-card sis-form">
      <fieldset class="sis-fieldset">
        <legend>Vooropleiding</legend>
        <div class="sis-fld"><label>Hoogst behaalde diploma</label><input type="text" name="diploma" value="{{ old('diploma', $student->diploma) }}"></div>
        <div class="sis-fld"><label>Naam onderwijsinstelling vorige opleiding</label><input type="text" name="vorige_instelling" value="{{ old('vorige_instelling', $student->vorige_instelling) }}"></div>
        <div class="sis-fld-row sis-fld-row--2">
          <div class="sis-fld"><label>Afstudeerjaar</label><input type="text" name="afstudeerjaar" inputmode="numeric" placeholder="bv. 2024" value="{{ old('afstudeerjaar', $student->afstudeerjaar) }}"></div>
          <div class="sis-fld"><label>Opleidingsrichting (vrij)</label><input type="text" name="vooropleiding" value="{{ old('vooropleiding', $student->vooropleiding) }}"></div>
        </div>
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
