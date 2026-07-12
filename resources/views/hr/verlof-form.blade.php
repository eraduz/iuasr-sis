@extends('layouts.app')

@section('titel', 'Verlof aanvragen')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('verlof.mijn') }}">Mijn verlof</a><span class="sep">›</span><b>Aanvragen</b></div>

<div class="iuasr-dash-vhead"><div><h1>Verlof aanvragen</h1></div></div>

<form method="POST" action="{{ route('verlof.store') }}" class="sis-card sis-form" style="max-width:640px;">
  @csrf

  @if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $errors->first() }}</span></div>
  @endif

  <div class="sis-fld">
    <label>Verloftype <span class="req">*</span></label>
    @php $ty = old('verloftype'); @endphp
    <select name="verloftype" id="vf-type" required><option value="">— kies —</option>@foreach ($types as $w => $l)<option value="{{ $w }}" @selected($ty===$w)>{{ $l }}</option>@endforeach</select>
  </div>

  {{-- Wettelijke toelichting + rekenhulp; verschijnt bij een wettelijk verloftype. --}}
  <div id="vf-wettelijk" class="iuasr-dash-alert iuasr-dash-alert--info" style="display:none;margin-bottom:12px;flex-direction:column;align-items:flex-start;gap:8px;">
    <span id="vf-toelichting"></span>
    <div id="vf-hulp-zwangerschap" style="display:none;">
      <label style="font-size:12px;display:block;margin-bottom:3px;">Uitgerekende datum <small class="sis-muted">(vult van/tot automatisch: 6 wk ervoor t/m 10 wk erna)</small></label>
      <input type="date" id="vf-uitgerekend" style="max-width:200px;">
    </div>
  </div>

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Van <span class="req">*</span></label><input type="date" name="van" id="vf-van" value="{{ old('van') }}" required></div>
    <div class="sis-fld"><label>Tot <span class="req">*</span></label><input type="date" name="tot" id="vf-tot" value="{{ old('tot') }}" required></div>
  </div>
  <div class="sis-fld"><label>Aantal uren <span class="req">*</span></label><input type="number" step="0.5" min="0.5" max="2000" name="uren" id="vf-uren" value="{{ old('uren') }}" required><small class="sis-muted" id="vf-uren-hint">Het aantal verlofuren dat u wilt opnemen.</small></div>
  <div class="sis-fld"><label>Reden / toelichting</label><textarea name="reden" rows="2">{{ old('reden') }}</textarea></div>

  <div class="sis-form__actions">
    <a class="iuasr-dash-btn" href="{{ route('verlof.mijn') }}">Annuleren</a>
    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Indienen</button></div>
  </div>
</form>

@php
  $toelichtingen = collect(\App\Enums\Verloftype::cases())
    ->filter->wettelijk()
    ->mapWithKeys(fn ($t) => [$t->value => $t->toelichting()]);
@endphp
<script>
  (function () {
    var toelichting = @json($toelichtingen);
    var weekuren = {{ $weekuren }};
    var type = document.getElementById('vf-type');
    var box = document.getElementById('vf-wettelijk');
    var tekst = document.getElementById('vf-toelichting');
    var hulpZw = document.getElementById('vf-hulp-zwangerschap');
    var uitger = document.getElementById('vf-uitgerekend');
    var van = document.getElementById('vf-van');
    var tot = document.getElementById('vf-tot');
    var uren = document.getElementById('vf-uren');
    var urenHint = document.getElementById('vf-uren-hint');

    function isoMinusDagen(iso, dagen) {
      var d = new Date(iso + 'T00:00:00');
      d.setDate(d.getDate() + dagen);
      return d.toISOString().slice(0, 10);
    }

    function sync() {
      var t = type.value;
      var note = toelichting[t];
      box.style.display = note ? 'flex' : 'none';
      tekst.textContent = note || '';
      hulpZw.style.display = (t === 'zwangerschap') ? '' : 'none';

      // Uren automatisch voorstellen op basis van de weekuren (partnerverlof).
      if (weekuren > 0 && !uren.value) {
        if (t === 'geboorte') { uren.value = weekuren; }
        else if (t === 'aanvullend_geboorte') { uren.value = weekuren * 5; }
      }
      urenHint.textContent = (t === 'geboorte')
        ? 'Voorstel: 1× de weekuren (' + weekuren + ' uur).'
        : (t === 'aanvullend_geboorte')
          ? 'Voorstel: max. 5× de weekuren (' + (weekuren * 5) + ' uur).'
          : 'Het aantal verlofuren dat u wilt opnemen.';
    }

    function berekenZwangerschap() {
      if (!uitger.value) return;
      van.value = isoMinusDagen(uitger.value, -42); // 6 weken ervoor
      tot.value = isoMinusDagen(uitger.value, 70);  // 10 weken erna
    }

    type.addEventListener('change', sync);
    uitger.addEventListener('change', berekenZwangerschap);
    sync();
  })();
</script>
@endsection
