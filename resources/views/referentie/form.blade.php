@extends('layouts.app')

@section('titel', ($rij ? 'Bewerken' : 'Toevoegen').' — '.$conf['enkel'])

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ route('opzoektabellen.tabel', $tabel) }}">{{ $conf['meer'] }}</a><span class="sep">›</span><b>{{ $rij ? 'Bewerken' : 'Toevoegen' }}</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>{{ $rij ? $conf['enkel'].' bewerken' : 'Nieuwe '.strtolower($conf['enkel']) }}</h1>
  </div>
</div>

@if ($errors->any())
  <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:16px;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>Controleer de invoer: {{ $errors->first() }}</span></div>
@endif

<form method="POST" action="{{ $rij ? route('opzoektabellen.update', [$tabel, $rij->id]) : route('opzoektabellen.store', $tabel) }}" class="sis-card sis-form" style="max-width:640px;">
  @csrf
  @if ($rij) @method('PUT') @endif

  @foreach ($conf['velden'] as $naam => $veld)
    @php $waarde = old($naam, $rij?->{$naam}); @endphp
    @if (($veld['type'] ?? 'text') === 'checkbox')
      <div class="sis-fld">
        <label class="sis-check-inline"><input type="checkbox" name="{{ $naam }}" value="1" @checked((bool) $waarde)> {{ $veld['label'] }}</label>
      </div>
    @else
      <div class="sis-fld">
        <label>{{ $veld['label'] }}@if(str_contains($veld['rules'] ?? '', 'required'))<span class="req">*</span>@endif</label>
        @switch($veld['type'] ?? 'text')
          @case('select')
            <select name="{{ $naam }}">
              @foreach ($veld['opties'] as $opt)
                <option value="{{ $opt }}" @selected($waarde === $opt)>{{ ucfirst($opt) }}</option>
              @endforeach
            </select>
            @break
          @case('belongsto')
            <select name="{{ $naam }}">
              @if (! empty($veld['leeg']))<option value="">{{ $veld['leeg'] }}</option>@endif
              @foreach ($veld['model']::orderBy($veld['toon'])->get() as $opt)
                <option value="{{ $opt->id }}" @selected((int) $waarde === $opt->id)>{{ $opt->{$veld['toon']} }}</option>
              @endforeach
            </select>
            @break
          @case('number')
            <input type="number" name="{{ $naam }}" value="{{ $waarde }}">
            @break
          @case('date')
            <input type="date" name="{{ $naam }}" value="{{ $waarde instanceof \Carbon\Carbon ? $waarde->format('Y-m-d') : $waarde }}">
            @break
          @default
            <input type="text" name="{{ $naam }}" value="{{ $waarde }}">
        @endswitch
        @if (! empty($veld['hint']))<div class="help">{{ $veld['hint'] }}</div>@endif
      </div>
    @endif
  @endforeach

  <div class="sis-form__actions">
    <a class="iuasr-dash-btn" href="{{ route('opzoektabellen.tabel', $tabel) }}">Annuleren</a>
    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div>
  </div>
</form>
@endsection
