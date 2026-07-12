@extends('layouts.app')

@section('titel', 'Bestuursoverzicht')

@php
    use App\Support\Statistiek;
    $euro0 = fn ($b) => '€ '.number_format((float) $b, 0, ',', '.');
    // Hoofdlijn (rubriekkop): een volledige scheidingslijn met titel en toelichting.
    $rubriekStijl = "font-family:'DM Serif Display',serif;font-size:22px;margin:34px 0 4px;color:var(--priColor100,#1E1446);border-top:3px solid var(--priColor100,#1E1446);padding-top:16px;";
    $subStijl = 'margin:0 0 14px;color:#6b6b6b;font-size:13px;';
    $afdBars = collect($hrPerAfdeling)->map(fn ($r) => ['label' => $r['afdeling'], 'value' => $r['fte']])->all();
@endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('modules.kiezen') }}">Platform</a><span class="sep">›</span><b>Bestuursoverzicht</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Bestuur — globaal overzicht</h1>
    <div class="summary">Eén instellingsbreed beeld van alle modules en afdelingen: studenten &amp; onderwijs, financiën, cursussen, relatiebeheer &amp; stage en personeelszaken. Alleen-lezen.</div>
  </div>
  <div class="iuasr-dash-vhead__actions">
    <a class="iuasr-dash-btn" href="{{ route('rapporten.alumni') }}">Alumni</a>
    <a class="iuasr-dash-btn" href="{{ route('presentieoverzicht') }}">Aanwezigheid</a>
    <a class="iuasr-dash-btn" href="{{ route('cursussen.rapport') }}">Cursusrapport</a>
    <a class="iuasr-dash-btn" href="{{ route('hr.rapport') }}">HR-rapport</a>
    <a class="iuasr-dash-btn" href="{{ route('hr.verzuimverlof') }}">HR verzuim &amp; verlof</a>
    <a class="iuasr-dash-btn" href="{{ route('relatiebeheer.dashboard') }}">Relatiebeheer</a>
  </div>
</div>

{{-- Onderwijsnieuws (informatief; lokaal opgeslagen, dagelijks bijgewerkt). --}}
<div class="sis-card" style="margin-bottom:18px;border-left:3px solid var(--priColor300,#D69A2D);">
  <div class="sis-card__hd">
    <h3>Onderwijsnieuws</h3>
    <span class="hint">Belangrijke berichten uit het onderwijsveld — automatisch bijgewerkt (dagelijks)</span>
  </div>
  @if ($nieuws->isEmpty())
    <p class="sis-muted" style="font-size:13px;margin:0;">Nog geen nieuws opgehaald. Beheer kan het handmatig ophalen via <b>Beheer → Nieuwsbronnen</b>.</p>
  @else
    <div style="display:flex;flex-direction:column;">
      @foreach ($nieuws as $bericht)
        <a href="{{ $bericht->link }}" target="_blank" rel="noopener noreferrer"
           style="display:flex;gap:12px;align-items:baseline;padding:10px 2px;border-top:1px solid var(--borderSubtleColor);text-decoration:none;color:inherit;">
          <span style="flex:0 0 74px;font-size:11.5px;color:var(--blackAltText,#6b6b6b);white-space:nowrap;">
            {{ $bericht->gepubliceerd_op?->format('d-m-Y') ?? '—' }}
          </span>
          <span style="flex:1 1 auto;">
            <b style="font-weight:600;color:var(--priColor100,#1E1446);">{{ $bericht->titel }}</b>
            @if ($bericht->samenvatting)
              <span style="display:block;font-size:12.5px;color:var(--blackAltText,#6b6b6b);margin-top:2px;">{{ $bericht->samenvatting }}</span>
            @endif
          </span>
          <span class="sis-pill-soft" style="flex:0 0 auto;font-size:10.5px;align-self:center;">{{ $bericht->bron?->naam }}</span>
        </a>
      @endforeach
    </div>
    <p class="sis-tblnote" style="margin-top:10px;">Alleen ter informatie. Klik voor het volledige bericht op de bron. Bronnen: {{ \App\Models\Nieuwsbron::where('actief', true)->pluck('naam')->implode(' · ') }}.</p>
  @endif
</div>

