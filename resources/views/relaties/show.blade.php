@extends('layouts.app')

@section('titel', $organisatie->naam)

@section('inhoud')
@php
    $magBeheer = auth()->user()->magRelatiebeheer();
    $magStage = $organisatie->stagesBeheerbaarVoor(auth()->user());
@endphp

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

{{-- Stageplaatsen (Fase D) — het aanbod/capaciteit per opleiding. --}}
<div class="sis-card" id="stageplaatsen" style="margin-bottom:16px;">
  <div class="sis-card__hd" style="display:flex; align-items:center; justify-content:space-between;">
    <b>Stageplaatsen ({{ $organisatie->stageplaatsen->count() }})</b>
    @if ($magStage)
      <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('stageplaatsen.create', $organisatie) }}">Stageplaats toevoegen</a>
    @endif
  </div>
  @if ($organisatie->stageplaatsen->isEmpty())
    <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">Nog geen stageplaatsen vastgelegd.</p></div>
  @else
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Opleiding</th><th>Leerjaar</th><th>Studiejaar</th><th>Werkdagen</th><th style="text-align:center;">Bezetting</th><th style="text-align:center;">Status</th>@if($magStage)<th class="row-act"></th>@endif</tr></thead>
      <tbody>
        @foreach ($organisatie->stageplaatsen as $sp)
          <tr @if(! $sp->actief) style="opacity:.55;" @endif>
            <td class="nm">{{ $sp->opleiding?->code ?? '—' }}@if($sp->specialisaties)<br><small class="sis-muted">{{ $sp->specialisaties }}</small>@endif</td>
            <td>{{ $sp->leerjaar ?? '—' }}</td>
            <td>{{ $sp->periode?->code ?? '—' }}</td>
            <td>{{ $sp->werkdagen ?? '—' }}</td>
            <td style="text-align:center;">{{ $sp->bezetting() }}@if($sp->max_studenten) / {{ $sp->max_studenten }}@endif</td>
            <td style="text-align:center;"><span class="iuasr-dash-status {{ $sp->actief ? 's-approved' : 's-draft' }}">{{ $sp->actief ? 'Actief' : 'Inactief' }}</span></td>
            @if($magStage)
              <td class="row-act" style="white-space:nowrap;">
                <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('stageplaatsen.edit', $sp) }}">Bewerken</a>
                <form method="POST" action="{{ route('stageplaatsen.status', $sp) }}" style="display:inline;">@csrf<button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">{{ $sp->actief ? 'Inactiveren' : 'Activeren' }}</button></form>
              </td>
            @endif
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>

{{-- Stages (Fase D) — de plaatsingen van studenten. --}}
<div class="sis-card" id="stages" style="margin-bottom:16px;">
  <div class="sis-card__hd" style="display:flex; align-items:center; justify-content:space-between;">
    <b>Stages ({{ $organisatie->stages->count() }})</b>
    @if ($magStage)
      <a class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" href="{{ route('stages.create', $organisatie) }}">Student plaatsen</a>
    @endif
  </div>
  @if ($organisatie->stages->isEmpty())
    <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">Nog geen studenten geplaatst.</p></div>
  @else
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Stagenr.</th><th>Student</th><th>Opleiding</th><th>Stage</th><th>Begeleiders</th><th>Periode</th><th style="text-align:center;">Status</th><th>Beoordeling</th>@if($magStage)<th class="row-act"></th>@endif</tr></thead>
      <tbody>
        @foreach ($organisatie->stages as $stage)
          <tr>
            <td class="tnum">{{ $stage->stagenummer }}</td>
            <td class="nm">{{ $stage->student?->volledigeNaam() ?? '—' }}<br><small class="sis-muted">{{ $stage->student?->studentnummer }}</small></td>
            <td>{{ $stage->opleiding?->code ?? '—' }}</td>
            <td>@if($stage->stageperiode)<small>{{ $stage->stageperiode->naam }}<br><span class="sis-muted">{{ $stage->uren ?? $stage->stageperiode->verplichte_uren }} / {{ $stage->stageperiode->verplichte_uren }} u</span></small>@else<span class="sis-muted">—</span>@endif</td>
            <td><small>{{ $stage->stagebegeleider?->naam ?? '—' }}<br>{{ $stage->werkplekbegeleider?->volledigeNaam() ?? '—' }}</small></td>
            <td class="dt"><small>{{ $stage->startdatum?->format('d-m-Y') ?? '—' }}@if($stage->einddatum)<br>t/m {{ $stage->einddatum->format('d-m-Y') }}@endif</small></td>
            <td style="text-align:center;"><span class="iuasr-dash-status {{ $stage->status?->badge() }}">{{ $stage->status?->label() }}</span></td>
            <td>@if($stage->beoordeling)<span class="iuasr-dash-status {{ $stage->beoordeling === 'voldoende' ? 's-approved' : 's-rejected' }}">{{ ucfirst($stage->beoordeling) }}</span>@else <span class="sis-muted">—</span> @endif</td>
            @if($magStage)<td class="row-act"><a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('stages.edit', $stage) }}">Bewerken</a></td>@endif
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>

