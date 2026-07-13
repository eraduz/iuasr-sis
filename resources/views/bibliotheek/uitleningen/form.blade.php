@extends('layouts.app')

@section('titel', 'Uitlenen')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('bibliotheek.dashboard') }}">Bibliotheek</a><span class="sep">›</span><a href="{{ route('bibliotheek.uitleningen') }}">Uitleningen</a><span class="sep">›</span><b>Uitlenen</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Uitlenen</h1>
    <div class="summary">De lener is een bestaande student of medewerker; telefoon en e-mail komen uit het dossier.</div>
  </div>
</div>

<form method="POST" action="{{ route('bibliotheek.uitlenen.store') }}" class="sis-card sis-form" style="max-width:760px;" id="uitleen-form">
  @csrf

  @if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><span>{{ $errors->first() }}</span></div>
  @endif

  <div class="sis-fld">
    <label>Exemplaar <span class="req">*</span></label>
    <select name="exemplaar_id" required>
      <option value="">— kies een beschikbaar exemplaar —</option>
      @foreach ($exemplaren as $ex)
        <option value="{{ $ex->id }}" @selected((int) old('exemplaar_id', $gekozenExemplaar?->id) === $ex->id)>
          {{ $ex->serienummer }} — {{ $ex->publicatie?->volledigeTitel() }}
        </option>
      @endforeach
    </select>
    <small class="sis-muted">Alleen exemplaren die nu beschikbaar zijn. Uitgeleend, verloren of beschadigd materiaal staat hier niet tussen.</small>
  </div>

  <div class="sis-fld">
    <label>Lener <span class="req">*</span></label>
    <div class="sis-check-inline" style="display:flex; gap:18px; margin-bottom:8px;">
      <label class="sis-check-inline"><input type="radio" name="lenerstype" value="student" id="type-student" @checked(old('lenerstype', 'student') === 'student')> Student</label>
      <label class="sis-check-inline"><input type="radio" name="lenerstype" value="medewerker" id="type-medewerker" @checked(old('lenerstype') === 'medewerker')> Docent / medewerker</label>
    </div>
  </div>

  <div class="sis-fld" data-veld="student">
    <label>Student</label>
    <select name="student_id" id="student-select">
      <option value="">— kies een student —</option>
      @foreach ($studenten as $s)
        <option value="{{ $s->id }}" @selected((int) old('student_id') === $s->id)>{{ $s->studentnummer }} — {{ $s->volledigeNaam() }}</option>
      @endforeach
    </select>
    <small class="sis-muted">Standaardtermijn voor studenten: {{ $termijnStudent }} dagen.</small>
  </div>

  <div class="sis-fld" data-veld="medewerker">
    <label>Docent / medewerker</label>
    <select name="medewerker_id" id="medewerker-select">
      <option value="">— kies een medewerker —</option>
      @foreach ($medewerkers as $m)
        <option value="{{ $m->id }}" @selected((int) old('medewerker_id') === $m->id)>{{ $m->volledigeNaam() }}</option>
      @endforeach
    </select>
    <small class="sis-muted">Standaardtermijn voor docenten: {{ $termijnDocent }} dagen. Geen boete bij te laat.</small>
  </div>

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld">
      <label>Uitleendatum <span class="req">*</span></label>
      <input type="date" name="uitgeleend_op" id="uitgeleend-op" value="{{ old('uitgeleend_op', now()->format('Y-m-d')) }}" required>
    </div>
    <div class="sis-fld">
      <label>Verwachte retourdatum <span class="req">*</span></label>
      <input type="date" name="verwachte_retour_op" id="retour-op" value="{{ old('verwachte_retour_op', now()->addDays($termijnStudent)->format('Y-m-d')) }}" required>
      <small class="sis-muted">Wordt automatisch berekend uit de termijn, maar u mag hem aanpassen.</small>
    </div>
  </div>

  <div class="sis-form__actions">
    <a class="iuasr-dash-btn" href="{{ route('bibliotheek.uitleningen') }}">Annuleren</a>
    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Uitlenen en bevestiging mailen</button></div>
  </div>
</form>
@endsection

@push('scripts')
<script>
// Toont het juiste lenersveld en berekent de retourdatum uit de standaardtermijn.
// De server valideert opnieuw: precies één lener, en de retourdatum moet ná de
// uitleendatum liggen.
(function () {
  var student = document.getElementById('type-student');
  var medewerker = document.getElementById('type-medewerker');
  var uit = document.getElementById('uitgeleend-op');
  var retour = document.getElementById('retour-op');
  var termijn = { student: {{ $termijnStudent }}, medewerker: {{ $termijnDocent }} };
  if (!student || !medewerker) return;

  var toon = function (naam, zichtbaar) {
    var veld = document.querySelector('[data-veld="' + naam + '"]');
    if (veld) veld.style.display = zichtbaar ? '' : 'none';
  };

  var berekenRetour = function () {
    if (!uit.value) return;
    var dagen = student.checked ? termijn.student : termijn.medewerker;
    var datum = new Date(uit.value);
    datum.setDate(datum.getDate() + dagen);
    retour.value = datum.toISOString().slice(0, 10);
  };

  var bijwerken = function () {
    toon('student', student.checked);
    toon('medewerker', medewerker.checked);
    berekenRetour();
  };

  student.addEventListener('change', bijwerken);
  medewerker.addEventListener('change', bijwerken);
  uit.addEventListener('change', berekenRetour);
  toon('student', student.checked);
  toon('medewerker', medewerker.checked);
})();
</script>
@endpush