{{-- ===================== RUBRIEK A — STUDENTEN & ONDERWIJS ===================== --}}
<h2 style="{{ $rubriekStijl }}">A · Studenten &amp; onderwijs</h2>
<p style="{{ $subStijl }}">Module Studentenzaken — inschrijving, rendement en aanwezigheid over {{ $aantalOpleidingen }} opleidingen.</p>

<div class="iuasr-dash-stats" style="grid-template-columns:repeat(4,1fr);">
  <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Actief ingeschreven</span><span class="val">{{ $kern['actief'] }}</span><span class="delta">van {{ $kern['studenten'] }} studenten · {{ $aantalOpleidingen }} opleidingen</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Afgestudeerd</span><span class="val">{{ $kern['afgestudeerd'] }}</span><span class="delta">alumni · {{ $kern['uitgeschreven'] }} uitgeschreven</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Studiesucces</span><span class="val">{{ $slaag['percentage'] }}%</span><span class="delta">toetsen voldoende</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Aanwezigheid</span><span class="val">{{ $presentie['percentage'] }}%</span><span class="delta">{{ $presentie['onder_norm'] }} onder de norm</span></div>
</div>

<div class="sis-chartgrid" style="margin-top:16px;">
  <div class="sis-chart-card">
    <h3>Studenten per opleiding</h3><p class="sub">Actieve inschrijvingen</p>
    @include('partials.charts.bar', ['data' => $perOpleiding, 'kleur' => 'var(--priColor200)', 'leeg' => 'Nog geen inschrijvingen.'])
  </div>
  <div class="sis-chart-card">
    <h3>Instroom per studiejaar</h3><p class="sub">Nieuwe inschrijvingen</p>
    @include('partials.charts.spark', ['data' => $instroom])
  </div>
  <div class="sis-chart-card">
    <h3>Inschrijvingsstatus</h3><p class="sub">Rendement &amp; uitval</p>
    @include('partials.charts.donut', ['segments' => $status, 'middenLabel' => 'totaal'])
  </div>
</div>

<div class="sis-chartgrid" style="margin-top:16px;">
  <div class="sis-chart-card">
    <h3>Aanwezigheid per opleiding</h3><p class="sub">Gemiddeld · onderwijskwaliteit</p>
    @include('partials.charts.bar', ['data' => $presentiePerOpleiding, 'kleur' => Statistiek::GROEN, 'eenheid' => '%', 'leeg' => 'Nog geen registraties.'])
  </div>
  <div class="sis-chart-card">
    <h3>Verdeling aanwezigheid</h3><p class="sub">Per student per vak · norm 80% (50% bij regeling)</p>
    @include('partials.charts.donut', ['segments' => $presentieVerdeling, 'middenLabel' => 'metingen'])
  </div>
</div>

{{-- ===================== RUBRIEK B — FINANCIËN ===================== --}}
<h2 style="{{ $rubriekStijl }}">B · Financiën</h2>
<p style="{{ $subStijl }}">Twee geldstromen samen: het <b>collegegeld</b> van de opleidingen én de <b>cursusgelden</b> van de cursussen. De tegels hieronder tonen eerst het gecombineerde totaal, daarna beide stromen apart.</p>

{{-- Gecombineerd totaal (collegegeld + cursusgeld) --}}
<div class="iuasr-dash-stats" style="grid-template-columns:repeat(4,1fr);">
  <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Totaal voldaan</span><span class="val">{{ $financieelTotaal['betaalgraad'] }}%</span><span class="delta">collegegeld + cursusgeld</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Totaal verschuldigd</span><span class="val">{{ $euro0($financieelTotaal['verschuldigd']) }}</span><span class="delta">in rekening gebracht</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Totaal openstaand</span><span class="val">{{ $euro0($financieelTotaal['openstaand']) }}</span><span class="delta">nog te ontvangen</span></div>
  <div class="iuasr-dash-stat {{ $collegegeld['achterstand_aantal'] > 0 ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Betaalachterstand</span><span class="val">{{ $collegegeld['achterstand_aantal'] }}</span><span class="delta">studenten (collegegeld)</span></div>
</div>

