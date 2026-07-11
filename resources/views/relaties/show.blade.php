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

{{-- 360°-relatiekaart — Contactpersonen (Fase B). --}}
<div class="sis-card" id="contactpersonen" style="margin-bottom:16px;">
  <div class="sis-card__hd" style="display:flex; align-items:center; justify-content:space-between;">
    <b>Contactpersonen ({{ $organisatie->contactpersonen->count() }})</b>
    @if ($magBeheer)
      <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('contactpersonen.create', $organisatie) }}">Contactpersoon toevoegen</a>
    @endif
  </div>
  @if ($organisatie->contactpersonen->isEmpty())
    <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">Nog geen contactpersonen vastgelegd.</p></div>
  @else
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Naam</th><th>Functie</th><th>E-mail</th><th>Telefoon</th><th>Voorkeur</th><th style="text-align:center;">Status</th>@if($magBeheer)<th class="row-act"></th>@endif</tr></thead>
      <tbody>
        @foreach ($organisatie->contactpersonen as $cp)
          <tr @if(! $cp->actief) style="opacity:.55;" @endif>
            <td class="nm">{{ $cp->volledigeNaam() }}</td>
            <td>{{ $cp->functie ?? '—' }}@if($cp->afdeling)<br><small class="sis-muted">{{ $cp->afdeling }}</small>@endif</td>
            <td>@if($cp->email)<a href="mailto:{{ $cp->email }}">{{ $cp->email }}</a>@else — @endif</td>
            <td>{{ $cp->mobiel ?? $cp->telefoon ?? '—' }}</td>
            <td>{{ $cp->voorkeur_communicatie ? ucfirst($cp->voorkeur_communicatie) : '—' }}</td>
            <td style="text-align:center;"><span class="iuasr-dash-status {{ $cp->actief ? 's-approved' : 's-draft' }}">{{ $cp->actief ? 'Actief' : 'Inactief' }}</span></td>
            @if($magBeheer)
              <td class="row-act" style="white-space:nowrap;">
                <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('contactpersonen.edit', $cp) }}">Bewerken</a>
                <form method="POST" action="{{ route('contactpersonen.status', $cp) }}" style="display:inline;">
                  @csrf
                  <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">{{ $cp->actief ? 'Inactiveren' : 'Activeren' }}</button>
                </form>
              </td>
            @endif
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>

{{-- Contactmomenten (Fase C). --}}
<div class="sis-card" id="contactmomenten" style="margin-bottom:16px;">
  <div class="sis-card__hd" style="display:flex; align-items:center; justify-content:space-between;">
    <b>Contactmomenten ({{ $organisatie->contactmomenten->count() }})</b>
    @if ($magBeheer)
      <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('contactmomenten.create', $organisatie) }}">Contactmoment vastleggen</a>
    @endif
  </div>
  @if ($organisatie->contactmomenten->isEmpty())
    <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">Nog geen contactmomenten vastgelegd.</p></div>
  @else
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Datum</th><th>Type</th><th>Onderwerp</th><th>Contactpersoon</th><th>Door</th><th>Vervolg</th>@if($magBeheer)<th class="row-act"></th>@endif</tr></thead>
      <tbody>
        @foreach ($organisatie->contactmomenten as $cm)
          <tr>
            <td class="dt">{{ $cm->datum?->format('d-m-Y') }}@if($cm->tijd)<br><small class="sis-muted">{{ \Illuminate\Support\Str::of($cm->tijd)->substr(0,5) }}</small>@endif</td>
            <td>{{ $cm->type?->naam ?? '—' }}</td>
            <td class="nm">{{ $cm->onderwerp }}@if($cm->samenvatting)<br><small class="sis-muted">{{ \Illuminate\Support\Str::limit($cm->samenvatting, 90) }}</small>@endif</td>
            <td>{{ $cm->contactpersoon?->volledigeNaam() ?? '—' }}</td>
            <td>{{ $cm->medewerker?->naam ?? '—' }}</td>
            <td class="dt">{{ $cm->vervolgdatum?->format('d-m-Y') ?? '—' }}</td>
            @if($magBeheer)<td class="row-act"><a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('contactmomenten.edit', $cm) }}">Bewerken</a></td>@endif
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>

