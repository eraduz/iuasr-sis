@extends('layouts.app')

@section('titel', 'EC-rapport')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>EC-rapport</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>EC-rapport</h1>
    <div class="summary">Studievoortgang per opleiding/klas · cumulatief behaalde EC per student</div>
  </div>
</div>

<div class="iuasr-dash-stats" style="grid-template-columns:repeat(2,1fr);margin-bottom:16px;">
  <div class="iuasr-dash-stat"><span class="lbl">Studenten</span><span class="val">{{ $rijen->count() }}</span><span class="delta">in selectie</span></div>
  <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Gemiddeld behaald</span><span class="val">{{ $gemiddeld !== null ? $gemiddeld : '—' }}</span><span class="delta">EC per student</span></div>
</div>

<form method="GET" action="{{ route('ec-rapport') }}" class="iuasr-dash-filters" style="margin-bottom:16px;">
  <div class="sis-fld" style="margin:0;">
    <label>Opleiding</label>
    <select name="opleiding_id" onchange="this.form.submit()">
      <option value="">Alle opleidingen</option>
      @foreach ($opleidingen as $o)
        <option value="{{ $o->id }}" @selected($gekozenOpleiding == $o->id)>{{ $o->naam }}</option>
      @endforeach
    </select>
  </div>
  <div class="sis-fld" style="margin:0;">
    <label>Leerjaar</label>
    <select name="leerjaar" onchange="this.form.submit()">
      <option value="">Alle</option>
      @for ($j = 1; $j <= 4; $j++)<option value="{{ $j }}" @selected($gekozenLeerjaar == $j)>Jaar {{ $j }}</option>@endfor
    </select>
  </div>
  <div class="sis-fld" style="margin:0;">
    <label>Klas</label>
    <select name="klas_id" onchange="this.form.submit()">
      <option value="">Alle</option>
      @foreach ($klassen as $k)
        <option value="{{ $k->id }}" @selected($gekozenKlas == $k->id)>{{ $k->code }} ({{ $k->opleiding?->code }})</option>
      @endforeach
    </select>
  </div>
</form>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Studentnr.</th><th>Naam</th><th>Opleiding</th><th style="text-align:center;">Leerjaar</th><th>Klas</th><th style="text-align:right;">Behaald EC</th><th style="width:180px;">Voortgang</th></tr></thead>
    <tbody>
      @forelse ($rijen as $r)
        @php
          $i = $r['inschrijving']; $behaald = $r['behaald']; $totaal = $r['totaal'];
          $pct = $totaal ? min(100, round($behaald / $totaal * 100)) : null;
        @endphp
        <tr>
          <td class="tnum">{{ $i->student->studentnummer }}</td>
          <td class="nm">{{ $i->student->volledigeNaam() }}</td>
          <td>{{ $i->opleiding?->code }}</td>
          <td class="tnum" style="text-align:center;">{{ $i->leerjaar }}</td>
          <td>{{ $i->klas?->code ?? '—' }}</td>
          <td class="tnum" style="text-align:right;"><b>{{ $behaald }}</b>@if($totaal) <span class="sis-muted" style="font-size:11px;">/ {{ $totaal }}</span>@endif</td>
          <td>
            @if ($pct !== null)
              <div style="display:flex;align-items:center;gap:8px;">
                <div style="flex:1;height:8px;border-radius:6px;background:var(--borderSubtleColor,#eee);overflow:hidden;"><div style="width:{{ $pct }}%;height:100%;background:var(--heritage-groen,#285C4D);"></div></div>
                <span class="tnum" style="font-size:11px;color:var(--blackAltText);">{{ $pct }}%</span>
              </div>
            @else<span class="sis-muted" style="font-size:12px;">n.v.t.</span>@endif
          </td>
        </tr>
      @empty
        <tr><td colspan="7"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen actieve studenten</h3><p>Er zijn geen actieve inschrijvingen voor deze selectie.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<p class="sis-tblnote" style="margin-top:12px;">Behaalde EC = cumulatief over alle studiejaren (vak volledig gehaald). Voortgang = behaald t.o.v. het nominale totaal van de opleiding.</p>
@endsection
