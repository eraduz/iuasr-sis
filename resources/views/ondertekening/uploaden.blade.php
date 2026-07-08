@extends('layouts.app')

@section('titel', 'Document ondertekenen')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ route('ondertekening') }}">Ondertekende documenten</a><span class="sep">›</span><b>Ondertekenen</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Document ondertekenen</h1>
    <div class="summary">Upload een eigen PDF; het wordt digitaal gewaarmerkt met verificatiecode en echtheidskenmerk (SHA-256)</div>
  </div>
</div>

<div class="sis-grid-2">
  <form method="POST" action="{{ route('ondertekening.onderteken') }}" enctype="multipart/form-data" class="sis-card sis-form">
    @csrf
    <fieldset class="sis-fieldset">
      <legend>Te ondertekenen document</legend>
      <div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-bottom:14px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg>
        <span>Uw origineel wordt <b>niet gewijzigd</b>. U krijgt <b>twee bestanden</b> terug: uw origineel én een apart digitaal <b>waarmerk-certificaat</b>.</span>
      </div>
      @if ($errors->any())
        <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $errors->first() }}</span></div>
      @endif
      <div class="sis-fld"><label>Titel <span class="req">*</span></label><input type="text" name="titel" value="{{ old('titel') }}" required placeholder="bv. Toelatingsbrief, Bevestiging stage"></div>
      <div class="sis-fld"><label>Verstrekt aan (persoon / organisatie) <span class="req">*</span></label><input type="text" name="ontvanger" value="{{ old('ontvanger') }}" required placeholder="bv. Gemeente Rotterdam"></div>
      <div class="sis-fld"><label>PDF-bestand <span class="req">*</span></label><input type="file" name="bestand" accept="application/pdf" required><div class="help">Alleen PDF, max. 15 MB. Het originele bestand blijft ongewijzigd.</div></div>
      <div class="sis-form__actions"><a class="iuasr-dash-btn" href="{{ route('ondertekening') }}">Annuleren</a><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Ondertekenen</button></div></div>
    </fieldset>
  </form>

  <div class="sis-card">
    <div class="sis-card__hd"><h3>Hoe werkt het waarmerken?</h3></div>
    <ol style="margin:0;padding-left:18px;font-size:13px;line-height:1.7;color:var(--blackText);">
      <li>Het originele PDF-bestand wordt veilig gearchiveerd (private opslag).</li>
      <li>Er wordt een uniek <b>echtheidskenmerk (SHA-256)</b> en een <b>verificatiecode</b> berekend.</li>
      <li>U ontvangt een <b>digitaal waarmerk-certificaat</b> (PDF) om samen met het document te versturen.</li>
      <li>De ontvanger controleert de echtheid op de publieke verificatiepagina — en kan de PDF uploaden om te bevestigen dat deze ongewijzigd is.</li>
      <li>Wie ondertekent, wanneer en aan wie verstrekt wordt, wordt <b>gelogd</b>.</li>
    </ol>
    <div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-top:14px;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg>
      <span>Alleen Studentenzaken, opleidingsdirecteuren/bestuur (Directie) en Beheer mogen deze module gebruiken.</span>
    </div>
  </div>
</div>
@endsection
