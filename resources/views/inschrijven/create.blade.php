@extends('layouts.app')

@section('titel', 'Student inschrijven')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ route('studenten.index') }}">Studenten</a><span class="sep">›</span><b>Inschrijven</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Student inschrijven</h1>
    <div class="summary">Nieuwe inschrijving{{ $actievePeriode ? ' · '.$actievePeriode->naam : '' }}</div>
  </div>
</div>

<div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-bottom:16px;">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
  <span>Het <b>studentnummer wordt automatisch toegekend</b> bij opslaan (jaarprefix + volgnummer, bijv. 261234). BSN wordt in deze fase niet vastgelegd (pas na akkoord FG).</span>
</div>

@if ($errors->any())
  <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:16px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <span>Controleer de invoer: {{ $errors->first() }}</span>
  </div>
@endif

<form method="POST" action="{{ route('inschrijven.store') }}">
  @csrf
  <div class="sis-grid-2">
    <div>
      <div class="sis-card sis-form">
        <fieldset class="sis-fieldset">
          <legend>Persoonsgegevens</legend>
          <div class="sis-fld-row sis-fld-row--2">
            <div class="sis-fld"><label>Voornaam <span class="req">*</span></label><input type="text" name="voornaam" value="{{ old('voornaam') }}" required></div>
            <div class="sis-fld"><label>Achternaam <span class="req">*</span></label><input type="text" name="achternaam" value="{{ old('achternaam') }}" required></div>
          </div>
          <div class="sis-fld-row sis-fld-row--21">
            <div class="sis-fld"><label>Tussenvoegsel</label><input type="text" name="tussenvoegsel" value="{{ old('tussenvoegsel') }}"></div>
            <div class="sis-fld"><label>Geboortedatum</label><input type="date" name="geboortedatum" value="{{ old('geboortedatum') }}"></div>
          </div>
          <div class="sis-fld"><label>Geboorteplaats</label><input type="text" name="geboorteplaats" value="{{ old('geboorteplaats') }}"></div>
        </fieldset>

        <fieldset class="sis-fieldset" style="margin-top:8px;">
          <legend>Contact</legend>
          <div class="sis-fld"><label>E-mail privé</label><input type="email" name="email_prive" value="{{ old('email_prive') }}"></div>
          <div class="sis-fld-row sis-fld-row--21">
            <div class="sis-fld"><label>Adres</label><input type="text" name="adres" value="{{ old('adres') }}"></div>
            <div class="sis-fld"><label>Postcode</label><input type="text" name="postcode" value="{{ old('postcode') }}"></div>
          </div>
          <div class="sis-fld-row sis-fld-row--2">
            <div class="sis-fld"><label>Woonplaats</label><input type="text" name="woonplaats" value="{{ old('woonplaats') }}"></div>
            <div class="sis-fld"><label>Telefoon</label><input type="text" name="telefoon" value="{{ old('telefoon') }}"></div>
          </div>
        </fieldset>

        <fieldset class="sis-fieldset" style="margin-top:8px;">
          <legend>Taalbeheersing</legend>
          <div class="sis-fld-row sis-fld-row--2">
            <div class="sis-fld">
              <label>Nederlandse taal</label>
              <select name="taal_nederlands">
                <option value="">— niet bekend —</option>
                @foreach (App\Enums\TaalNiveau::cases() as $n)
                  <option value="{{ $n->value }}" @selected(old('taal_nederlands') === $n->value)>{{ $n->label() }}</option>
                @endforeach
              </select>
              <div class="help">Belangrijk voor toelating en begeleiding.</div>
            </div>
            <div class="sis-fld">
              <label>Arabische taal</label>
              <select name="taal_arabisch">
                <option value="">— niet bekend —</option>
                @foreach (App\Enums\TaalNiveau::cases() as $n)
                  <option value="{{ $n->value }}" @selected(old('taal_arabisch') === $n->value)>{{ $n->label() }}</option>
                @endforeach
              </select>
              <div class="help">Alleen ter informatie.</div>
            </div>
          </div>
          <div class="sis-fld">
            <label class="sis-check-inline"><input type="checkbox" name="nt2_examen_vereist" value="1" @checked(old('nt2_examen_vereist'))> Student moet nog een NT2-examen afleggen</label>
          </div>
        </fieldset>

        <fieldset class="sis-fieldset" style="margin-top:8px;">
          <legend>Inschrijving</legend>
          <div class="sis-fld">
            <label>Opleiding <span class="req">*</span></label>
            <select name="opleiding_id" required>
              <option value="">— kies opleiding —</option>
              @foreach ($opleidingen as $o)
                <option value="{{ $o->id }}" @selected(old('opleiding_id') == $o->id)>{{ $o->naam }}</option>
              @endforeach
            </select>
          </div>
          <div class="sis-fld-row sis-fld-row--3">
            <div class="sis-fld"><label>Klas</label>
              <select name="klas_id">
                <option value="">— geen —</option>
                @foreach ($klassen as $k)
                  <option value="{{ $k->id }}" @selected(old('klas_id') == $k->id)>{{ $k->code }} · {{ $k->opleiding?->code }}</option>
                @endforeach
              </select>
            </div>
            <div class="sis-fld"><label>Periode <span class="req">*</span></label>
              <select name="periode_id" required>
                @foreach ($perioden as $p)
                  <option value="{{ $p->id }}" @selected(old('periode_id', $actievePeriode?->id) == $p->id)>{{ $p->naam }}</option>
                @endforeach
              </select>
            </div>
            <div class="sis-fld"><label>Leerjaar</label><input type="number" name="leerjaar" min="1" max="10" value="{{ old('leerjaar', 1) }}"></div>
          </div>
          <div class="sis-fld-row sis-fld-row--2">
            <div class="sis-fld"><label>Inschrijfdatum <span class="req">*</span></label><input type="date" name="inschrijfdatum" value="{{ old('inschrijfdatum', $actievePeriode?->startdatum?->format('Y-m-d')) }}" required></div>
            <div class="sis-fld"><label>Betaalwijze</label>
              <select name="betaalwijze">
                <option value="">— n.v.t. —</option>
                <option value="termijnen" @selected(old('betaalwijze')==='termijnen')>Termijnen</option>
                <option value="contant" @selected(old('betaalwijze')==='contant')>Contant</option>
              </select>
            </div>
          </div>
        </fieldset>

        <div class="sis-form__actions">
          <a class="iuasr-dash-btn" href="{{ route('studenten.index') }}">Annuleren</a>
          <div class="right">
            <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Inschrijven &amp; nummer toekennen</button>
          </div>
        </div>
      </div>
    </div>

    <div>
      <div class="sis-card">
        <div class="sis-card__hd"><h3>Automatisch toegekend</h3></div>
        <div class="sis-fld sis-fld--auto"><label>Studentnummer</label><input type="text" value="26••••" readonly></div>
        <div class="help" style="margin-top:-6px;">Volgend vrije nummer in de jaarreeks — definitief bij opslaan.</div>
      </div>
      <div class="sis-card">
        <div class="sis-card__hd"><h3>AVG</h3></div>
        <p class="sis-muted" style="font-size:12.5px;line-height:1.6;margin:0;">Alleen strikt noodzakelijke gegevens worden vastgelegd (dataminimalisatie). BSN en rekeningnummer volgen pas na expliciet akkoord van de Functionaris Gegevensbescherming. Deze aanmaak wordt gelogd in de audit-log.</p>
      </div>
    </div>
  </div>
</form>
@endsection