{{-- Taken (Fase E). --}}
<div class="sis-card" id="taken" style="margin-bottom:16px;">
  <div class="sis-card__hd"><b>Taken ({{ $organisatie->relatietaken->where('status','!=','afgerond')->count() }} open)</b></div>
  <div style="padding:14px 16px;">
    @if ($magBeheer)
      <form method="POST" action="{{ route('relatietaken.store', $organisatie) }}" style="margin-bottom:14px;">
        @csrf
        <div class="sis-fld"><label>Nieuwe taak <span class="req">*</span></label><input type="text" name="titel" maxlength="255" required placeholder="bv. Contract verlengen, stagebezoek plannen"></div>
        <div class="sis-fld-row sis-fld-row--2">
          <div class="sis-fld"><label>Toegewezen aan</label><select name="toegewezen_aan_id"><option value="">— vrij op te pakken —</option>@foreach ($taakMedewerkers as $m)<option value="{{ $m->id }}">{{ $m->naam }}</option>@endforeach</select></div>
          <div class="sis-fld"><label>Prioriteit</label><select name="prioriteit">@foreach (\App\Enums\TaakPrioriteit::opties() as $w => $l)<option value="{{ $w }}" @selected($w==='normaal')>{{ $l }}</option>@endforeach</select></div>
        </div>
        <div class="sis-fld-row sis-fld-row--2">
          <div class="sis-fld"><label>Vervaldatum</label><input type="date" name="vervaldatum"></div>
          <div class="sis-fld" style="align-self:end; text-align:right;"><button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Taak toevoegen</button></div>
        </div>
      </form>
    @endif
    @forelse ($organisatie->relatietaken as $taak)
      <div style="display:flex; justify-content:space-between; gap:12px; padding:10px 0; border-top:1px solid var(--border,#e5e5e5); {{ $taak->status->value==='afgerond' ? 'opacity:.55;' : '' }}">
        <div>
          <b>{{ $taak->titel }}</b>
          <span class="iuasr-dash-status {{ $taak->status->badge() }}">{{ $taak->status->label() }}</span>
          @if ($taak->isTeLaat())<span class="iuasr-dash-status s-rejected">Te laat</span>@endif
          <div><small class="sis-muted">
            {{ $taak->toegewezenAan?->naam ?? 'Vrij' }} · prioriteit {{ $taak->prioriteit->label() }}@if($taak->vervaldatum) · vervalt {{ $taak->vervaldatum->format('d-m-Y') }}@endif
          </small></div>
        </div>
        @if ($magBeheer)
          <div style="white-space:nowrap;">
            <form method="POST" action="{{ route('relatietaken.afronden', $taak) }}" style="display:inline;">@csrf<button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">{{ $taak->status->value==='afgerond' ? 'Heropenen' : 'Afronden' }}</button></form>
            <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('relatietaken.edit', $taak) }}">Bewerken</a>
            <form method="POST" action="{{ route('relatietaken.destroy', $taak) }}" onsubmit="return confirm('Taak verwijderen?');" style="display:inline;">@csrf @method('DELETE')<button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">×</button></form>
          </div>
        @endif
      </div>
    @empty
      <p class="sis-muted" style="margin:0;">Nog geen taken.</p>
    @endforelse
  </div>
</div>

