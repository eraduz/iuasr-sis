@extends('layouts.app')

@section('titel', 'Migratie uit Access')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Migratie (import)</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Migratie uit de oude database</h1>
    <div class="summary">Zet studenten en cijfers uit het oude Access-systeem over naar het SIS via de per-jaar geëxporteerde CSV-bestanden. <b>Tijdelijk hulpmiddel.</b></div>
  </div>
</div>

<div class="iuasr-dash-alert iuasr-dash-alert--warn" style="margin-bottom:16px;">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
  <span>Doe <b>altijd eerst een controle (preview)</b> vóór het echt importeren. Bestaande studentnummers worden nooit overschreven. Draai dit bij voorkeur eerst op de testomgeving.</span>
</div>

<div class="sis-card" style="margin-bottom:18px;">
  <div class="sis-card__hd"><h3>Studenten importeren</h3><span class="hint">Upload het bestand <code>_studenten.csv</code> uit de export</span></div>
  <form method="POST" action="{{ route('migratie.verwerk') }}" enctype="multipart/form-data" style="display:flex;flex-wrap:wrap;gap:14px 20px;align-items:flex-end;">
    @csrf
    <input type="hidden" name="type" value="studenten">
    <label style="display:flex;flex-direction:column;gap:4px;font-size:12px;">CSV-bestand (studenten)
      <input type="file" name="bestand" accept=".csv,text/csv" required style="font-size:13px;">
    </label>
    <label class="sis-check-inline" style="font-size:13px;"><input type="radio" name="modus" value="preview" checked> Controle (preview — schrijft niets)</label>
    <label class="sis-check-inline" style="font-size:13px;"><input type="radio" name="modus" value="import"> Nu importeren</label>
    <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--primary">Uitvoeren</button>
  </form>
  @error('bestand')<p class="sis-tblnote" style="color:var(--secColor100);">{{ $message }}</p>@enderror
  <p class="sis-tblnote" style="margin-top:10px;">Gemapt: <b>SDT-NR → studentnummer</b>, naam, geboortedatum/-plaats, nationaliteit, adres, e-mail, vooropleiding en diploma. BSN/IBAN worden <b>niet</b> meegenomen. Cijfers en inschrijvingen volgen in een aparte stap.</p>
</div>

@if ($rapport !== null)
  <div class="sis-card" style="border-left:3px solid {{ $modus === 'import' ? 'var(--priColor200,#285C4D)' : 'var(--priColor300,#D69A2D)' }};">
    <div class="sis-card__hd">
      <h3>{{ $modus === 'import' ? 'Import uitgevoerd' : 'Controle (preview)' }}</h3>
      <span class="hint">{{ $bestandsnaam ?? '' }} · {{ $rapport['totaal'] }} rijen gelezen</span>
    </div>
    <div class="iuasr-dash-stats" style="margin-bottom:14px;">
      <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">{{ $modus === 'import' ? 'Aangemaakt' : 'Nieuw (worden aangemaakt)' }}</span><span class="val">{{ $rapport['nieuw'] }}</span></div>
      <div class="iuasr-dash-stat"><span class="lbl">Overgeslagen (bestaat al)</span><span class="val">{{ $rapport['overgeslagen'] }}</span></div>
      <div class="iuasr-dash-stat"><span class="lbl">Lege/junk-rijen</span><span class="val">{{ $rapport['leeg'] ?? 0 }}</span></div>
      <div class="iuasr-dash-stat {{ count($rapport['fouten']) ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Fouten</span><span class="val">{{ count($rapport['fouten']) }}</span></div>
    </div>

    @if ($modus === 'preview' && $rapport['nieuw'] > 0)
      <div class="iuasr-dash-alert iuasr-dash-alert--ok" style="margin-bottom:14px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><polyline points="20 6 9 17 4 12"/></svg>
        <span>Ziet dit er goed uit? Kies dan hierboven <b>Nu importeren</b> en upload hetzelfde bestand opnieuw.</span>
      </div>
    @endif

    @if ($rapport['voorbeeld'])
      <div class="iuasr-dash-tbl-card" style="border:0;">
        <table class="iuasr-dash-tbl">
          <thead><tr><th>Studentnummer</th><th>Naam</th><th>Geboortedatum</th><th>Diploma</th></tr></thead>
          <tbody>
            @foreach ($rapport['voorbeeld'] as $v)
              <tr><td class="nm">{{ $v['studentnummer'] }}</td><td>{{ $v['naam'] }}</td><td class="dt">{{ $v['geboortedatum'] ?: '—' }}</td><td>{{ $v['diploma'] }}</td></tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <p class="sis-tblnote">Voorbeeld van de eerste {{ count($rapport['voorbeeld']) }} nieuwe studenten.</p>
    @endif

    @if ($rapport['fouten'])
      <div class="sis-card" style="margin-top:14px;background:#fdecec;">
        <div class="sis-card__hd"><h3 style="color:var(--secColor100);">Fouten ({{ count($rapport['fouten']) }})</h3></div>
        <ul style="margin:0;font-size:12.5px;color:var(--secColor100);">
          @foreach (array_slice($rapport['fouten'], 0, 30) as $fout)<li>{{ $fout }}</li>@endforeach
        </ul>
        @if (count($rapport['fouten']) > 30)<p class="sis-tblnote">… en nog {{ count($rapport['fouten']) - 30 }} meer.</p>@endif
      </div>
    @endif
  </div>
@endif
@endsection
