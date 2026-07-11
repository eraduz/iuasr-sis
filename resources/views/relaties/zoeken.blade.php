@extends('layouts.app')

@section('titel', 'Zoeken')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('relatiebeheer.dashboard') }}">Relatiebeheer</a><span class="sep">›</span><b>Zoeken</b></div>

<div class="iuasr-dash-vhead"><div><h1>Zoeken</h1><div class="summary">Zoek over organisaties, contactpersonen en stages</div></div></div>

<form method="GET" action="{{ route('relatiebeheer.zoeken') }}" class="sis-toolbar" style="margin-bottom:16px;">
  <input type="search" name="q" value="{{ $q }}" placeholder="Zoekterm (min. 2 tekens)" autofocus style="min-width:320px;">
  <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Zoeken</button>
</form>

@if (mb_strlen($q) < 2)
  <p class="sis-muted">Voer minimaal twee tekens in.</p>
@else
  <div class="sis-card" style="margin-bottom:16px;">
    <div class="sis-card__hd"><b>Organisaties ({{ $organisaties->count() }})</b></div>
    @if ($organisaties->isEmpty())
      <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">Geen organisaties gevonden.</p></div>
    @else
      <table class="iuasr-dash-tbl">
        <thead><tr><th>Relatienr.</th><th>Naam</th><th>Plaats</th></tr></thead>
        <tbody>
          @foreach ($organisaties as $org)
            <tr><td class="tnum">{{ $org->relatienummer }}</td><td class="nm"><a href="{{ route('relaties.show', $org) }}">{{ $org->naam }}</a></td><td>{{ $org->plaats ?? '—' }}</td></tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

  <div class="sis-card" style="margin-bottom:16px;">
    <div class="sis-card__hd"><b>Contactpersonen ({{ $contactpersonen->count() }})</b></div>
    @if ($contactpersonen->isEmpty())
      <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">Geen contactpersonen gevonden.</p></div>
    @else
      <table class="iuasr-dash-tbl">
        <thead><tr><th>Naam</th><th>Functie</th><th>Organisatie</th><th>E-mail</th></tr></thead>
        <tbody>
          @foreach ($contactpersonen as $cp)
            <tr><td class="nm">{{ $cp->volledigeNaam() }}</td><td>{{ $cp->functie ?? '—' }}</td><td><a href="{{ route('relaties.show', $cp->organisatie) }}#contactpersonen">{{ $cp->organisatie?->naam }}</a></td><td>{{ $cp->email ?? '—' }}</td></tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

  <div class="sis-card">
    <div class="sis-card__hd"><b>Stages ({{ $stages->count() }})</b></div>
    @if ($stages->isEmpty())
      <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">Geen stages gevonden.</p></div>
    @else
      <table class="iuasr-dash-tbl">
        <thead><tr><th>Stagenr.</th><th>Student</th><th>Organisatie</th></tr></thead>
        <tbody>
          @foreach ($stages as $stage)
            <tr><td class="tnum">{{ $stage->stagenummer }}</td><td class="nm">{{ $stage->student?->volledigeNaam() }}</td><td><a href="{{ route('relaties.show', $stage->organisatie) }}#stages">{{ $stage->organisatie?->naam }}</a></td></tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>
@endif
@endsection