<div class="sis-chartgrid" style="margin-top:16px;">
  <div class="sis-chart-card">
    <h3>Collegegeld — opleidingen</h3><p class="sub">Betaalgraad {{ $collegegeld['betaalgraad'] }}% · {{ $euro0($collegegeld['openstaand']) }} openstaand</p>
    @include('partials.charts.donut', [
      'segments' => [
        ['label' => 'Betaald', 'value' => (int) round($collegegeld['betaald']), 'kleur' => Statistiek::GROEN],
        ['label' => 'Openstaand', 'value' => (int) round($collegegeld['openstaand']), 'kleur' => Statistiek::ROOD],
      ],
      'midden' => $collegegeld['betaalgraad'].'%', 'middenLabel' => 'voldaan',
    ])
  </div>
  <div class="sis-chart-card">
    <h3>Cursusgelden — cursussen</h3><p class="sub">Betaalgraad {{ $cursusgeld['betaalgraad'] }}% · {{ $euro0($cursusgeld['openstaand']) }} openstaand</p>
    @include('partials.charts.donut', [
      'segments' => [
        ['label' => 'Betaald', 'value' => (int) round($cursusgeld['betaald']), 'kleur' => Statistiek::GROEN],
        ['label' => 'Openstaand', 'value' => (int) round($cursusgeld['openstaand']), 'kleur' => Statistiek::ROOD],
      ],
      'midden' => $cursusgeld['betaalgraad'].'%', 'middenLabel' => 'voldaan',
    ])
  </div>
</div>

{{-- ===================== RUBRIEK C — CURSUSSEN ===================== --}}
<h2 style="{{ $rubriekStijl }}">C · Cursussen</h2>
<p style="{{ $subStijl }}">Module Cursussen — het cursusaanbod en de cursisten, los van de reguliere studenten.</p>

<div class="iuasr-dash-stats" style="grid-template-columns:repeat(4,1fr);">
  <div class="iuasr-dash-stat"><span class="lbl">Cursussen</span><span class="val">{{ $aantalCursussen }}</span><span class="delta">actief in aanbod</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Cursisten</span><span class="val">{{ $aantalCursisten }}</span><span class="delta">los van de studenten</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Actieve inschrijvingen</span><span class="val">{{ $cursusInschrijvingen }}</span><span class="delta">lopende deelnames</span></div>
</div>

<div class="sis-chartgrid" style="margin-top:16px;">
  <div class="sis-chart-card">
    <h3>Inschrijvingen per cursus</h3><p class="sub">Actieve deelnames</p>
    @include('partials.charts.bar', ['data' => $cursusPerCursus, 'kleur' => 'var(--heritage-green, #285C4D)', 'leeg' => 'Nog geen cursusinschrijvingen.'])
  </div>
</div>

{{-- ===================== RUBRIEK D — RELATIEBEHEER & STAGE ===================== --}}
<h2 style="{{ $rubriekStijl }}">D · Relatiebeheer &amp; stage</h2>
<p style="{{ $subStijl }}">Module Relatiebeheer &amp; Stage — organisaties, stageplaatsen en lopende plaatsingen (alle opleidingen).</p>

<div class="iuasr-dash-stats" style="grid-template-columns:repeat(4,1fr);">
  <div class="iuasr-dash-stat"><span class="lbl">Organisaties</span><span class="val">{{ $relatie['organisaties'] }}</span><span class="delta">{{ $relatie['contactpersonen'] }} contactpersonen</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Stageplaatsen</span><span class="val">{{ $relatie['stageplaatsen'] }}</span><span class="delta">{{ $relatie['bezettingsgraad'] !== null ? $relatie['bezettingsgraad'].'% bezet' : 'geen capaciteit vastgelegd' }}</span></div>
  <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Lopende stages</span><span class="val">{{ $relatie['stages_lopend'] }}</span><span class="delta">actief geplaatst</span></div>
  <div class="iuasr-dash-stat {{ $relatie['stages_te_beoordelen'] > 0 ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Te beoordelen</span><span class="val">{{ $relatie['stages_te_beoordelen'] }}</span><span class="delta">stages wachten op beoordeling</span></div>
</div>
<div class="iuasr-dash-stats" style="grid-template-columns:repeat(4,1fr);margin-top:12px;">
  <div class="iuasr-dash-stat"><span class="lbl">Open taken</span><span class="val">{{ $relatie['taken_open'] }}</span><span class="delta">relatietaken</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Afspraken (7 dagen)</span><span class="val">{{ $relatie['afspraken_komend'] }}</span><span class="delta">gepland deze week</span></div>
  <div class="iuasr-dash-stat {{ $relatie['contracten_verlopen'] > 0 ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Contracten verlopen</span><span class="val">{{ $relatie['contracten_verlopen'] }}</span><span class="delta">binnen 60 dagen</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Stage-evaluatie</span><span class="val">{{ $stageEvaluatie['percentage'] !== null ? $stageEvaluatie['percentage'].'%' : '—' }}</span><span class="delta">voldoende ({{ $stageEvaluatie['beoordeeld'] }} beoordeeld)</span></div>
