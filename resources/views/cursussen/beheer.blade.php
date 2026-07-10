@extends('layouts.app')

@section('titel', 'Cursusbeheer')

@php $euro = fn ($b) => '€ '.number_format((float) $b, 2, ',', '.'); @endphp

@section('inhoud')
@php $magBeheerder = auth()->user()->rolIs('beheerder'); @endphp
<div class="sis-crumb"><a href="{{ route('cursussen.dashboard') }}">Cursussen</a><span class="sep">›</span><b>Cursusbeheer</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Cursusbeheer</h1>
    <div class="summary">{{ $cursussen->count() }} {{ $cursussen->count() === 1 ? 'cursus' : 'cursussen' }}@unless($magBeheerder) onder uw beheer@endunless</div>
  </div>
  @if ($magBeheerder)
    <div class="iuasr-dash-vhead__actions">
      <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('cursussen.create') }}">Nieuwe cursus</a>
    </div>
  @endif
</div>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Code</th><th>Cursus</th>@if($magBeheerder)<th>Directeur</th>@endif<th style="text-align:right;">Cursusgeld</th><th>Looptijd</th><th style="text-align:center;">Inschrijvingen</th><th style="text-align:center;">Status</th><th class="row-act"></th></tr></thead>
    <tbody>
      @foreach ($cursussen as $c)
        <tr>
          <td class="tnum">{{ $c->code }}</td>
          <td class="nm">{{ $c->naam }}</td>
          @if($magBeheerder)<td>{{ $c->directeur?->naam ?? '—' }}</td>@endif
          <td class="tnum" style="text-align:right;">{{ $euro($c->cursusgeld) }}</td>
          <td class="dt">{{ $c->startdatum?->format('d-m-Y') ?? '—' }} @if($c->einddatum) t/m {{ $c->einddatum->format('d-m-Y') }} @endif</td>
          <td class="tnum" style="text-align:center;">{{ $c->inschrijvingen_count }}</td>
          <td style="text-align:center;"><span class="iuasr-dash-status {{ $c->actief ? 's-approved' : 's-draft' }}">{{ $c->actief ? 'Actief' : 'Inactief' }}</span></td>
          <td class="row-act" style="white-space:nowrap;">
            <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('cursussen.edit', $c) }}">Bewerken</a>
            @if ($magBeheerder)
              <form method="POST" action="{{ route('cursussen.destroy', $c) }}" onsubmit="return confirm('Cursus verwijderen?');" style="display:inline;">
                @csrf @method('DELETE')
                <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">Verwijderen</button>
              </form>
            @endif
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
<p class="sis-tblnote">Een cursus met inschrijvingen kan niet worden verwijderd (historie); zet haar in dat geval op inactief.@if($magBeheerder) Nieuwe cursussen, tarieven en de directeurtoewijzing regelt u hier.@else Voor het aanmaken/verwijderen van cursussen of het wijzigen van de directeur kunt u terecht bij de Beheerder.@endif</p>
@endsection
