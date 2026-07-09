@extends('layouts.app')

@php $tweede = ($modus ?? 'herinschrijven') === 'tweede'; @endphp
@section('titel', $tweede ? 'Tweede opleiding' : 'Herinschrijven')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ route('studenten.show', $student) }}">{{ $student->studentnummer }}</a><span class="sep">›</span><b>{{ $tweede ? 'Tweede opleiding' : 'Herinschrijven' }}</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>{{ $tweede ? 'Tweede opleiding toevoegen' : 'Herinschrijven' }}</h1>
    <div class="summary">{{ $tweede ? 'Extra opleiding naast de lopende inschrijving · dezelfde student' : 'Bestaande student · nieuwe periode' }}</div>
  </div>
</div>

@if ($tweede)
  <div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-bottom:16px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg>
    <span>U voegt een <b>tweede opleiding</b> toe naast de lopende inschrijving. De student volgt dan twee opleidingen tegelijk. Kies een <b>andere opleiding</b> dan de huidige en het gewenste studiejaar; collegegeld wordt per studiejaar één keer berekend.</span>
  </div>
@endif

@if ($errors->any())
  <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:16px;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>Controleer de invoer: {{ $errors->first() }}</span></div>
@endif

@if ($financieel['achterstand'])
  <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:16px;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    <span><b>Herinschrijven geblokkeerd.</b> Openstaande betalingsachterstand van € {{ number_format($financieel['openstaand'], 2, ',', '.') }}. De studievoortgang is geblokkeerd tot de schuld is voldaan.</span>
  </div>
@endif

<div class="sis-grid-2">
  <div>
    <div class="sis-card" style="margin-bottom:16px;">
      <div class="iuasr-dash-candidate__hd" style="margin:0;padding:0;border:0;">
        <span class="iuasr-dash-candidate__avatar" style="width:44px;height:44px;font-size:18px;" aria-hidden="true">{{ mb_substr($student->voornaam,0,1) }}</span>
        <div class="iuasr-dash-candidate__body">
          <div style="font-family:var(--serif-font);font-size:19px;">{{ $student->volledigeNaam() }}</div>
          <div class="iuasr-dash-candidate__meta"><span>{{ $student->studentnummer }}</span><span class="dot"></span><span>{{ $huidige?->opleiding?->naam ?? '—' }}</span></div>
        </div>
      </div>
    </div>

    <form method="POST" action="{{ route('herinschrijven.store', $student) }}" class="sis-card sis-form">
      @csrf
      <fieldset class="sis-fieldset">
        <legend>Nieuwe inschrijving</legend>
        <div class="sis-fld">
          <label>Opleiding <span class="req">*</span></label>
          <select name="opleiding_id" id="hi-opleiding" required data-huidige="{{ $huidige?->opleiding_id }}">
            @foreach ($opleidingen as $o)
              <option value="{{ $o->id }}" {{ old('opleiding_id', $huidige?->opleiding_id) == $o->id ? 'selected' : '' }}>{{ $o->naam }}</option>
            @endforeach
          </select>
          <small class="sis-muted" style="font-size:12px;">Wisselt de student van opleiding (studiewissel)? Kies de nieuwe opleiding; het leerjaar wordt dan automatisch 1.</small>
        </div>
        @php $actievePeriode = $perioden->firstWhere('actief', true); @endphp
        <div class="sis-fld">
          <label>Studiejaar <span class="req">*</span></label>
          <select name="periode_id" required>
            @foreach ($perioden as $p)
              <option value="{{ $p->id }}" {{ old('periode_id', optional($actievePeriode)->id) == $p->id ? 'selected' : '' }}>{{ $p->naam }}{{ $p->actief ? ' · huidig' : '' }}</option>
            @endforeach
          </select>
        </div>
        <div class="sis-fld-row sis-fld-row--2">
          <div class="sis-fld"><label>Klas</label>
            <select name="klas_id" id="hi-klas">
              <option value="">— geen —</option>
              @foreach ($klassen as $k)
                <option value="{{ $k->id }}" data-opleiding="{{ $k->opleiding_id }}" {{ old('klas_id') == $k->id ? 'selected' : '' }}>{{ $k->code }} · {{ $k->opleiding?->code }}</option>
              @endforeach
            </select>
          </div>
          <div class="sis-fld"><label>Leerjaar <span class="req">*</span></label><input type="number" id="hi-leerjaar" name="leerjaar" min="1" max="10" required value="{{ old('leerjaar', ($huidige->leerjaar ?? 1) + 1) }}"></div>
        </div>
        <div class="sis-fld"><label>Inschrijfdatum <span class="req">*</span></label><input type="date" name="inschrijfdatum" value="{{ old('inschrijfdatum', now()->toDateString()) }}" required></div>
      </fieldset>
      <div class="sis-form__actions">
        <a class="iuasr-dash-btn" href="{{ route('studenten.show', $student) }}">Annuleren</a>
        <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit" {{ $financieel['achterstand'] ? 'disabled' : '' }}>Herinschrijving vastleggen</button></div>
      </div>
    </form>
  </div>

  <div>
    <div class="sis-card">
      <div class="sis-card__hd"><h3>Wat blijft gelijk</h3></div>
      <dl class="sis-dl">
        <dt>Studentnummer</dt><dd class="tnum">{{ $student->studentnummer }} <span class="sis-pill-soft">blijft gelijk</span></dd>
        <dt>Vorige opleiding</dt><dd>{{ $huidige?->opleiding?->naam ?? '—' }}{{ $huidige ? ' · '.$huidige->status->label() : '' }}</dd>
        <dt>Persoonsgegevens</dt><dd>Ongewijzigd</dd>
      </dl>
    </div>
    <div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-top:16px;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg>
      <span>Het studentnummer en de persoonsgegevens blijven gelijk. U kunt <b>dezelfde opleiding</b> kiezen (vervolg of jaar overdoen) óf een <b>andere opleiding</b> (studiewissel). Er wordt een nieuwe inschrijving gemaakt en de vakken worden op de gekozen opleiding + leerjaar toegewezen. Eerder behaalde resultaten blijven op de vorige inschrijving bewaard.</span>
    </div>
  </div>
</div>

@push('scripts')
<script>
  (function () {
    var opl = document.getElementById('hi-opleiding');
    var klas = document.getElementById('hi-klas');
    var leerjaar = document.getElementById('hi-leerjaar');
    if (!opl || !klas) return;
    var huidige = opl.dataset.huidige || '';

    function filterKlas() {
      Array.prototype.forEach.call(klas.options, function (o) {
        if (!o.value) return;
        var hoort = o.dataset.opleiding === opl.value;
        o.hidden = !hoort;
        if (!hoort && o.selected) klas.value = '';
      });
    }
    opl.addEventListener('change', function () {
      filterKlas();
      if (opl.value !== huidige && leerjaar) leerjaar.value = 1;
    });
    filterKlas();
  })();
</script>
@endpush
@endsection
