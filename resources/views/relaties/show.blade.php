@extends('layouts.app')

@section('titel', $organisatie->naam)

@section('inhoud')
@php $magBeheer = auth()->user()->magRelatiebeheer(); @endphp

<div class="sis-crumb"><a href="{{ route('relaties') }}">Organisaties</a><span class="sep">›</span><b>{{ $organisatie->naam }}</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>{{ $organisatie->naam }}</h1>
    <div class="summary">
      Relatienummer <b>{{ $organisatie->relatienummer }}</b>
      · <span class="iuasr-dash-status {{ $organisatie->actief ? 's-approved' : 's-draft' }}">{{ $organisatie->actief ? 'Actief' : 'Inactief' }}</span>
    </div>
  </div>
  @if ($magBeheer)
    <div class="iuasr-dash-vhead__actions" style="display:flex; gap:8px;">
      <a class="iuasr-dash-btn" href="{{ route('relaties.edit', $organisatie) }}">Bewerken</a>
      <form method="POST" action="{{ route('relaties.status', $organisatie) }}" onsubmit="return confirm('{{ $organisatie->actief ? 'Organisatie op inactief zetten?' : 'Organisatie activeren?' }}');">
        @csrf
        <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">{{ $organisatie->actief ? 'Op inactief zetten' : 'Activeren' }}</button>
      </form>
    </div>
  @endif
</div>

<div class="sis-card" style="margin-bottom:16px;">
  <div class="sis-card__hd"><b>Organisatiegegevens</b></div>
  <div style="padding:14px 16px; display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px 24px;">
    <div><small class="sis-muted">Type</small><div>{{ $organisatie->type?->naam ?? '—' }}</div></div>
    <div><small class="sis-muted">KvK-nummer</small><div>{{ $organisatie->kvk_nummer ?? '—' }}</div></div>
    <div><small class="sis-muted">BRIN-nummer</small><div>{{ $organisatie->brin_nummer ?? '—' }}</div></div>
    <div><small class="sis-muted">Adres</small><div>{{ $organisatie->adres ?? '—' }}</div></div>
    <div><small class="sis-muted">Postcode / plaats</small><div>{{ trim(($organisatie->postcode ?? '').' '.($organisatie->plaats ?? '')) ?: '—' }}</div></div>
    <div><small class="sis-muted">Provincie</small><div>{{ $organisatie->provincie ?? '—' }}</div></div>
    <div><small class="sis-muted">Telefoon</small><div>{{ $organisatie->telefoon ?? '—' }}</div></div>
    <div><small class="sis-muted">E-mail</small><div>{{ $organisatie->email ?? '—' }}</div></div>
    <div><small class="sis-muted">Website</small><div>@if($organisatie->website)<a href="{{ $organisatie->website }}" target="_blank" rel="noopener">{{ $organisatie->website }}</a>@else — @endif</div></div>
    <div><small class="sis-muted">Opleiding(en)</small><div>{{ $organisatie->opleidingen->pluck('naam')->implode(', ') ?: '—' }}</div></div>
  </div>
  @if ($organisatie->opmerkingen)
    <div style="padding:0 16px 14px;"><small class="sis-muted">Opmerkingen</small><div>{{ $organisatie->opmerkingen }}</div></div>
  @endif
</div>

{{-- 360°-relatiekaart: de overige panelen (contactpersonen, contactmomenten,
     stageplaatsen & stages, documenten, overeenkomsten, taken, agenda, historie)
     worden in de volgende fasen van de module toegevoegd. --}}
<div class="sis-card">
  <div class="sis-card__hd"><b>Relatiekaart (360°)</b></div>
  <div style="padding:14px 16px;">
    <p class="sis-muted" style="margin:0;">De onderdelen contactpersonen, contactmomenten, stageplaatsen &amp; stages, documenten, overeenkomsten, taken, agenda en historie verschijnen hier zodra de bijbehorende fasen (B t/m G) van de module Relatiebeheer &amp; Stage zijn opgeleverd.</p>
  </div>
</div>
@endsection
