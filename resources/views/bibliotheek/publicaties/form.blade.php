@extends('layouts.app')

@php
  $nieuw = ! $publicatie->exists;
  $titel = $nieuw ? 'Nieuwe publicatie' : 'Publicatie bewerken';
  $soortWaarde = (int) old('soort_id', $publicatie->soort_id);
  $boekSoortId = $soorten->firstWhere('code', 'boek')?->id;
  $digitaalSoortIds = $soorten->where('heeft_exemplaren', false)->pluck('id')->all();
  $auteurRegels = old('auteurs', $gekozenAuteurs ?: ['']);
@endphp

@section('titel', $titel)

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('bibliotheek.dashboard') }}">Bibliotheek</a><span class="sep">›</span><a href="{{ route('bibliotheek.publicaties') }}">Catalogus</a><span class="sep">›</span><b>{{ $titel }}</b></div>

<div class="iuasr-dash-vhead"><div><h1>{{ $titel }}</h1><div class="summary">De titel staat één keer; de fysieke boeken voegt u toe als exemplaren.</div></div></div>

<form method="POST" action="{{ $nieuw ? route('bibliotheek.publicaties.store') : route('bibliotheek.publicaties.update', $publicatie) }}" class="sis-card sis-form" style="max-width:820px;" id="pub-form">
  @csrf
  @unless ($nieuw) @method('PUT') @endunless

  @if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><span>{{ $errors->first() }}</span></div>
  @endif

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld">
      <label>Publicatietype <span class="req">*</span></label>
      <select name="soort_id" id="pub-soort" required
              data-boek="{{ $boekSoortId }}" data-zonder-exemplaren="{{ implode(',', $digitaalSoortIds) }}">
        @foreach ($soorten as $s)
          <option value="{{ $s->id }}" @selected($soortWaarde === $s->id)>{{ $s->naam }}</option>
        @endforeach
      </select>
      <small class="sis-muted">Staat het soort er niet bij (bijv. cd of dvd)? Voeg het toe onder Beheer &rarr; Soorten.</small>
    </div>
    <div class="sis-fld">
      <label>ISBN</label>
      <input type="text" name="isbn" value="{{ old('isbn', $publicatie->isbn) }}" maxlength="20" placeholder="Optioneel">
    </div>
  </div>

  <div class="sis-fld"><label>Titel <span class="req">*</span></label><input type="text" name="titel" value="{{ old('titel', $publicatie->titel) }}" maxlength="255" required dir="auto"></div>

  <div class="sis-fld">
    <label>Rek / plaats</label>
    <input type="text" name="bron_rekcode" value="{{ old('bron_rekcode', $publicatie->bron_rekcode) }}" maxlength="40" placeholder="bijv. F. 1070">
    <small class="sis-muted">Waar het boek in de bibliotheek ligt: de letter is de kast, het nummer de plaats in het rek. Dit is het nummer waarmee de collectie altijd al is bijgehouden.</small>
  </div>

  <div class="sis-fld">
    <label>Auteur(s)</label>
    <div id="auteur-lijst">
      @foreach ($auteurRegels as $auteur)
        <input type="text" name="auteurs[]" value="{{ $auteur }}" maxlength="255" dir="auto" placeholder="Naam van de auteur" style="margin-bottom:6px;">
      @endforeach
    </div>
    <button type="button" class="iuasr-dash-btn iuasr-dash-btn--sm" id="auteur-erbij">Auteur toevoegen</button>
    <small class="sis-muted">Meerdere auteurs mogelijk. Een naam die nog niet bestaat wordt automatisch aangemaakt.</small>
  </div>

  <div class="sis-fld">
    <label>Taal / talen</label>
    <div style="display:flex; flex-wrap:wrap; gap:10px 18px; margin-top:4px;">
      @foreach ($talen as $t)
        <label class="sis-check-inline"><input type="checkbox" name="talen[]" value="{{ $t->id }}" @checked(in_array($t->id, old('talen', $gekozenTalen)))> {{ $t->naam }}</label>
      @endforeach
    </div>
    <small class="sis-muted">Een publicatie kan meerdere talen hebben (bijv. Arabisch met Nederlandse vertaling).</small>
  </div>

  <div class="sis-fld-row sis-fld-row--3">
    <div class="sis-fld"><label>Uitgavejaar</label><input type="number" name="uitgavejaar" value="{{ old('uitgavejaar', $publicatie->uitgavejaar) }}" min="1000" max="{{ date('Y') + 1 }}"></div>
    <div class="sis-fld"><label>Druknummer</label><input type="text" name="druknummer" value="{{ old('druknummer', $publicatie->druknummer) }}" maxlength="30"></div>
    <div class="sis-fld">
      <label>Vakgebied</label>
      <select name="vakgebied_id">
        <option value="">— kies —</option>
        @foreach ($vakgebieden as $v)
          <option value="{{ $v->id }}" @selected((int) old('vakgebied_id', $publicatie->vakgebied_id) === $v->id)>{{ $v->naam }}</option>
        @endforeach
      </select>
    </div>
  </div>

  <div class="sis-fld-row sis-fld-row--2" data-veld="reeks">
    <div class="sis-fld">
      <label>Onderdeel van boekreeks</label>
      <select name="reeks_id">
        <option value="">— losse titel —</option>
        @foreach ($reeksen as $r)
          <option value="{{ $r->id }}" @selected((int) old('reeks_id', $publicatie->reeks_id) === $r->id)>{{ $r->titel }}</option>
        @endforeach
      </select>
      <small class="sis-muted">Een hele reeks in één keer invoeren? Gebruik <a href="{{ route('bibliotheek.reeksen.create') }}">Boekreeks aanmaken</a>.</small>
    </div>
    <div class="sis-fld"><label>Deelnummer</label><input type="number" name="deelnummer" value="{{ old('deelnummer', $publicatie->deelnummer) }}" min="1" max="999"></div>
  </div>

  @if ($nieuw)
    <div class="sis-fld-row sis-fld-row--2" data-veld="exemplaren">
      <div class="sis-fld">
        <label>Serienummer(s) van de exemplaren</label>
        <div id="exemplaar-lijst">
          @foreach (old('exemplaren', ['']) as $serienummer)
            <input type="text" name="exemplaren[]" value="{{ $serienummer }}" maxlength="40" placeholder="Intern serienummer" style="margin-bottom:6px;">
          @endforeach
        </div>
        <button type="button" class="iuasr-dash-btn iuasr-dash-btn--sm" id="exemplaar-erbij">Exemplaar toevoegen</button>
        <small class="sis-muted">Heeft u drie exemplaren van dit boek? Voer drie serienummers in. Later bijvoegen kan ook.</small>
      </div>
      <div class="sis-fld">
        <label>Boekenkast / reknummer</label>
        <select name="kast_id">
          <option value="">— geen —</option>
          @foreach ($kasten as $k)
            <option value="{{ $k->id }}" @selected((int) old('kast_id') === $k->id)>{{ $k->code }}@if ($k->omschrijving) — {{ $k->omschrijving }}@endif</option>
          @endforeach
        </select>
      </div>
    </div>
  @endif

  <div class="sis-fld"><label>Korte opmerking</label><textarea name="opmerking" maxlength="2000" dir="auto">{{ old('opmerking', $publicatie->opmerking) }}</textarea></div>

  <div class="sis-form__actions">
    <a class="iuasr-dash-btn" href="{{ $nieuw ? route('bibliotheek.publicaties') : route('bibliotheek.publicaties.show', $publicatie) }}">Annuleren</a>
    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div>
  </div>
