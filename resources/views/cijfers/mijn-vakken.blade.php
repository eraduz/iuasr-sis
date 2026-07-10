@extends('layouts.app')

@section('titel', 'Mijn vakken')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Mijn vakken</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Mijn vakken</h1>
    <div class="summary">{{ auth()->user()->naam }} · <b>{{ $vakken->count() }}</b> vakken</div>
  </div>
</div>

<div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-bottom:16px;">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
  <span>U ziet uitsluitend <b>uw eigen vakken</b>. Cijfers van andere docenten en studentdossiers buiten uw vak zijn niet toegankelijk.</span>
</div>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Vak</th><th>Code</th><th>Opleiding</th><th>EC</th><th>Studenten</th><th>Onderdelen</th><th>Cijferstatus</th><th>Aanwezigheid</th><th class="row-act"></th></tr></thead>
    <tbody>
      @forelse ($vakken as $r)
        @php
          $vak = $r['vak']; $st = $r['status'];
          if ($st !== App\Enums\CijferlijstStatus::Concept) { $badge = $st->badge(); $tekst = $st->label(); }
          elseif ($r['aantal'] === 0) { $badge = 's-draft'; $tekst = 'Geen deelnemers'; }
          elseif ($r['ingevoerd'] === 0) { $badge = 's-draft'; $tekst = 'Nog niet gestart'; }
          elseif ($r['ingevoerd'] < $r['aantal']) { $badge = 's-incomplete'; $tekst = $r['ingevoerd'].'/'.$r['aantal'].' ingevoerd'; }
          else { $badge = 's-incomplete'; $tekst = 'Volledig — nog niet ingediend'; }
          $vergrendeld = $st !== App\Enums\CijferlijstStatus::Concept;

          $p = $r['presentie'];
          if ($p['deelnemers'] === 0) { $pBadge = 's-draft'; $pTekst = '—'; }
          elseif ($p['volledig']) { $pBadge = 's-approved'; $pTekst = 'Volledig'; }
          elseif ($p['weken_geregistreerd'] === 0) { $pBadge = 's-rejected'; $pTekst = 'Niet gestart'; }
          else { $pBadge = 's-incomplete'; $pTekst = $p['weken_geregistreerd'].'/'.$p['weken_totaal'].' weken'; }
        @endphp
        <tr>
          <td class="nm">{{ $vak->naam }}</td>
          <td class="tnum">{{ $vak->code }}</td>
          <td class="pg">{{ $vak->opleiding?->naam }}</td>
          <td class="tnum">{{ \App\Support\Ec::toon($vak->ec) }}</td>
          <td class="tnum">{{ $r['aantal'] }}</td>
          <td class="tnum">{{ $r['onderdelen'] }}</td>
          <td><span class="iuasr-dash-status {{ $badge }}">{{ $tekst }}</span></td>
          <td><span class="iuasr-dash-status {{ $pBadge }}">{{ $pTekst }}</span></td>
          <td class="row-act" style="white-space:nowrap;">
            <a class="iuasr-dash-btn iuasr-dash-btn--sm {{ $p['volledig'] || $p['deelnemers'] === 0 ? '' : 'iuasr-dash-btn--primary' }}" href="{{ route('vakken.presentie', $vak) }}">Aanwezigheid</a>
            <a class="iuasr-dash-btn iuasr-dash-btn--sm {{ $vergrendeld ? '' : 'iuasr-dash-btn--primary' }}" href="{{ route('vakken.cijfers', $vak) }}">{{ $vergrendeld ? 'Bekijken' : 'Cijfers invoeren' }}</a>
          </td>
        </tr>
      @empty
        <tr><td colspan="9"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen vakken gekoppeld</h3><p>Er zijn nog geen vakken aan uw docentprofiel gekoppeld.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<p class="sis-tblnote">Het eindcijfer is het gewogen gemiddelde van de deelresultaten; EC worden pas toegekend als álle meetellende onderdelen voldoende zijn (cesuur 5,5). Het <b>registreren van de aanwezigheid</b> tijdens college is verplicht: leg per onderwijsweek per student 1 (aanwezig) of 0 (afwezig) vast.</p>
@endsection
