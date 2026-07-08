@extends('layouts.app')

@section('titel', 'Dashboard')

@php
  $rol = auth()->user()->rol;
  $euro = fn ($v) => '€ '.number_format((float) $v, 0, ',', '.');
@endphp

@section('inhoud')

{{-- ========================= STUDENTENZAKEN ========================= --}}
@if ($rol === App\Enums\Rol::Studentenzaken)
  <div class="iuasr-dash-vhead">
    <div>
      <h1>Studentenzaken</h1>
      <div class="summary">Identiteit &amp; inschrijving · <b>geen</b> cijferinzage voor deze rol</div>
    </div>
    <div class="iuasr-dash-vhead__actions">
      <a class="iuasr-dash-btn" href="{{ route('collegegeld') }}">Collegegeld</a>
      <a class="iuasr-dash-btn" href="{{ route('verklaringen') }}">Verklaring opstellen</a>
      <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('inschrijven') }}">Student inschrijven</a>
    </div>
  </div>

  <div class="iuasr-dash-stats">
    <div class="iuasr-dash-stat"><span class="lbl">Studenten</span><span class="val">{{ $kpi['studenten'] }}</span><span class="delta">in het systeem</span></div>
    <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Actief ingeschreven</span><span class="val">{{ $kpi['inschrijvingen'] }}</span><span class="delta">huidige periode</span></div>
    <div class="iuasr-dash-stat"><span class="lbl">Afgestudeerd</span><span class="val">{{ $kpi['afgestudeerd'] }}</span><span class="delta">alumni</span></div>
    <div class="iuasr-dash-stat"><span class="lbl">Vrijstellingen</span><span class="val">{{ $stat['vrijstellingen'] ?? 0 }}</span><span class="delta">vastgelegd</span></div>
  </div>

  <div class="sis-chartgrid" style="margin-top:16px;">
    <div class="sis-chart-card">
      <h3>Studenten per opleiding</h3><p class="sub">Actieve inschrijvingen</p>
      @include('partials.charts.bar', ['data' => $stat['perOpleiding'] ?? [], 'kleur' => 'var(--priColor200)'])
    </div>
    <div class="sis-chart-card">
      <h3>Instroom per studiejaar</h3><p class="sub">Nieuwe inschrijvingen</p>
      @include('partials.charts.spark', ['data' => $stat['instroom'] ?? []])
    </div>
    <div class="sis-chart-card">
      <h3>Studenten per leerjaar</h3><p class="sub">Actieve inschrijvingen</p>
      @include('partials.charts.bar', ['data' => $stat['perLeerjaar'] ?? [], 'kleur' => '#5B7FBF'])
    </div>
    <div class="sis-chart-card">
      <h3>Inschrijvingsstatus</h3><p class="sub">Alle inschrijvingen</p>
      @include('partials.charts.donut', ['segments' => $stat['status'] ?? [], 'middenLabel' => 'totaal'])
    </div>
  </div>

  <div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-top:16px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    <span>Als Studentenzaken heeft u <b>geen inzage in cijfers</b>. Dit wordt server-side afgedwongen (rolscheiding), niet alleen in de interface.</span>
  </div>

  @php
    $verlopen = $nt2->where('status', 'verlopen');
    $binnenkort = $nt2->filter(fn ($r) => $r['status'] === 'open' && $r['dagen'] !== null && $r['dagen'] <= 30);
  @endphp
  <div class="sis-grid-2" style="margin-top:16px;align-items:start;">
    <div class="sis-card">
      <div class="sis-card__hd"><h3>NT2-examen</h3>@if ($nt2->isNotEmpty())<span class="hint">{{ $nt2->count() }} openstaand</span>@endif</div>
      @if ($nt2->isEmpty())
        <p class="sis-muted" style="font-size:13px;margin:0;">Geen openstaande NT2-verplichtingen.</p>
      @else
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px;">
          @if ($verlopen->count())<span class="iuasr-dash-status s-rejected">{{ $verlopen->count() }} verstreken</span>@endif
          @if ($binnenkort->count())<span class="iuasr-dash-status s-incomplete">{{ $binnenkort->count() }} &lt; 30 dagen</span>@endif
        </div>
        <ul class="iuasr-dash-log" style="margin:0;">
          @foreach ($nt2->take(4) as $r)
            @php $s = $r['student']; $verl = $r['status'] === 'verlopen'; @endphp
            <li>
              <a href="{{ route('studenten.show', $s) }}"><b>{{ $s->volledigeNaam() }}</b></a>
              <time>@if ($verl)<span style="color:var(--secColor100);">{{ abs($r['dagen']) }} dagen te laat</span>@else nog {{ $r['dagen'] }} dagen @endif</time>
            </li>
          @endforeach
        </ul>
        @if ($nt2->count() > 4)<p class="sis-muted" style="font-size:12px;margin:8px 2px 0;">+ {{ $nt2->count() - 4 }} meer…</p>@endif
      @endif
    </div>

    <div class="sis-card">
      <div class="sis-card__hd"><h3>Documenten later</h3>@if ($docLater->isNotEmpty())<span class="hint">{{ $docLater->count() }} student(en)</span>@endif</div>
      @if ($docLater->isEmpty())
        <p class="sis-muted" style="font-size:13px;margin:0;">Geen openstaande documentaanleveringen.</p>
      @else
        <ul class="iuasr-dash-log" style="margin:0;">
          @foreach ($docLater->take(5) as $s)
            <li>
              <a href="{{ route('studenten.show', $s) }}#documenten"><b>{{ $s->volledigeNaam() }}</b> · {{ $s->studentnummer }}</a>
              <time>diploma of benodigde documenten worden later aangeleverd</time>
            </li>
          @endforeach
        </ul>
        @if ($docLater->count() > 5)<p class="sis-muted" style="font-size:12px;margin:8px 2px 0;">+ {{ $docLater->count() - 5 }} meer…</p>@endif
      @endif
    </div>
  </div>

