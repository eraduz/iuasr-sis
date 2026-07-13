@extends('layouts.app')

@section('titel', 'E-mailsjablonen bibliotheek')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('bibliotheek.dashboard') }}">Bibliotheek</a><span class="sep">›</span><b>E-mailsjablonen</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>E-mailsjablonen</h1>
    <div class="summary">De vijf berichten die de bibliotheek automatisch verstuurt. Alleen de Beheerder kan ze aanpassen.</div>
  </div>
</div>

<div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-bottom:16px;">
  <span>Beschikbare variabelen: @foreach ($variabelen as $v)<code>&#123;&#123;{{ $v }}&#125;&#125;</code>@if (! $loop->last), @endif @endforeach — ze worden bij het versturen vervangen door de werkelijke waarden. Elk bericht krijgt automatisch een CC naar {{ config('sis.mail.cc.bibliotheek') }}.</span>
</div>

@foreach ($sjablonen as $sjabloon)
  <form method="POST" action="{{ route('bibliotheek.sjablonen.update', $sjabloon) }}" class="sis-card sis-form" style="margin-bottom:16px; max-width:900px;">
    @csrf @method('PUT')

    <h3>{{ $sjabloon->soort->label() }}</h3>

    <div class="sis-fld">
      <label>Onderwerp <span class="req">*</span></label>
      <input type="text" name="onderwerp" value="{{ old('onderwerp', $sjabloon->onderwerp) }}" maxlength="255" required>
    </div>

    <div class="sis-fld">
      <label>Inhoud <span class="req">*</span></label>
      <textarea name="inhoud" rows="10" maxlength="5000" required>{{ old('inhoud', $sjabloon->inhoud) }}</textarea>
    </div>

    <div class="sis-fld">
      <label class="sis-check-inline"><input type="checkbox" name="actief" value="1" @checked($sjabloon->actief)> Actief</label>
      <small class="sis-muted">Staat een sjabloon uit, dan wordt dat bericht niet verstuurd; dat wordt wel in het e-maillogboek vastgelegd.</small>
    </div>

    <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div></div>
  </form>
@endforeach
@endsection
