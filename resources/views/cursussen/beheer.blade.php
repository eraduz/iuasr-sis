@extends('layouts.app')

@section('titel', 'Cursusbeheer')

@php $euro = fn ($b) => '€ '.number_format((float) $b, 2, ',', '.'); @endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('cursussen.dashboard') }}">Cursussen</a><span class="sep">›</span><b>Cursusbeheer</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Cursusbeheer</h1>
    <div class="summary">{{ $cursussen->count() }} cursussen</div>
  </div>
  <div class="iuasr-dash-vhead__actions">
    <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('cursussen.create') }}">Nieuwe cursus</a>
  </div>
</div>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Code</th><th>Cursus</th><th style="text-align:right;">Cursusgeld</th><th>Looptijd</th><th style="text-align:center;">Inschrijvingen</th><th style="text-align:center;">Status</th><th class="row-act"></th></tr></thead>
    <tbody>
      @foreach ($cursussen as $c)
        <tr>
          <td class="tnum">{{ $c->code }}</td>
          <td class="nm">{{ $c->naam }}</td>
          <td class="tnum" style="text-align:right;">{{ $euro($c->cursusgeld) }}</td>
          <td class="dt">{{ $c->startdatum?->format('d-m-Y') ?? '—' }} @if($c->einddatum) t/m {{ $c->einddatum->format('d-m-Y') }} @endif</td>
          <td class="tnum" style="text-align:center;">{{ $c->inschrijvingen_count }}</td>
          <td style="text-align:center;"><span class="iuasr-dash-status {{ $c->actief ? 's-approved' : 's-draft' }}">{{ $c->actief ? 'Actief' : 'Inactief' }}</span></td>
          <td class="row-act" style="white-space:nowrap;">
            <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('cursussen.edit', $c) }}">Bewerken</a>
            <form method="POST" action="{{ route('cursussen.destroy', $c) }}" onsubmit="return confirm('Cursus verwijderen?');" style="display:inline;">
              @csrf @method('DELETE')
              <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">Verwijderen</button>
            </form>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
<p class="sis-tblnote">Een cursus met inschrijvingen kan niet worden verwijderd (historie); zet haar in dat geval op inactief. Nieuwe cursussen en tarieven voegt u hier gewoon toe.</p>
@endsection