{{-- ========================= FINANCIËLE ADMINISTRATIE ========================= --}}
@elseif ($rol === App\Enums\Rol::Financien)
  @php $fin = $stat['financieel'] ?? ['verschuldigd'=>0,'betaald'=>0,'openstaand'=>0,'achterstand_aantal'=>0,'betaalgraad'=>0,'openstaand_per_opleiding'=>[]]; @endphp
  <div class="iuasr-dash-vhead">
    <div>
      <h1>Financiële Administratie</h1>
      <div class="summary">Collegegeld · betalingen &amp; achterstanden (synthetische bedragen)</div>
    </div>
    <div class="iuasr-dash-vhead__actions">
      <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('financien') }}">Betalingen &amp; achterstand</a>
    </div>
  </div>

  <div class="iuasr-dash-stats">
    <div class="iuasr-dash-stat"><span class="lbl">Verschuldigd</span><span class="val" style="font-size:20px;">{{ $euro($fin['verschuldigd']) }}</span><span class="delta">actieve studenten</span></div>
    <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Betaald</span><span class="val" style="font-size:20px;">{{ $euro($fin['betaald']) }}</span><span class="delta">{{ $fin['betaalgraad'] }}% van verschuldigd</span></div>
    <div class="iuasr-dash-stat {{ $fin['openstaand'] > 0 ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Openstaand</span><span class="val" style="font-size:20px;">{{ $euro($fin['openstaand']) }}</span><span class="delta">te ontvangen</span></div>
    <div class="iuasr-dash-stat {{ $fin['achterstand_aantal'] > 0 ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Achterstanden</span><span class="val">{{ $fin['achterstand_aantal'] }}</span><span class="delta">studenten met schuld</span></div>
  </div>

  <div class="sis-chartgrid" style="margin-top:16px;">
    <div class="sis-chart-card">
      <h3>Betaalgraad</h3><p class="sub">Betaald t.o.v. openstaand</p>
      @include('partials.charts.donut', [
        'segments' => [
          ['label' => 'Betaald', 'value' => (int) round($fin['betaald']), 'kleur' => App\Support\Statistiek::GROEN],
          ['label' => 'Openstaand', 'value' => (int) round($fin['openstaand']), 'kleur' => App\Support\Statistiek::ROOD],
        ],
        'midden' => $fin['betaalgraad'].'%', 'middenLabel' => 'betaald',
      ])
    </div>
    <div class="sis-chart-card">
      <h3>Openstaand per opleiding</h3><p class="sub">In euro's</p>
      @include('partials.charts.bar', ['data' => $fin['openstaand_per_opleiding'], 'kleur' => 'var(--secColor100)', 'leeg' => 'Geen openstaande bedragen.'])
    </div>
  </div>

  <div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-top:16px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
    <span>U registreert betalingen per student. Het systeem bepaalt automatisch de achterstand; studenten met een schuld worden gesignaleerd bij Studentenzaken.</span>
  </div>

{{-- ========================= DOCENT ========================= --}}
@elseif ($rol === App\Enums\Rol::Docent)
  <div class="iuasr-dash-vhead">
    <div>
      <h1>Docent</h1>
      <div class="summary">Cijferinvoer voor <b>uw eigen vakken</b></div>
    </div>
    <div class="iuasr-dash-vhead__actions">
      <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('cijferinvoer') }}">Cijfers invoeren</a>
    </div>
  </div>
  <div class="iuasr-dash-alert iuasr-dash-alert--info">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <span>U ziet en muteert <b>alleen uw eigen vakken</b>. Andere vakken en persoonsdossiers zijn niet toegankelijk.</span>
  </div>