{{-- Agenda (Fase E). --}}
<div class="sis-card" id="agenda" style="margin-bottom:16px;">
  <div class="sis-card__hd" style="display:flex; align-items:center; justify-content:space-between;">
    <b>Agenda ({{ $organisatie->afspraken->count() }})</b>
    @if ($magBeheer)
      <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('afspraken.create', $organisatie) }}">Afspraak plannen</a>
    @endif
  </div>
  @if ($organisatie->afspraken->isEmpty())
    <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">Nog geen afspraken gepland.</p></div>
  @else
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Datum</th><th>Tijd</th><th>Type</th><th>Onderwerp</th><th>Door</th><th style="text-align:center;">Status</th>@if($magBeheer)<th class="row-act"></th>@endif</tr></thead>
      <tbody>
        @foreach ($organisatie->afspraken as $af)
          <tr>
            <td class="dt">{{ $af->datum?->format('d-m-Y') }}</td>
            <td class="dt">{{ $af->tijd_van ? \Illuminate\Support\Str::of($af->tijd_van)->substr(0,5) : '—' }}@if($af->tijd_tot)–{{ \Illuminate\Support\Str::of($af->tijd_tot)->substr(0,5) }}@endif</td>
            <td>{{ $af->type?->label() ?? '—' }}</td>
            <td class="nm">{{ $af->omschrijving ? \Illuminate\Support\Str::limit($af->omschrijving, 60) : '—' }}@if($af->stage)<br><small class="sis-muted">Stage {{ $af->stage->student?->achternaam }}</small>@endif</td>
            <td>{{ $af->medewerker?->naam ?? '—' }}</td>
            <td style="text-align:center;"><span class="iuasr-dash-status {{ $af->status==='gepland' ? 's-requested' : ($af->status==='afgerond' ? 's-approved' : 's-rejected') }}">{{ ucfirst($af->status) }}</span></td>
            @if($magBeheer)
              <td class="row-act" style="white-space:nowrap;">
                <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('afspraken.edit', $af) }}">Bewerken</a>
                <form method="POST" action="{{ route('afspraken.destroy', $af) }}" onsubmit="return confirm('Afspraak verwijderen?');" style="display:inline;">@csrf @method('DELETE')<button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">×</button></form>
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
            @if($magBeheer)
              <td class="row-act" style="white-space:nowrap;">
                @if($cm->vervolgdatum)
                  <form method="POST" action="{{ route('contactmomenten.taak', $cm) }}" style="display:inline;" title="Maak een opvolgtaak met deze vervolgdatum">@csrf<button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">→ Taak</button></form>
                @endif
                <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('contactmomenten.edit', $cm) }}">Bewerken</a>
              </td>
            @endif
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
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

{{-- Overeenkomsten (Fase F). --}}
<div class="sis-card" id="overeenkomsten" style="margin-bottom:16px;">
  <div class="sis-card__hd" style="display:flex; align-items:center; justify-content:space-between;">
    <b>Overeenkomsten ({{ $organisatie->overeenkomsten->count() }})</b>
    @if ($magBeheer)
      <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('overeenkomsten.create', $organisatie) }}">Overeenkomst toevoegen</a>
    @endif
  </div>
  @if ($organisatie->overeenkomsten->isEmpty())
    <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">Nog geen overeenkomsten vastgelegd.</p></div>
  @else
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Type</th><th>Start</th><th>Verloopt</th><th style="text-align:center;">Status</th><th>Getekend</th>@if($magBeheer)<th class="row-act"></th>@endif</tr></thead>
      <tbody>
        @foreach ($organisatie->overeenkomsten as $ovk)
          <tr>
            <td class="nm">{{ $ovk->type?->label() }}@if($ovk->titel)<br><small class="sis-muted">{{ $ovk->titel }}</small>@endif</td>
            <td class="dt">{{ $ovk->startdatum?->format('d-m-Y') ?? '—' }}</td>
            <td class="dt">{{ $ovk->verloopdatum?->format('d-m-Y') ?? '—' }} @if($ovk->isVerlopen())<span class="iuasr-dash-status s-rejected">Verlopen</span>@elseif($ovk->dagenTotVerloop() !== null && $ovk->dagenTotVerloop() <= 60)<span class="iuasr-dash-status s-requested">Loopt af</span>@endif</td>
            <td style="text-align:center;"><span class="iuasr-dash-status {{ $ovk->status?->badge() }}">{{ $ovk->status?->label() }}</span></td>
            <td>@if($ovk->ondertekend_document_id)<a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('overeenkomsten.download', $ovk) }}">PDF</a>@else <span class="sis-muted">—</span> @endif</td>
            @if($magBeheer)
              <td class="row-act" style="white-space:nowrap;">
                <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('overeenkomsten.edit', $ovk) }}">Bewerken</a>
                <form method="POST" action="{{ route('overeenkomsten.destroy', $ovk) }}" onsubmit="return confirm('Overeenkomst verwijderen?');" style="display:inline;">@csrf @method('DELETE')<button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">×</button></form>
              </td>
            @endif
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>

