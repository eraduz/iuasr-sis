@extends('layouts.app')

@section('titel', 'Relatiebeheer')

@section('inhoud')
<div class="iuasr-dash-vhead">
  <div>
    <h1>Relatiebeheer &amp; Stage</h1>
    <div class="summary">Overzicht van uw relaties, stages en signaleringen</div>
    @php $mijnOpleidingen = auth()->user()->opleidingen->sortBy('code'); @endphp
    @if ($mijnOpleidingen->isNotEmpty())
      <div style="margin-top:6px;">
        <span class="sis-pill-soft" title="Uw opleiding(en) binnen relatiebeheer &amp; stage">Opleiding: <b>{{ $mijnOpleidingen->pluck('naam')->implode(' · ') }}</b></span>
      </div>
    @endif
  </div>
  <div class="iuasr-dash-vhead__actions" style="display:flex; gap:8px;">
    <a class="iuasr-dash-btn" href="{{ route('relaties') }}">Organisaties</a>
    <a class="iuasr-dash-btn" href="{{ route('stages') }}">Stages</a>
    <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('relatiebeheer.rapport') }}">Rapportage</a>
  </div>
</div>

<div class="iuasr-dash-stats" style="grid-template-columns:repeat(5,1fr);">
  <div class="iuasr-dash-stat"><span class="lbl">Organisaties</span><span class="val">{{ $kpi['organisaties'] }}</span><span class="delta">{{ $kpi['organisaties_nieuw'] }} nieuw (30 dagen)</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Contactpersonen</span><span class="val">{{ $kpi['contactpersonen'] }}</span><span class="delta">actief</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Stageplaatsen</span><span class="val">{{ $kpi['stageplaatsen'] }}</span><span class="delta">{{ $kpi['bezettingsgraad'] !== null ? $kpi['bezettingsgraad'].'% bezet' : 'geen maximum ingesteld' }}</span></div>
  <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Lopende stages</span><span class="val">{{ $kpi['stages_lopend'] }}</span><span class="delta">{{ $evaluatie['percentage'] !== null ? $evaluatie['percentage'].'% voldoende' : 'nog niet beoordeeld' }}</span></div>
  <div class="iuasr-dash-stat {{ $kpi['stages_te_beoordelen'] > 0 ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Te beoordelen</span><span class="val">{{ $kpi['stages_te_beoordelen'] }}</span><span class="delta">stages</span></div>
</div>

<div class="iuasr-dash-stats" style="grid-template-columns:repeat(5,1fr);margin-top:12px;">
  <div class="iuasr-dash-stat"><span class="lbl">Open taken</span><span class="val">{{ $kpi['taken_open'] }}</span><span class="delta"><a href="{{ route('agenda') }}">planning</a></span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Aankomende bezoeken</span><span class="val">{{ $kpi['afspraken_komend'] }}</span><span class="delta">komende 7 dagen</span></div>
  <div class="iuasr-dash-stat {{ $kpi['contracten_verlopen'] > 0 ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Contracten verlopen</span><span class="val">{{ $kpi['contracten_verlopen'] }}</span><span class="delta">≤ 60 dagen</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Nieuwe documenten</span><span class="val">{{ $kpi['documenten_nieuw'] }}</span><span class="delta">laatste 30 dagen</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Beoordeeld</span><span class="val">{{ $evaluatie['beoordeeld'] }}</span><span class="delta">{{ $evaluatie['voldoende'] }} voldoende</span></div>
</div>

<div class="sis-chartgrid" style="margin-top:16px;">
  <div class="sis-chart-card">
    <h3>Stages per status</h3>
    @include('partials.charts.donut', ['segments' => $stagesPerStatus, 'middenLabel' => 'stages'])
  </div>
  <div class="sis-chart-card">
    <h3>Organisaties per type</h3>
    @include('partials.charts.bar', ['data' => $organisatiesPerType, 'kleur' => 'var(--priColor200)', 'leeg' => 'Nog geen organisaties.'])
  </div>
</div>

<div class="sis-card" style="margin-top:16px;">
  <div class="sis-card__hd"><b>Te beoordelen stages</b></div>
  @if ($teBeoordelen->isEmpty())
    <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">Geen stages die op een beoordeling wachten.</p></div>
  @else
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Stagenr.</th><th>Student</th><th>Opleiding</th><th>Organisatie</th><th>Einddatum</th><th class="row-act"></th></tr></thead>
      <tbody>
        @foreach ($teBeoordelen as $stage)
          <tr>
            <td class="tnum">{{ $stage->stagenummer }}</td>
            <td class="nm">{{ $stage->student?->volledigeNaam() }}</td>
            <td>{{ $stage->opleiding?->code }}</td>
            <td><a href="{{ route('relaties.show', $stage->organisatie) }}#stages">{{ $stage->organisatie?->naam }}</a></td>
            <td class="dt">{{ $stage->einddatum?->format('d-m-Y') ?? '—' }}</td>
            <td class="row-act">@if(auth()->user()->magStagebeheer())<a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('stages.edit', $stage) }}">Beoordelen</a>@endif</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>
@endsection
