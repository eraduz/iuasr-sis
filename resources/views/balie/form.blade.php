@extends('layouts.app')

@php
  $nieuw = ! $registratie->exists;
  $titel = $nieuw ? 'Nieuwe registratie' : 'Registratie bewerken';
  $soortWaarde = old('soort', $registratie->soort?->value ?? 'telefoon');
  $richtingWaarde = old('richting', $registratie->richting?->value ?? 'inkomend');
@endphp

@section('titel', $titel)

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('balie.dashboard') }}">Balie / Receptie</a><span class="sep">›</span><a href="{{ route('balie') }}">Logboek</a><span class="sep">›</span><b>{{ $titel }}</b></div>

<div class="iuasr-dash-vhead"><div><h1>{{ $titel }}</h1><div class="summary">Het formulier toont alleen de velden die bij het gekozen soort horen.</div></div></div>

<form method="POST" action="{{ $nieuw ? route('balie.store') : route('balie.update', $registratie) }}" class="sis-card sis-form" style="max-width:760px;" id="balie-form">
  @csrf
  @unless ($nieuw) @method('PUT') @endunless

  @if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $errors->first() }}</span></div>
  @endif

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld">
      <label>Soort <span class="req">*</span></label>
      <select name="soort" id="balie-soort" required>
        @foreach (\App\Enums\BalieSoort::opties() as $waarde => $label)
          <option value="{{ $waarde }}" @selected($soortWaarde === $waarde)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="sis-fld" data-veld="richting">
      <label>Richting <span class="req">*</span></label>
      <select name="richting" id="balie-richting">
        @foreach (\App\Enums\BalieRichting::opties() as $waarde => $label)
          <option value="{{ $waarde }}" @selected($richtingWaarde === $waarde)>{{ $label }}</option>
        @endforeach
      </select>
      <small class="sis-muted">Inkomend = binnengekomen bij de school; uitgaand = door de school verstuurd of gebeld.</small>
    </div>
  </div>

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld">
      <label>Datum en tijd <span class="req">*</span></label>
      <input type="datetime-local" name="datum_tijd" value="{{ old('datum_tijd', optional($registratie->datum_tijd)->format('Y-m-d\TH:i')) }}" required>
    </div>
    <div class="sis-fld" data-veld="vertrek">
      <label>Vertrokken op</label>
      <input type="datetime-local" name="vertrokken_op" value="{{ old('vertrokken_op', optional($registratie->vertrokken_op)->format('Y-m-d\TH:i')) }}">
      <small class="sis-muted">Leeg laten zolang de bezoeker nog in het pand is; afmelden kan met één klik op het overzicht.</small>
    </div>
  </div>

  <div class="sis-fld" data-veld="onderwerp">
    <label>Onderwerp <span class="req">*</span></label>
    <input type="text" name="onderwerp" value="{{ old('onderwerp', $registratie->onderwerp) }}" maxlength="255" placeholder="Waar ging het over?">
  </div>

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld">
      <label id="balie-contact-label">Naam <span class="req">*</span></label>
      <input type="text" name="contact_naam" value="{{ old('contact_naam', $registratie->contact_naam) }}" maxlength="255" required>
    </div>
    <div class="sis-fld">
      <label>Organisatie</label>
      <input type="text" name="contact_organisatie" value="{{ old('contact_organisatie', $registratie->contact_organisatie) }}" maxlength="255" placeholder="Optioneel">
    </div>
  </div>

  <div class="sis-fld" data-veld="telefoon">
    <label>Telefoonnummer</label>
    <input type="text" name="contact_telefoon" value="{{ old('contact_telefoon', $registratie->contact_telefoon) }}" maxlength="30" placeholder="Optioneel — handig om terug te bellen">
  </div>

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld">
      <label id="balie-bestemd-label">Bestemd voor (medewerker)</label>
      <select name="medewerker_id">
        <option value="">— geen specifieke medewerker —</option>
        @foreach ($medewerkers as $m)
          <option value="{{ $m->id }}" @selected((int) old('medewerker_id', $registratie->medewerker_id) === $m->id)>{{ $m->volledigeNaam() }}</option>
        @endforeach
      </select>
    </div>
    <div class="sis-fld">
      <label>Of afdeling</label>
      <input type="text" name="afdeling" value="{{ old('afdeling', $registratie->afdeling) }}" maxlength="255" placeholder="Bijv. Studentenzaken">
      <small class="sis-muted">Vul dit in wanneer het niet voor één persoon maar voor een afdeling bestemd is.</small>
    </div>
  </div>

  <div class="sis-fld">
    <label>Korte toelichting</label>
    <textarea name="toelichting" maxlength="2000" placeholder="Wat is er besproken of afgesproken?">{{ old('toelichting', $registratie->toelichting) }}</textarea>
  </div>

  <div class="sis-form__actions">
    <a class="iuasr-dash-btn" href="{{ route('balie') }}">Annuleren</a>
    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div>
  </div>
</form>
@endsection

@push('scripts')
<script>
// Toont per soort alleen de relevante velden. De server valideert dit opnieuw:
// een bezoek is altijd inkomend, bij post wordt geen onderwerp vastgelegd en
// alleen een bezoek kent een vertrekmoment.
(function () {
  var soort = document.getElementById('balie-soort');
  var richting = document.getElementById('balie-richting');
  if (!soort) return;

  var toon = function (naam, zichtbaar) {
    var veld = document.querySelector('[data-veld="' + naam + '"]');
    if (veld) veld.style.display = zichtbaar ? '' : 'none';
  };

  var contactLabels = {
    'bezoek': 'Naam bezoeker',
    'telefoon-inkomend': 'Beller',
    'telefoon-uitgaand': 'Gebeld met',
    'post-inkomend': 'Afzender',
    'post-uitgaand': 'Verzonden aan'
  };

  var bijwerken = function () {
    var s = soort.value;
    var r = richting ? richting.value : 'inkomend';

    toon('richting', s !== 'bezoek');
    toon('vertrek', s === 'bezoek');
    toon('onderwerp', s !== 'post');
    toon('telefoon', s !== 'post');

    var onderwerp = document.querySelector('input[name="onderwerp"]');
    if (onderwerp) onderwerp.required = (s !== 'post');

    var sleutel = s === 'bezoek' ? 'bezoek' : s + '-' + r;
    var label = document.getElementById('balie-contact-label');
    if (label) label.innerHTML = (contactLabels[sleutel] || 'Naam') + ' <span class="req">*</span>';

    var bestemd = document.getElementById('balie-bestemd-label');
    if (bestemd) bestemd.textContent = s === 'bezoek' ? 'Afspraak met (medewerker)' : 'Bestemd voor (medewerker)';
  };

  soort.addEventListener('change', bijwerken);
  if (richting) richting.addEventListener('change', bijwerken);
  bijwerken();
})();
</script>
@endpush
