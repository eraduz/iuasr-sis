@extends('layouts.app')

@section('titel', 'Herinschrijven')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ route('studenten.show', $student) }}">{{ $student->studentnummer }}</a><span class="sep">›</span><b>Herinschrijven</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Herinschrijven</h1>
    <div class="summary">Bestaande student · nieuwe periode</div>
  </div>
</div>

@if ($errors->any())
  <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:16px;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>Controleer de invoer: {{ $errors->first() }}</span></div>
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
        <legend>Nieuwe inschrijfperiode</legend>
        <div class="sis-fld">
          <label>Studiejaar <span class="req">*</span></label>
          <select name="periode_id" required>
            @foreach ($perioden as $p)
              <option value="{{ $p->id }}" @selected(old('periode_id') == $p->id)>{{ $p->naam }}</option>
            @endforeach
          </select>
        </div>
        <div class="sis-fld-row sis-fld-row--2">
          <div class="sis-fld"><label>Klas</label>
            <select name="klas_id">
              <option value="">— geen —</option>
              @foreach ($klassen as $k)
                <option value="{{ $k->id }}" @selected(old('klas_id') == $k->id)>{{ $k->code }} · {{ $k->opleiding?->code }}</option>
              @endforeach
            </select>
          </div>
          <div class="sis-fld"><label>Leerjaar</label><input type="number" name="leerjaar" min="1" max="10" value="{{ old('leerjaar', ($huidige->leerjaar ?? 1) + 1) }}"></div>
        </div>
        <div class="sis-fld"><label>Inschrijfdatum <span class="req">*</span></label><input type="date" name="inschrijfdatum" value="{{ old('inschrijfdatum', now()->toDateString()) }}" required></div>
      </fieldset>
      <div class="sis-form__actions">
        <a class="iuasr-dash-btn" href="{{ route('studenten.show', $student) }}">Annuleren</a>
        <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Herinschrijving vastleggen</button></div>
      </div>
    </form>
  </div>

  <div>
    <div class="sis-card">
      <div class="sis-card__hd"><h3>Ongewijzigd overgenomen</h3></div>
      <dl class="sis-dl">
        <dt>Studentnummer</dt><dd class="tnum">{{ $student->studentnummer }} <span class="sis-pill-soft">blijft gelijk</span></dd>
        <dt>Opleiding</dt><dd>{{ $huidige?->opleiding?->naam ?? '—' }}</dd>
        <dt>Persoonsgegevens</dt><dd>Ongewijzigd</dd>
      </dl>
    </div>
    <div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-top:16px;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg>
      <span>Herinschrijven is een <b>vereenvoudigd formulier</b>: alleen de nieuwe periode en klas hoeven te worden bevestigd. Behaalde EC blijven behouden.</span>
    </div>
  </div>
</div>
@endsection
