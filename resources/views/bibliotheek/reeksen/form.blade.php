@extends('layouts.app')

@section('titel', 'Boekreeks aanmaken')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('bibliotheek.dashboard') }}">Bibliotheek</a><span class="sep">›</span><a href="{{ route('bibliotheek.reeksen') }}">Boekreeksen</a><span class="sep">›</span><b>Nieuwe boekreeks</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Boekreeks aanmaken</h1>
    <div class="summary">Voer de gedeelde gegevens één keer in en voeg hieronder alle delen in één keer toe.</div>
  </div>
</div>

<form method="POST" action="{{ route('bibliotheek.reeksen.store') }}" class="sis-card sis-form" style="max-width:900px;" id="reeks-form">
  @csrf

  @if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><span>{{ $errors->first() }}</span></div>
  @endif

  <div class="sis-fld"><label>Reekstitel <span class="req">*</span></label><input type="text" name="titel" value="{{ old('titel') }}" maxlength="255" required dir="auto" placeholder="bijv. Tafsir Ibn Kathir"></div>

  <div class="sis-fld">
    <label>Auteur(s) — geldt voor alle delen</label>
    <div id="auteur-lijst">
      @foreach (old('auteurs', ['']) as $auteur)
        <input type="text" name="auteurs[]" value="{{ $auteur }}" maxlength="255" dir="auto" placeholder="Naam van de auteur" style="margin-bottom:6px;">
      @endforeach
    </div>
    <button type="button" class="iuasr-dash-btn iuasr-dash-btn--sm" id="auteur-erbij">Auteur toevoegen</button>
  </div>

  <div class="sis-fld">
    <label>Taal / talen — geldt voor alle delen</label>
    <div style="display:flex; flex-wrap:wrap; gap:10px 18px; margin-top:4px;">
      @foreach ($talen as $t)
        <label class="sis-check-inline"><input type="checkbox" name="talen[]" value="{{ $t->id }}" @checked(in_array($t->id, old('talen', [])))> {{ $t->naam }}</label>
      @endforeach
    </div>
  </div>

  <div class="sis-fld-row sis-fld-row--3">
    <div class="sis-fld">
      <label>Vakgebied</label>
      <select name="vakgebied_id">
        <option value="">— kies —</option>
        @foreach ($vakgebieden as $v)
          <option value="{{ $v->id }}" @selected((int) old('vakgebied_id') === $v->id)>{{ $v->naam }}</option>
        @endforeach
      </select>
    </div>
    <div class="sis-fld"><label>Uitgavejaar</label><input type="number" name="uitgavejaar" value="{{ old('uitgavejaar') }}" min="1000" max="{{ date('Y') + 1 }}"></div>
    <div class="sis-fld">
      <label>Boekenkast / reknummer</label>
      <select name="kast_id">
        <option value="">— geen —</option>
        @foreach ($kasten as $k)
          <option value="{{ $k->id }}" @selected((int) old('kast_id') === $k->id)>{{ $k->code }}</option>
        @endforeach
      </select>
    </div>
  </div>

  <h3 style="margin-top:18px;">Delen</h3>
  <p class="sis-muted">Elk deel wordt een eigen, apart uitleenbaar boek. De titel mag leeg blijven: dan krijgt het deel de reekstitel.</p>

  <table class="iuasr-dash-tbl" id="deel-tabel">
    <thead><tr><th style="width:110px;">Deelnummer</th><th>Eigen titel (optioneel)</th><th style="width:220px;">Serienummer (optioneel)</th></tr></thead>
    <tbody id="deel-lijst">
      @php $bestaandeDelen = old('delen', [['deelnummer' => 1], ['deelnummer' => 2]]); @endphp
      @foreach ($bestaandeDelen as $i => $deel)
        <tr>
          <td><input type="number" name="delen[{{ $i }}][deelnummer]" value="{{ $deel['deelnummer'] ?? $i + 1 }}" min="1" max="999" required></td>
          <td><input type="text" name="delen[{{ $i }}][titel]" value="{{ $deel['titel'] ?? '' }}" maxlength="255" dir="auto"></td>
          <td><input type="text" name="delen[{{ $i }}][serienummer]" value="{{ $deel['serienummer'] ?? '' }}" maxlength="40"></td>
        </tr>
      @endforeach
    </tbody>
  </table>
  <button type="button" class="iuasr-dash-btn iuasr-dash-btn--sm" id="deel-erbij" style="margin-top:8px;">Nog een deel</button>

  <div class="sis-fld" style="margin-top:14px;"><label>Opmerking bij de reeks</label><textarea name="opmerking" maxlength="2000" dir="auto">{{ old('opmerking') }}</textarea></div>

  <div class="sis-form__actions">
    <a class="iuasr-dash-btn" href="{{ route('bibliotheek.reeksen') }}">Annuleren</a>
    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Reeks met delen aanmaken</button></div>
  </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
  var lijst = document.getElementById('deel-lijst');
  var knop = document.getElementById('deel-erbij');
  if (!lijst || !knop) return;

  knop.addEventListener('click', function () {
    var index = lijst.rows.length;
    var rij = lijst.insertRow();
    rij.innerHTML =
      '<td><input type="number" name="delen[' + index + '][deelnummer]" value="' + (index + 1) + '" min="1" max="999" required></td>' +
      '<td><input type="text" name="delen[' + index + '][titel]" maxlength="255" dir="auto"></td>' +
      '<td><input type="text" name="delen[' + index + '][serienummer]" maxlength="40"></td>';
    rij.querySelector('input').focus();
  });

  var auteurKnop = document.getElementById('auteur-erbij');
  if (auteurKnop) {
    auteurKnop.addEventListener('click', function () {
      var invoer = document.createElement('input');
      invoer.type = 'text';
      invoer.name = 'auteurs[]';
      invoer.placeholder = 'Naam van de auteur';
      invoer.setAttribute('dir', 'auto');
      invoer.style.marginBottom = '6px';
      document.getElementById('auteur-lijst').appendChild(invoer);
      invoer.focus();
    });
  }
})();
</script>
@endpush
