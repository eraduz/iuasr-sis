@extends('layouts.app')

@section('titel', 'Zoeken')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('hr.dashboard') }}">HR</a><span class="sep">›</span><b>Zoeken</b></div>

<div class="iuasr-dash-vhead"><div><h1>Zoeken</h1><div class="summary">Zoek over medewerkers en afdelingen</div></div></div>

<form method="GET" action="{{ route('hr.zoeken') }}" class="sis-toolbar" style="margin-bottom:16px;">
  <input type="search" name="q" value="{{ $q }}" placeholder="Naam, personeelsnummer, e-mail of afdeling (min. 2 tekens)" autofocus style="min-width:340px;">
  <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Zoeken</button>
</form>

@if (mb_strlen($q) < 2)
  <p class="sis-muted">Voer minimaal twee tekens in.</p>
@else
  <div class="sis-card" style="margin-bottom:16px;">
    <div class="sis-card__hd"><b>Medewerkers ({{ $medewerkers->count() }})</b></div>
    @if ($medewerkers->isEmpty())
      <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">Geen medewerkers gevonden.</p></div>
    @else
      <table class="iuasr-dash-tbl">
        <thead><tr><th>Personeelsnr.</th><th>Naam</th><th>Functie</th><th>Afdeling</th><th>Status</th></tr></thead>
        <tbody>
          @foreach ($medewerkers as $m)
            <tr>
              <td class="tnum">{{ $m->personeelsnummer }}</td>
              <td class="nm"><a href="{{ route('medewerkers.show', $m) }}">{{ $m->volledigeNaam() }}</a></td>
              <td>{{ $m->functie?->naam ?? '—' }}</td>
              <td>{{ $m->afdeling?->naam ?? '—' }}</td>
              <td>@if ($m->status)<span class="iuasr-dash-status {{ $m->status->badge() }}">{{ $m->status->label() }}</span>@else — @endif</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

  @if (! auth()->user()->isHrTeamBeperkt())
    <div class="sis-card">
      <div class="sis-card__hd"><b>Afdelingen ({{ $afdelingen->count() }})</b></div>
      @if ($afdelingen->isEmpty())
        <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">Geen afdelingen gevonden.</p></div>
      @else
        <table class="iuasr-dash-tbl">
          <thead><tr><th>Code</th><th>Afdeling</th><th>Manager</th><th style="text-align:right;">Medewerkers</th></tr></thead>
          <tbody>
            @foreach ($afdelingen as $a)
              <tr>
                <td class="tnum">{{ $a->code }}</td>
                <td class="nm"><a href="{{ route('hr.organisatie') }}">{{ $a->naam }}</a></td>
                <td>{{ $a->manager?->volledigeNaam() ?? '—' }}</td>
                <td class="tnum" style="text-align:right;">{{ $a->medewerkers_actief }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>
  @endif
@endif
@endsection