</div>

<div class="sis-chartgrid" style="margin-top:16px;">
  <div class="sis-chart-card">
    <h3>Stages per status</h3><p class="sub">Verdeling van alle stages</p>
    @include('partials.charts.donut', ['segments' => $stagesPerStatus, 'middenLabel' => 'stages'])
  </div>
  <div class="sis-chart-card">
    <h3>Organisaties per type</h3><p class="sub">Samenstelling van het netwerk</p>
    @include('partials.charts.bar', ['data' => $organisatiesPerType, 'kleur' => 'var(--priColor200)', 'leeg' => 'Nog geen organisaties.'])
  </div>
</div>

{{-- ===================== RUBRIEK E — HR / PERSONEELSZAKEN ===================== --}}
<h2 style="{{ $rubriekStijl }}">E · HR / Personeelszaken</h2>
<p style="{{ $subStijl }}">Module HR — personeelsbestand, FTE en verzuim over {{ $aantalAfdelingen }} afdelingen.</p>

<div class="iuasr-dash-stats" style="grid-template-columns:repeat(4,1fr);">
  <div class="iuasr-dash-stat"><span class="lbl">Medewerkers</span><span class="val">{{ $hr['medewerkers'] }}</span><span class="delta">{{ $hr['actief'] }} actief in dienst</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Totaal FTE</span><span class="val">{{ number_format($hr['fte'], 1, ',', '.') }}</span><span class="delta">gem. {{ number_format($hr['gem_fte'], 2, ',', '.') }} per medewerker</span></div>
  <div class="iuasr-dash-stat {{ $hr['ziek'] > 0 ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Ziek gemeld</span><span class="val">{{ $hr['ziek'] }}</span><span class="delta">verzuim {{ number_format($hr['verzuim'], 1, ',', '.') }}%</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Verzuimdagen</span><span class="val">{{ $hr['verzuim_dagen'] }}</span><span class="delta">dit jaar (alle medewerkers)</span></div>
</div>

<div class="sis-chartgrid" style="margin-top:16px;">
  <div class="sis-chart-card">
    <h3>FTE per afdeling</h3><p class="sub">Formatie · actieve medewerkers</p>
    @include('partials.charts.bar', ['data' => $afdBars, 'kleur' => 'var(--heritage-green, #285C4D)', 'leeg' => 'Nog geen medewerkers.'])
  </div>
  <div class="sis-chart-card">
    <h3>Bezetting per afdeling</h3><p class="sub">Aantal medewerkers en verzuim</p>
    @if (empty($hrPerAfdeling))
      <p class="sis-bars--empty">Nog geen medewerkers.</p>
    @else
      <table class="iuasr-dash-tbl" style="width:100%;">
        <thead><tr><th>Afdeling</th><th style="text-align:right;">Medewerkers</th><th style="text-align:right;">FTE</th><th style="text-align:right;">Verzuim</th></tr></thead>
        <tbody>
          @foreach ($hrPerAfdeling as $rij)
            <tr>
              <td>{{ $rij['afdeling'] }}</td>
              <td style="text-align:right;">{{ $rij['aantal'] }}</td>
              <td style="text-align:right;">{{ number_format($rij['fte'], 2, ',', '.') }}</td>
              <td style="text-align:right;">{{ number_format($rij['verzuim'], 1, ',', '.') }}%</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>
</div>

<p class="sis-tblnote" style="margin-top:18px;">Dit overzicht bundelt de statistieken van alle modules en afdelingen en is alleen-lezen. Voor detailrapporten kunt u naar <a href="{{ route('rapporten.alumni') }}">Alumni</a>, het <a href="{{ route('cursussen.rapport') }}">Cursusrapport</a>, het <a href="{{ route('hr.rapport') }}">HR-rapport</a>, het <a href="{{ route('relatiebeheer.dashboard') }}">Relatiebeheer-dashboard</a> of de <a href="{{ route('ondertekening') }}">ondertekende documenten</a>.</p>
@endsection