{{-- ========================= EXAMENCOMMISSIE ========================= --}}
@elseif ($rol === App\Enums\Rol::Examencommissie)
  @php $slaag = $stat['slaag'] ?? ['percentage'=>0,'totaal'=>0,'voldoende'=>0,'onvoldoende'=>0]; @endphp
  <div class="iuasr-dash-vhead">
    <div>
      <h1>Examencommissie</h1>
      <div class="summary">Toetsresultaten &amp; studievoortgang · wijzigen strikt &amp; gelogd</div>
    </div>
    <div class="iuasr-dash-vhead__actions">
      <a class="iuasr-dash-btn" href="{{ route('overgang') }}">Leerjaar-herbeoordeling</a>
      <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('cijferoverzicht') }}">Cijferoverzicht</a>
    </div>
  </div>

  <div class="iuasr-dash-stats">
    <div class="iuasr-dash-stat {{ $kpi['ter_vaststelling'] > 0 ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Ter vaststelling</span><span class="val">{{ $kpi['ter_vaststelling'] }}</span><span class="delta">ingediende cijferlijsten</span></div>
    <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Slaagpercentage</span><span class="val">{{ $slaag['percentage'] }}%</span><span class="delta">{{ $slaag['voldoende'] }}/{{ $slaag['totaal'] }} toetsen voldoende</span></div>
    <div class="iuasr-dash-stat"><span class="lbl">Herkansingen</span><span class="val">{{ $stat['herkansingen'] ?? 0 }}</span><span class="delta">geregistreerd</span></div>
    <div class="iuasr-dash-stat"><span class="lbl">Vrijstellingen</span><span class="val">{{ $stat['vrijstellingen'] ?? 0 }}</span><span class="delta">vastgelegd</span></div>
  </div>

  <div class="sis-chartgrid" style="margin-top:16px;">
    <div class="sis-chart-card">
      <h3>Cijferverdeling</h3><p class="sub">Alle beoordeelde toetsresultaten</p>
      @include('partials.charts.spark', ['data' => $stat['cijferverdeling'] ?? [], 'kleur' => 'var(--priColor200)'])
    </div>
    <div class="sis-chart-card">
      <h3>Cijferlijst-status</h3><p class="sub">Huidige periode</p>
      @include('partials.charts.donut', ['segments' => $stat['cijferlijstStatus'] ?? [], 'middenLabel' => 'vakken'])
    </div>
    <div class="sis-chart-card">
      <h3>Overgangsadvies</h3><p class="sub">Actieve studenten · EC t.o.v. drempel</p>
      @include('partials.charts.donut', ['segments' => $stat['overgang'] ?? [], 'middenLabel' => 'studenten'])
    </div>
    <div class="sis-chart-card">
      <h3>Studenten per opleiding</h3><p class="sub">Actieve inschrijvingen</p>
      @include('partials.charts.bar', ['data' => $stat['perOpleiding'] ?? [], 'kleur' => 'var(--priColor200)'])
    </div>
  </div>