{{-- Notities (Fase C). --}}
<div class="sis-card" id="notities" style="margin-bottom:16px;">
  <div class="sis-card__hd"><b>Notities ({{ $organisatie->notities->count() }})</b></div>
  <div style="padding:14px 16px;">
    @if ($magBeheer)
      <form method="POST" action="{{ route('relaties.notities.store', $organisatie) }}" style="margin-bottom:14px;">
        @csrf
        <div class="sis-fld-row sis-fld-row--2">
          <div class="sis-fld"><label>Categorie</label><input type="text" name="categorie" maxlength="255" placeholder="Optioneel"></div>
          <div class="sis-fld"><label>Tags</label><input type="text" name="tags" maxlength="255" placeholder="Optioneel, bv. stage, bezoek"></div>
        </div>
        <div class="sis-fld"><label>Notitie <span class="req">*</span></label><textarea name="tekst" rows="2" required></textarea></div>
        <div style="text-align:right;"><button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Notitie toevoegen</button></div>
      </form>
    @endif
    @forelse ($organisatie->notities as $n)
      <div style="border-top:1px solid var(--border,#e5e5e5); padding:10px 0;">
        <div style="display:flex; justify-content:space-between; gap:12px;">
          <div>
            @if($n->categorie)<span class="sis-pill-soft">{{ $n->categorie }}</span> @endif
            <span>{{ $n->tekst }}</span>
            @if($n->tags)<div><small class="sis-muted">Tags: {{ $n->tags }}</small></div>@endif
          </div>
          <div style="white-space:nowrap; text-align:right;">
            <small class="sis-muted">{{ $n->created_at?->format('d-m-Y') }}<br>{{ $n->auteur?->naam }}</small>
            @if($magBeheer)
              <form method="POST" action="{{ route('relaties.notities.destroy', $n) }}" onsubmit="return confirm('Notitie verwijderen?');" style="display:inline;">
                @csrf @method('DELETE')
                <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">×</button>
              </form>
            @endif
          </div>
        </div>
      </div>
    @empty
      <p class="sis-muted" style="margin:0;">Nog geen notities.</p>
    @endforelse
  </div>
</div>

{{-- Historie / tijdlijn (Fase C) — afgeleid uit contactmomenten, notities en de audit-log. --}}
<div class="sis-card" id="tijdlijn" style="margin-bottom:16px;">
  <div class="sis-card__hd"><b>Historie / tijdlijn</b></div>
  <div style="padding:14px 16px;">
    @forelse ($tijdlijn as $item)
      <div style="display:flex; gap:12px; padding:8px 0; border-top:1px solid var(--border,#e5e5e5);">
        <div style="min-width:92px;"><small class="sis-muted">{{ optional($item['moment'])->format('d-m-Y') }}</small></div>
        <div style="flex:1;">
          <span class="sis-pill-soft">{{ $item['label'] }}</span>
          <b style="margin-left:6px;">{{ $item['titel'] }}</b>
          @if($item['detail'])<div><small class="sis-muted">{{ \Illuminate\Support\Str::limit($item['detail'], 120) }}</small></div>@endif
        </div>
        <div style="white-space:nowrap;"><small class="sis-muted">{{ $item['door'] }}</small></div>
      </div>
    @empty
      <p class="sis-muted" style="margin:0;">Nog geen gebeurtenissen.</p>
    @endforelse
  </div>
</div>

{{-- De overige panelen (stageplaatsen & stages, documenten, overeenkomsten,
     taken, agenda) volgen in de fasen D t/m G. --}}
<div class="sis-card">
  <div class="sis-card__hd"><b>Overige onderdelen</b></div>
  <div style="padding:14px 16px;">
    <p class="sis-muted" style="margin:0;">Stageplaatsen &amp; stages, documenten, overeenkomsten, taken en agenda verschijnen hier zodra de bijbehorende fasen (D t/m G) van de module Relatiebeheer &amp; Stage zijn opgeleverd.</p>
  </div>
</div>
@endsection
