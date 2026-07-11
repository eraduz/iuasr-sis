@extends('layouts.app')

@php $titel = $overeenkomst->exists ? 'Overeenkomst bewerken' : 'Nieuwe overeenkomst'; @endphp

@section('titel', $titel)

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('relaties') }}">Organisaties</a><span class="sep">›</span><a href="{{ route('relaties.show', $organisatie) }}">{{ $organisatie->naam }}</a><span class="sep">›</span><b>{{ $titel }}</b></div>

<div class="iuasr-dash-vhead"><div><h1>{{ $titel }}</h1><div class="summary">Bij <b>{{ $organisatie->naam }}</b> ({{ $organisatie->relatienummer }})</div></div></div>

<form method="POST" action="{{ $overeenkomst->exists ? route('overeenkomsten.update', $overeenkomst) : route('overeenkomsten.store', $organisatie) }}" enctype="multipart/form-data" class="sis-card sis-form" style="max-width:760px;">
  @csrf
  @if ($overeenkomst->exists) @method('PUT') @endif

  @if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $errors->first() }}</span></div>
  @endif

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld">
      <label>Type <span class="req">*</span></label>
      @php $ty = old('type', $overeenkomst->type?->value); @endphp
      <select name="type" required><option value="">— kies een type —</option>@foreach ($types as $w => $l)<option value="{{ $w }}" @selected($ty===$w)>{{ $l }}</option>@endforeach</select>
    </div>
    <div class="sis-fld">
      <label>Status</label>
      @php $st = old('status', $overeenkomst->status?->value ?? 'concept'); @endphp
      <select name="status">@foreach ($statussen as $w => $l)<option value="{{ $w }}" @selected($st===$w)>{{ $l }}</option>@endforeach</select>
    </div>
  </div>

  <div class="sis-fld"><label>Titel</label><input type="text" name="titel" value="{{ old('titel', $overeenkomst->titel) }}" maxlength="255" placeholder="Optioneel"></div>

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Startdatum</label><input type="date" name="startdatum" value="{{ old('startdatum', $overeenkomst->startdatum?->toDateString()) }}"></div>
    <div class="sis-fld"><label>Verloopdatum</label><input type="date" name="verloopdatum" value="{{ old('verloopdatum', $overeenkomst->verloopdatum?->toDateString()) }}"><small class="sis-muted">Stuurt de signalering ‘contracten die verlopen’.</small></div>
  </div>

  <div class="sis-fld"><label>Opmerking</label><textarea name="opmerking" rows="2">{{ old('opmerking', $overeenkomst->opmerking) }}</textarea></div>

  <div class="sis-fld">
    <label>Getekende PDF (laten waarmerken)</label>
    <input type="file" name="bestand" accept="application/pdf">
    @if ($overeenkomst->exists && $overeenkomst->ondertekend_document_id)
      <small class="sis-muted">Er is al een gewaarmerkt document gekoppeld (<a href="{{ route('overeenkomsten.download', $overeenkomst) }}">downloaden</a>). Een nieuw bestand vervangt het en zet de status op ‘Getekend’.</small>
    @else
      <small class="sis-muted">Optioneel: upload de getekende PDF. Deze krijgt een SHA-256-echtheidskenmerk en verificatiecode via de ondertekenmodule; de status wordt dan ‘Getekend’.</small>
    @endif
  </div>

  <div class="sis-form__actions">
    <a class="iuasr-dash-btn" href="{{ route('relaties.show', $organisatie) }}">Annuleren</a>
    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div>
  </div>
</form>
@endsection
