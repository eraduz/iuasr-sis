@extends('layouts.app')

@section('titel', 'Vakken aanpassen')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ route('studenten.show', $inschrijving->student_id) }}">{{ $inschrijving->student?->studentnummer }}</a><span class="sep">›</span><b>Vakken aanpassen</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Vaktoewijzing aanpassen</h1>
    <div class="summary">{{ $inschrijving->student?->volledigeNaam() }} · {{ $inschrijving->opleiding?->naam }} · {{ $inschrijving->periode?->naam }} (jaar {{ $inschrijving->leerjaar }})</div>
  </div>
</div>

<div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-bottom:16px;">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
  <span>Vink de vakken aan die deze student in dit studiejaar volgt. Automatisch toegewezen jaarvakken staan al aangevinkt; u kunt bijvoorbeeld openstaande vakken uit een ander jaar toevoegen.</span>
</div>

<form method="POST" action="{{ route('inschrijving.vakken.update', $inschrijving) }}">
  @csrf @method('PUT')
  @foreach ($structuur as $leerjaar => $blokken)
    <div class="sis-card" style="margin-bottom:12px;">
      <div class="sis-card__hd"><h3>Jaar {{ $leerjaar }}</h3></div>
      @foreach ($blokken as $blok => $vakken)
        <div style="margin-bottom:10px;">
          <div class="sis-fieldset"><legend style="padding-bottom:6px;">{{ $blok ? 'Blok '.$blok : 'Hele studiejaar' }}</legend></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 16px;">
            @foreach ($vakken as $vak)
              <label class="iuasr-dash-check">
                <input type="checkbox" name="vak_ids[]" value="{{ $vak->id }}" @checked(in_array($vak->id, $toegewezen))>
                <span>{{ $vak->naam }} <small style="color:var(--blackAltText);">· {{ $vak->code }} · {{ \App\Support\Ec::toon($vak->ec) }} EC</small></span>
              </label>
            @endforeach
          </div>
        </div>
      @endforeach
    </div>
  @endforeach

  <div class="sis-card">
    <div class="sis-form__actions" style="margin:0;padding:0;border:0;">
      <a class="iuasr-dash-btn" href="{{ route('studenten.show', $inschrijving->student_id) }}">Annuleren</a>
      <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Vaktoewijzing opslaan</button></div>
    </div>
  </div>
</form>
@endsection
