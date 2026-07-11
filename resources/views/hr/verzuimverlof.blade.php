@extends('layouts.app')

@section('titel', 'Verzuim & verlof')

@section('inhoud')
@php $teamnaam = auth()->user()->isHrTeamBeperkt() ? ' (eigen team)' : ''; @endphp

<div class="sis-crumb"><a href="{{ route('hr.dashboard') }}">HR</a><span class="sep">›</span><a href="{{ route('hr.rapport') }}">Rapportage</a><span class="sep">›</span><b>Verzuim &amp; verlof</b></div>

<div class="iuasr-dash-vhead">
  <div><h1>Verzuim &amp; verlof per medewerker</h1><div class="summary">Volg elke medewerker op ziekteverzuim en verlof · {{ $jaar }}{{ $teamnaam }}</div></div>
  <div class="iuasr-dash-vhead__actions"><a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('hr.verzuimverlof.export', ['jaar' => $jaar, 'afdeling' => $afdelingId]) }}">Exporteren (CSV)</a></div>
</div>

<form method="GET" action="{{ route('hr.verzuimverlof') }}" class="sis-toolbar" style="margin-bottom:16px;">
  <label>Jaar
    <select name="jaar" onchange="this.form.submit()">
      @foreach ($jaren as $j)<option value="{{ $j }}" @selected($j === $jaar)>{{ $j }}</option>@endforeach
    </select>
  </label>
  <label>Afdeling
    <select name="afdeling" onchange="this.form.submit()">
      <option value="">Alle afdelingen</option>
      @foreach ($afdelingen as $a)<option value="{{ $a->id }}" @selected((string) $a->id === (string) $afdelingId)>{{ $a->naam }}</option>@endforeach
    </select>
  </label>
  <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Toon</button>
</form>

<div class="iuasr-dash-stats" style="grid-template-columns:repeat(4,1fr);">
  <div class="iuasr-dash-stat"><span class="lbl">Medewerkers</span><span class="val">{{ $totalen['medewerkers'] }}</span><span class="delta">in beeld</span></div>
  <div class="iuasr-dash-stat {{ $totalen['momenteel_ziek'] > 0 ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Nu ziek gemeld</span><span class="val">{{ $totalen['momenteel_ziek'] }}</span><span class="delta">{{ $totalen['ziektedagen'] }} ziektedagen ({{ $jaar }})</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Gemiddeld verzuim</span><span class="val">{{ number_format($totalen['verzuim'], 1, ',', '.') }}%</span><span class="delta">kalenderdag-gebaseerd</span></div>
  <div class="iuasr-dash-stat {{ $totalen['verlof_open'] > 0 ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Openstaande verlofaanvragen</span><span class="val">{{ $totalen['verlof_open'] }}</span><span class="delta"><a href="{{ route('verlof') }}">beoordelen</a></span></div>
</div>

<div class="iuasr-dash-tbl-card" style="margin-top:16px;">
  <table class="iuasr-dash-tbl">
    <thead>
      <tr>
        <th rowspan="2">Medewerker</th>
        <th rowspan="2">Afdeling</th>
        <th rowspan="2">Status</th>
        <th colspan="3" style="text-align:center;border-left:1px solid var(--borderColor);">Verzuim ({{ $jaar }})</th>
        <th colspan="4" style="text-align:center;border-left:1px solid var(--borderColor);">Verlof in uren ({{ $jaar }})</th>
      </tr>
      <tr>
        <th style="text-align:right;border-left:1px solid var(--borderColor);">Meldingen</th>
        <th style="text-align:right;">Ziektedagen</th>
        <th style="text-align:right;">Verzuim%</th>
        <th style="text-align:right;border-left:1px solid var(--borderColor);">Recht</th>
        <th style="text-align:right;">Opgenomen</th>
        <th style="text-align:right;">Saldo</th>
        <th style="text-align:right;">Open</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($rijen as $r)
        <tr>
          <td class="nm"><a href="{{ route('medewerkers.show', $r['id']) }}#verzuim">{{ $r['naam'] }}</a><br><small class="sis-muted">{{ $r['personeelsnummer'] }}</small></td>
          <td>{{ $r['afdeling'] }}</td>
          <td>@if ($r['status'])<span class="iuasr-dash-status {{ $r['status']->badge() }}">{{ $r['status']->label() }}</span>@else — @endif</td>
          <td class="tnum" style="text-align:right;border-left:1px solid var(--borderColor);">{{ $r['ziek_meldingen'] }}</td>
          <td class="tnum" style="text-align:right;">{{ $r['ziektedagen'] }}</td>
          <td class="tnum" style="text-align:right;">{{ number_format($r['verzuim'], 1, ',', '.') }}%</td>
          <td class="tnum" style="text-align:right;border-left:1px solid var(--borderColor);">{{ number_format($r['verlof_recht'], 1, ',', '.') }}</td>
          <td class="tnum" style="text-align:right;">{{ number_format($r['verlof_opgenomen'], 1, ',', '.') }}</td>
          <td class="tnum" style="text-align:right;{{ $r['verlof_saldo'] < 0 ? 'color:var(--secColor100);' : '' }}">{{ number_format($r['verlof_saldo'], 1, ',', '.') }}</td>
          <td class="tnum" style="text-align:right;">@if ($r['verlof_open'] > 0)<b>{{ $r['verlof_open'] }}</b>@else 0 @endif</td>
        </tr>
      @empty
        <tr><td colspan="10"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen medewerkers</h3></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<p class="sis-tblnote">Verzuim% is kalenderdag-gebaseerd: ziektedagen ÷ verstreken kalenderdagen in het jaar. Verlof telt alle verloftypen samen; alleen goedgekeurd verlof telt als opgenomen. Klik op een naam voor het volledige verzuim- en verlofverloop op de medewerkerkaart.</p>
@endsection