{{-- ========================= DIRECTIE & SCHOOLBESTUUR ========================= --}}
@elseif ($rol === App\Enums\Rol::Directie || $rol === App\Enums\Rol::Bestuur)
  @php
    $slaag = $stat['slaag'] ?? ['percentage'=>0];
    $fin = $stat['financieel'] ?? ['verschuldigd'=>0,'betaald'=>0,'openstaand'=>0,'betaalgraad'=>0];
    $isBestuur = $rol === App\Enums\Rol::Bestuur;
  @endphp
  <div class="iuasr-dash-vhead">
    <div>
      <h1>{{ $isBestuur ? 'Schoolbestuur' : 'Directie' }}</h1>
      <div class="summary">Instellingsbrede kerncijfers: studiesucces, instroom en financiën</div>
    </div>
    <div class="iuasr-dash-vhead__actions">
      @if ($isBestuur)<a class="iuasr-dash-btn" href="{{ route('ondertekening') }}">Ondertekende documenten</a>@endif
      <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('rapporten.inzage') }}">Rapporten</a>
    </div>
  </div>

  <div class="iuasr-dash-stats">
    <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Actief ingeschreven</span><span class="val">{{ $kpi['inschrijvingen'] }}</span><span class="delta">van {{ $kpi['studenten'] }} studenten</span></div>
    <div class="iuasr-dash-stat"><span class="lbl">Afgestudeerd</span><span class="val">{{ $kpi['afgestudeerd'] }}</span><span class="delta">alumni</span></div>
    <div class="iuasr-dash-stat"><span class="lbl">Studiesucces</span><span class="val">{{ $slaag['percentage'] }}%</span><span class="delta">toetsen voldoende</span></div>
    <div class="iuasr-dash-stat"><span class="lbl">Uitgeschreven</span><span class="val">{{ $kpi['uitgeschreven'] }}</span><span class="delta">uitval</span></div>
  </div>

  <div class="sis-chartgrid" style="margin-top:16px;">
    <div class="sis-chart-card">
      <h3>Studenten per opleiding</h3><p class="sub">Actieve inschrijvingen</p>
      @include('partials.charts.bar', ['data' => $stat['perOpleiding'] ?? [], 'kleur' => 'var(--priColor200)'])
    </div>
    <div class="sis-chart-card">
      <h3>Instroom per studiejaar</h3><p class="sub">Nieuwe inschrijvingen</p>
      @include('partials.charts.spark', ['data' => $stat['instroom'] ?? []])
    </div>
    <div class="sis-chart-card">
      <h3>Inschrijvingsstatus</h3><p class="sub">Rendement &amp; uitval</p>
      @include('partials.charts.donut', ['segments' => $stat['status'] ?? [], 'middenLabel' => 'totaal'])
    </div>
    <div class="sis-chart-card">
      <h3>Overgangsadvies</h3><p class="sub">Actieve studenten · EC t.o.v. drempel</p>
      @include('partials.charts.donut', ['segments' => $stat['overgang'] ?? [], 'middenLabel' => 'studenten'])
    </div>
  </div>

  <div class="sis-chartgrid" style="margin-top:16px;">
    <div class="sis-chart-card">
      <h3>Collegegeld</h3><p class="sub">Betaald t.o.v. openstaand (synthetisch)</p>
      <div class="sis-donut-wrap">
        @include('partials.charts.donut', [
          'segments' => [
            ['label' => 'Betaald', 'value' => (int) round($fin['betaald']), 'kleur' => App\Support\Statistiek::GROEN],
            ['label' => 'Openstaand', 'value' => (int) round($fin['openstaand']), 'kleur' => App\Support\Statistiek::ROOD],
          ],
          'midden' => $fin['betaalgraad'].'%', 'middenLabel' => 'betaald',
        ])
      </div>
    </div>
    <div class="sis-chart-card">
      <h3>Financieel overzicht</h3><p class="sub">Actieve studenten</p>
      <table class="iuasr-dash-tbl" style="font-size:13px;">
        <tbody>
          <tr><td>Verschuldigd</td><td class="tnum" style="text-align:right;font-weight:600;">{{ $euro($fin['verschuldigd']) }}</td></tr>
          <tr><td>Betaald</td><td class="tnum" style="text-align:right;font-weight:600;color:var(--heritage-groen,#285C4D);">{{ $euro($fin['betaald']) }}</td></tr>
          <tr><td>Openstaand</td><td class="tnum" style="text-align:right;font-weight:600;color:var(--secColor100);">{{ $euro($fin['openstaand']) }}</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  @if ($isBestuur)
    <div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-top:16px;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M8.21 13.89 7 22l5-3 5 3-1.21-8.11"/></svg>
      <span>Als Schoolbestuur heeft u tevens inzage in <b>alle</b> digitaal ondertekende documenten van de organisatie.</span>
    </div>
  @endif

{{-- ========================= BEHEERDER ========================= --}}
@elseif ($rol === App\Enums\Rol::Beheerder)
  <div class="iuasr-dash-vhead">
    <div>
      <h1>Beheer</h1>
      <div class="summary">Gebruikers, rollen en referentiedata · audit-logging actief</div>
    </div>
    <div class="iuasr-dash-vhead__actions">
      <a class="iuasr-dash-btn" href="{{ route('audit-log') }}">Audit-log</a>
      <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('gebruikers') }}">Gebruikers beheren</a>
    </div>
  </div>
  <div class="iuasr-dash-stats">
    <div class="iuasr-dash-stat"><span class="lbl">Gebruikers</span><span class="val">{{ $kpi['gebruikers'] ?? '—' }}</span><span class="delta">alle rollen</span></div>
    <div class="iuasr-dash-stat"><span class="lbl">Studenten</span><span class="val">{{ $kpi['studenten'] }}</span><span class="delta">synthetisch</span></div>
    <div class="iuasr-dash-stat"><span class="lbl">Vakken</span><span class="val">{{ $kpi['vakken'] }}</span><span class="delta">actief</span></div>
    <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Audit-events</span><span class="val">{{ $kpi['audit'] ?? 0 }}</span><span class="delta">volledig gelogd</span></div>
  </div>
  <div class="sis-chartgrid" style="margin-top:16px;">
    <div class="sis-chart-card">
      <h3>Gebruikers per rol</h3><p class="sub">Toegang &amp; rolscheiding</p>
      @include('partials.charts.bar', ['data' => $stat['gebruikersPerRol'] ?? [], 'kleur' => 'var(--priColor200)'])
    </div>
  </div>
@endif

@endsection
