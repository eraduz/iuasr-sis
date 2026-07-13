@extends('layouts.app')

@section('titel', 'Afstuderen')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ route('studenten.show', $student) }}">{{ $student->studentnummer }}</a><span class="sep">›</span><b>Afstuderen</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Afgestudeerd markeren</h1>
    <div class="summary">Terminale eindstatus · de student wordt alumnus</div>
  </div>
</div>

<div class="sis-grid-2">
  <div>
    <div class="sis-card" style="margin-bottom:16px;">
      <div class="iuasr-dash-candidate__hd" style="margin:0;padding:0;border:0;">
        <span class="iuasr-dash-candidate__avatar" style="width:44px;height:44px;font-size:18px;" aria-hidden="true">{{ mb_substr($student->voornaam,0,1) }}</span>
        <div class="iuasr-dash-candidate__body">
          <div style="font-family:var(--serif-font);font-size:19px;">{{ $student->volledigeNaam() }}</div>
          <div class="iuasr-dash-candidate__meta"><span>{{ $student->studentnummer }}</span></div>
        </div>
      </div>
    </div>

    <form method="POST" action="{{ route('afstuderen.store', $student) }}" class="sis-card sis-form">
      @csrf
      <fieldset class="sis-fieldset">
        <legend>Afstuderen</legend>
        <div class="sis-fld">
          <label>Opleiding <span class="req">*</span></label>
          <select name="inschrijving_id" required>
            @foreach ($kandidaten as $k)
              <option value="{{ $k->id }}" @selected(old('inschrijving_id') == $k->id)>{{ $k->opleiding?->naam }} · jaar {{ $k->leerjaar }} · {{ $k->periode?->naam ?? $k->periode?->code }}@unless($k->isLaatsteLeerjaar()) · vervroegd (examencommissie){{-- --}}@endunless</option>
            @endforeach
          </select>
          @error('inschrijving_id')<p class="sis-muted" style="color:var(--secColor100);font-size:12px;margin:6px 0 0;">{{ $message }}</p>@enderror
        </div>
        <div class="sis-fld">
          <label>Afstudeerdatum <span class="req">*</span></label>
          <input type="date" name="afstudeerdatum" value="{{ old('afstudeerdatum', now()->toDateString()) }}" required>
          <div class="help">De datum waarop de opleiding is afgerond.</div>
          @error('afstudeerdatum')<p class="sis-muted" style="color:var(--secColor100);font-size:12px;margin:6px 0 0;">{{ $message }}</p>@enderror
        </div>
      </fieldset>
      <div class="sis-form__actions">
        <a class="iuasr-dash-btn" href="{{ route('studenten.show', $student) }}">Annuleren</a>
        <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Afgestudeerd markeren</button></div>
      </div>
    </form>
  </div>

  <div>
    <div class="sis-card">
      <div class="sis-card__hd"><h3>Gevolgen</h3></div>
      <ul style="margin:0;padding-left:18px;font-size:13px;line-height:1.7;">
        <li>De inschrijving krijgt status <b>Afgestudeerd</b> en wordt afgerond (alleen-lezen historie).</li>
        <li>De student wordt <b>alumnus</b>; het <b>studentnummer blijft behouden</b>.</li>
        <li>Korting, betaalregeling, aanwezigheidsregeling en vaktoewijzing zijn voor deze opleiding niet meer wijzigbaar.</li>
        <li>De student kan zich later voor een <b>andere</b> opleiding inschrijven (nieuwe registratie), maar niet opnieuw voor deze.</li>
      </ul>
    </div>
    <div class="iuasr-dash-alert iuasr-dash-alert--warn" style="margin-top:16px;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <span>Afstuderen kan alleen in het laatste leerjaar, of eerder met een vrijgave van de examencommissie (vervroegd afstuderen).</span>
    </div>
  </div>
</div>
@endsection
