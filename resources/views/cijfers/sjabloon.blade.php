@extends('layouts.app')

@section('titel', 'E-mailsjabloon cijferlijst')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('cijfers-mailen') }}">Cijfers mailen</a><span class="sep">›</span><b>E-mailsjabloon</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>E-mailsjabloon cijferlijst</h1>
    <div class="summary">De standaardtekst die de student bij de cijferlijst-mail ontvangt</div>
  </div>
</div>

@if (session('status'))
  <div class="iuasr-dash-alert iuasr-dash-alert--ok" style="margin-bottom:14px;"><span>{{ session('status') }}</span></div>
@endif

<form method="POST" action="{{ route('cijferlijst-sjabloon.update') }}" class="sis-card sis-form" style="max-width:840px;">
  @csrf
  @if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><span>{{ $errors->first() }}</span></div>
  @endif

  <div class="sis-fld">
    <label>Onderwerp <span class="req">*</span></label>
    <input type="text" name="onderwerp" value="{{ old('onderwerp', $sjabloon->onderwerp) }}" maxlength="255" required>
  </div>

  <div class="sis-fld">
    <label>Tekst van de e-mail <span class="req">*</span></label>
    <textarea name="inhoud" rows="15" maxlength="5000" required>{{ old('inhoud', $sjabloon->inhoud) }}</textarea>
  </div>

  <p class="sis-tblnote">
    Beschikbare variabelen (worden per student ingevuld):
    @foreach (\App\Models\Cijferlijstsjabloon::VARIABELEN as $var)<code>&#123;&#123;{{ $var }}&#125;&#125;</code>@if(! $loop->last), @endif @endforeach.
    De <b>ondertekende cijferlijst (PDF)</b> en de vaste <b>AVG-voettekst</b> worden automatisch toegevoegd — die hoeft u hier niet op te nemen.
  </p>

  <div class="sis-form__actions">
    <a class="iuasr-dash-btn" href="{{ route('cijfers-mailen') }}">Terug</a>
    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div>
  </div>
</form>
@endsection
