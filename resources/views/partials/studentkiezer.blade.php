{{--
  Zoekveld om één student te kiezen uit een lange lijst. Vervangt de keuzelijst
  op plekken waar honderden studenten in beeld zijn — daar is scrollen door een
  <select> onwerkbaar.

  Bewust zonder JavaScript: een <datalist> is gewoon HTML en filtert in de
  browser terwijl u typt. De optiewaarde is "studentnummer — Naam", zodat u op
  BEIDE kunt zoeken; de server pelt het studentnummer er weer af. Zo blijft het
  formulier ook werken als er iets met de scripts misgaat, en blijft de
  server-side controle op wie u mag kiezen ongewijzigd.

  Parameters:
    $studenten  collectie studenten (met studentnummer + volledigeNaam())
    $naam       naam van het formulierveld (bv. 'student_zoek')
    $lijstId    unieke id voor de <datalist>
    $waarde     voorgevulde waarde ("261234 — Naam"), of leeg
    $verplicht  bool
--}}
@php
    $lijstId = $lijstId ?? ($naam.'-lijst');
    $verplicht = $verplicht ?? true;
    // Optioneel: per studentnummer het leerjaar per opleiding, voor leerjaar-filters.
    $leerjaren = $leerjaren ?? [];
@endphp
<input type="text" name="{{ $naam }}" list="{{ $lijstId }}" value="{{ $waarde ?? '' }}"
       autocomplete="off" placeholder="Typ een studentnummer of naam…"
       @if ($verplicht) required @endif>
<datalist id="{{ $lijstId }}">
  @foreach ($studenten as $s)
    <option value="{{ $s->studentnummer }} — {{ $s->volledigeNaam() }}"@isset($leerjaren[$s->studentnummer]) data-leerjaren="{{ json_encode($leerjaren[$s->studentnummer]) }}"@endisset></option>
  @endforeach
</datalist>
<small class="sis-muted">{{ $studenten->count() }} {{ $studenten->count() === 1 ? 'student' : 'studenten' }} beschikbaar. Typ een deel van het nummer of de naam en kies uit de lijst.</small>