{{-- Documenten met versiebeheer (Fase F). --}}
<div class="sis-card" id="documenten" style="margin-bottom:16px;">
  <div class="sis-card__hd"><b>Documenten ({{ $organisatie->documenten->count() }})</b></div>
  <div style="padding:14px 16px;">
    @if ($magBeheer)
      <form method="POST" action="{{ route('relatiedocumenten.store', $organisatie) }}" enctype="multipart/form-data" style="margin-bottom:14px;">
        @csrf
        <div class="sis-fld-row sis-fld-row--2">
          <div class="sis-fld"><label>Categorie <span class="req">*</span></label><select name="categorie" required>@foreach (\App\Models\RelatieDocument::CATEGORIEEN as $w => $l)<option value="{{ $w }}">{{ $l }}</option>@endforeach</select></div>
          <div class="sis-fld"><label>Titel</label><input type="text" name="titel" maxlength="255" placeholder="Optioneel"></div>
        </div>
        <div class="sis-fld-row sis-fld-row--2">
          <div class="sis-fld"><label>Bestand <span class="req">*</span></label><input type="file" name="bestand" required></div>
          <div class="sis-fld" style="align-self:end; text-align:right;"><button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Uploaden</button></div>
        </div>
        <small class="sis-muted">PDF, afbeelding of Office-bestand (max. 16 MB). Op de private schijf; inzage wordt gelogd.</small>
      </form>
    @endif
    @if ($organisatie->documenten->isEmpty())
      <p class="sis-muted" style="margin:0;">Nog geen documenten.</p>
    @else
      <table class="iuasr-dash-tbl">
        <thead><tr><th>Categorie</th><th>Bestand</th><th>Versie</th><th>Geüpload</th>@if($magBeheer)<th class="row-act"></th>@endif</tr></thead>
        <tbody>
          @foreach ($organisatie->documenten as $doc)
            <tr @if(! $doc->isHuidigeVersie()) style="opacity:.55;" @endif>
              <td>{{ $doc->categorieLabel() }}@if($doc->titel)<br><small class="sis-muted">{{ $doc->titel }}</small>@endif</td>
              <td class="nm"><a href="{{ route('relatiedocumenten.download', $doc) }}">{{ $doc->bestandsnaam }}</a></td>
              <td>v{{ $doc->versie }}@if(! $doc->isHuidigeVersie()) <small class="sis-muted">(vervangen)</small>@endif</td>
              <td class="dt"><small>{{ $doc->created_at?->format('d-m-Y') }}<br>{{ $doc->geuploadDoor?->naam }}</small></td>
              @if($magBeheer)
                <td class="row-act" style="white-space:nowrap;">
                  @if ($doc->isHuidigeVersie())
                    <form method="POST" action="{{ route('relatiedocumenten.versie', $doc) }}" enctype="multipart/form-data" style="display:inline;" onsubmit="return this.bestand.value !== '';">
                      @csrf
                      <label class="iuasr-dash-btn iuasr-dash-btn--sm" style="cursor:pointer;">Nieuwe versie<input type="file" name="bestand" style="display:none;" onchange="this.form.submit()"></label>
                    </form>
                  @endif
                  <form method="POST" action="{{ route('relatiedocumenten.destroy', $doc) }}" onsubmit="return confirm('Document verwijderen?');" style="display:inline;">@csrf @method('DELETE')<button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">×</button></form>
                </td>
              @endif
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>
</div>
@endsection