</form>
@endsection

@push('scripts')
<script>
// Een tijdschrift en een digitaal document horen niet in een boekreeks, en een
// digitaal document kent geen fysieke exemplaren. De server dwingt dit opnieuw af.
(function () {
  var soort = document.getElementById('pub-soort');
  if (!soort) return;

  var toon = function (naam, zichtbaar) {
    var veld = document.querySelector('[data-veld="' + naam + '"]');
    if (veld) veld.style.display = zichtbaar ? '' : 'none';
  };

  // Welke soort een boekreeks kan hebben en welke geen exemplaren heeft, staat in
  // de opzoektabel — de vlaggen komen mee als data-attributen. De server dwingt
  // dit opnieuw af.
  var boekId = soort.getAttribute('data-boek');
  var zonderExemplaren = (soort.getAttribute('data-zonder-exemplaren') || '').split(',').filter(Boolean);

  var bijwerken = function () {
    toon('reeks', soort.value === boekId);
    toon('exemplaren', zonderExemplaren.indexOf(soort.value) === -1);
  };

  soort.addEventListener('change', bijwerken);
  bijwerken();

  var erbij = function (knopId, lijstId, naam, placeholder) {
    var knop = document.getElementById(knopId);
    if (!knop) return;
    knop.addEventListener('click', function () {
      var invoer = document.createElement('input');
      invoer.type = 'text';
      invoer.name = naam;
      invoer.placeholder = placeholder;
      invoer.setAttribute('dir', 'auto');
      invoer.style.marginBottom = '6px';
      document.getElementById(lijstId).appendChild(invoer);
      invoer.focus();
    });
  };

  erbij('auteur-erbij', 'auteur-lijst', 'auteurs[]', 'Naam van de auteur');
  erbij('exemplaar-erbij', 'exemplaar-lijst', 'exemplaren[]', 'Intern serienummer');
})();
</script>
@endpush
