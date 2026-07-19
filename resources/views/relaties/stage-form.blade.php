@extends('layouts.app')

@php
    $magBeoordeling = $stage->exists;
    $titel = $stage->exists ? 'Stage bewerken' : 'Student plaatsen';
@endphp

@section('titel', $titel)

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('relaties') }}">Organisaties</a><span class="sep">›</span><a href="{{ route('relaties.show', $organisatie) }}">{{ $organisatie->naam }}</a><span class="sep">›</span><b>{{ $stage->exists ? $stage->stagenummer : 'Student plaatsen' }}</b></div>

<div class="iuasr-dash-vhead"><div><h1>{{ $titel }}</h1><div class="summary">Bij <b>{{ $organisatie->naam }}</b> ({{ $organisatie->relatienummer }})@if($stage->exists) · stagenummer <b>{{ $stage->stagenummer }}</b>@endif</div></div></div>

<form method="POST" action="{{ $stage->exists ? route('stages.update', $stage) : route('stages.store', $organisatie) }}" class="sis-card sis-form" style="max-width:820px;">
  @csrf
  @if ($stage->exists) @method('PUT') @endif

  @if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $errors->first() }}</span></div>
  @endif

  @if ($studenten->isEmpty() && ! $stage->exists)
    <div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-bottom:12px;"><span>Er zijn geen studenten met een actieve inschrijving in een opleiding van deze organisatie.</span></div>
  @endif

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld">
      <label>Student <span class="req">*</span></label>
      @php
        // Voorvullen: na een afgekeurd formulier de getypte tekst terug, anders de
        // al gekoppelde student in hetzelfde "nummer — naam"-formaat als de lijst.
        $gekozen = $stage->student ?? $studenten->firstWhere('id', (int) old('student_id', $stage->student_id));
        $studentWaarde = old('student_zoek', $gekozen ? $gekozen->studentnummer.' — '.$gekozen->volledigeNaam() : '');
      @endphp
      @include('partials.studentkiezer', ['naam' => 'student_zoek', 'lijstId' => 'stage-studenten', 'waarde' => $studentWaarde])
    </div>
    <div class="sis-fld">
      <label>Opleiding <span class="req">*</span></label>
      @php $oid = old('opleiding_id', $stage->opleiding_id); @endphp
      <select name="opleiding_id" required>
        <option value="">— kies een opleiding —</option>
        @foreach ($opleidingen as $o)
          <option value="{{ $o->id }}" @selected((int) $oid === $o->id)>{{ $o->code }} — {{ $o->naam }}</option>
        @endforeach
      </select>
    </div>
  </div>

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld">
      <label>Stageplaats</label>
      @php $spid = old('stageplaats_id', $stage->stageplaats_id); @endphp
      <select name="stageplaats_id">
        <option value="">— geen specifieke plaats —</option>
        @foreach ($stageplaatsen as $sp)
          <option value="{{ $sp->id }}" @selected((int) $spid === $sp->id)>{{ $sp->opleiding?->code }}@if($sp->leerjaar) · jaar {{ $sp->leerjaar }}@endif ({{ $sp->bezetting() }}@if($sp->max_studenten)/{{ $sp->max_studenten }}@endif bezet)</option>
        @endforeach
      </select>
    </div>
    <div class="sis-fld">
      <label>Status <span class="req">*</span></label>
      @php $stat = old('status', $stage->status?->value ?? 'aangevraagd'); @endphp
      <select name="status" required>
        @foreach ($statussen as $s)
          <option value="{{ $s->value }}" @selected($stat === $s->value)>{{ $s->label() }}</option>
        @endforeach
      </select>
    </div>
  </div>

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld">
      <label>Stagebegeleider (opleiding)</label>
      @php $bid = old('stagebegeleider_id', $stage->stagebegeleider_id); @endphp
      <select name="stagebegeleider_id">
        <option value="">— nog niet toegewezen —</option>
        @foreach ($begeleiders as $b)
          <option value="{{ $b->id }}" @selected((int) $bid === $b->id)>{{ $b->naam }}</option>
        @endforeach
      </select>
    </div>
    <div class="sis-fld">
      <label>Werkplekbegeleider (locatie)</label>
      @php $wid = old('werkplekbegeleider_id', $stage->werkplekbegeleider_id); @endphp
      <select name="werkplekbegeleider_id">
        <option value="">— nog niet toegewezen —</option>
        @foreach ($werkplekbegeleiders as $w)
          <option value="{{ $w->id }}" @selected((int) $wid === $w->id)>{{ $w->volledigeNaam() }}@if($w->functie) ({{ $w->functie }})@endif</option>
        @endforeach
      </select>
    </div>
  </div>

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Startdatum</label><input type="date" name="startdatum" value="{{ old('startdatum', $stage->startdatum?->toDateString()) }}"></div>
    <div class="sis-fld"><label>Einddatum</label><input type="date" name="einddatum" value="{{ old('einddatum', $stage->einddatum?->toDateString()) }}"></div>
  </div>

  @if ($magBeoordeling)
    <div class="sis-card__hd" style="margin:6px 0 8px; padding:0;"><b>Beoordeling</b></div>
    <div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-bottom:10px;"><span>De beoordeling gaat over de student en wordt gelogd. Leg alleen de formeel vastgestelde uitslag vast.</span></div>
    <div class="sis-fld-row sis-fld-row--2">
      <div class="sis-fld">
        <label>Uitslag</label>
        @php $be = old('beoordeling', $stage->beoordeling); @endphp
        <select name="beoordeling">
          <option value="">— nog niet beoordeeld —</option>
          <option value="voldoende" @selected($be === 'voldoende')>Voldoende</option>
          <option value="onvoldoende" @selected($be === 'onvoldoende')>Onvoldoende</option>
        </select>
      </div>
    </div>
    <div class="sis-fld"><label>Toelichting</label><textarea name="beoordeling_toelichting" rows="3" placeholder="Optioneel">{{ old('beoordeling_toelichting', $stage->beoordeling_toelichting) }}</textarea></div>
  @endif

  <div class="sis-form__actions">
    <a class="iuasr-dash-btn" href="{{ route('relaties.show', $organisatie) }}">Annuleren</a>
    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div>
  </div>
</form>
@endsection
