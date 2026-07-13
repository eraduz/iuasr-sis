@extends('layouts.app')

@section('titel', $publicatie->volledigeTitel())

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('catalogus') }}">Bibliotheek IUASR</a><span class="sep">›</span><b>{{ \Illuminate\Support\Str::limit($publicatie->titel, 50) }}</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1 dir="auto">{{ $publicatie->volledigeTitel() }}</h1>
    <div class="summary">{{ $publicatie->soort->label() }} · {{ $publicatie->auteursTekst() }}</div>
  </div>
</div>

<div class="sis-card" style="margin-bottom:16px;">
  <table class="iuasr-dash-tbl">
    <tbody>
      <tr><td class="sis-muted" style="width:200px;">Rek / plaats</td><td class="tnum"><b style="font-size:15px;">{{ $publicatie->rekplaats() ?? '—' }}</b> <small class="sis-muted">— zo vindt u het boek in de bibliotheek</small></td></tr>
      <tr><td class="sis-muted">ISBN</td><td class="tnum">{{ $publicatie->isbn ?? '—' }}</td></tr>
      <tr><td class="sis-muted">Talen</td><td>{{ $publicatie->talenTekst() }}</td></tr>
      <tr><td class="sis-muted">Uitgavejaar</td><td class="tnum">{{ $publicatie->uitgavejaar ?? '—' }}</td></tr>
      <tr><td class="sis-muted">Druknummer</td><td>{{ $publicatie->druknummer ?? '—' }}</td></tr>
      <tr><td class="sis-muted">Vakgebied</td><td>{{ $publicatie->vakgebied?->naam ?? '—' }}</td></tr>
      @if ($publicatie->reeks)
        <tr><td class="sis-muted">Boekreeks</td><td>{{ $publicatie->reeks->titel }} — deel {{ $publicatie->deelnummer }}</td></tr>
      @endif
    </tbody>
  </table>
</div>

@if ($publicatie->soort->heeftExemplaren())
  <h2 style="margin:22px 0 10px;">Waar staat het?</h2>
  <div class="iuasr-dash-tbl-card">
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Serienummer</th><th>Kast / rek</th><th>Status</th></tr></thead>
      <tbody>
        @forelse ($publicatie->exemplaren as $ex)
          <tr>
            <td class="tnum">{{ $ex->serienummer }}</td>
            <td class="tnum">{{ $ex->kast?->code ?? '—' }}@if ($ex->kast?->omschrijving)<br><small class="sis-muted">{{ $ex->kast->omschrijving }}</small>@endif</td>
            <td><span class="iuasr-dash-status {{ $ex->status->badge() }}">{{ $ex->status->label() }}</span></td>
          </tr>
        @empty
          <tr><td colspan="3"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen exemplaren geregistreerd</h3></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <p class="sis-tblnote">Wilt u dit boek lenen, meld u dan bij de bibliotheek met het serienummer. Zij leggen de uitlening vast.</p>
@endif

@if ($publicatie->reeks && $publicatie->reeks->delen->count() > 1)
  <h2 style="margin:22px 0 10px;">Andere delen van deze reeks</h2>
  <div class="iuasr-dash-tbl-card">
    <table class="iuasr-dash-tbl">
      <thead><tr><th style="width:80px;">Deel</th><th>Titel</th><th style="text-align:center;">Beschikbaar</th></tr></thead>
      <tbody>
        @foreach ($publicatie->reeks->delen as $deel)
          <tr>
            <td class="tnum">{{ $deel->deelnummer }}</td>
            <td class="nm" dir="auto"><a href="{{ route('catalogus.show', $deel) }}">{{ $deel->titel }}</a></td>
            <td style="text-align:center;">{{ $deel->aantalBeschikbaar() }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif

@if ($publicatie->soort->heeftUitgaven() && $publicatie->uitgaven->isNotEmpty())
  <h2 style="margin:22px 0 10px;">Uitgaven en artikelen</h2>
  @foreach ($publicatie->uitgaven as $uitgave)
    <div class="sis-card" style="margin-bottom:10px;">
      <h3>{{ $uitgave->uitgavenummer }}@if ($uitgave->jaar) ({{ $uitgave->jaar }})@endif</h3>
      <table class="iuasr-dash-tbl">
        <thead><tr><th>Artikel</th><th>Auteur(s)</th><th>Pagina's</th></tr></thead>
        <tbody>
          @forelse ($uitgave->artikelen as $artikel)
            <tr>
              <td class="nm" dir="auto">{{ $artikel->titel }}</td>
              <td dir="auto">{{ $artikel->auteurs->pluck('naam')->implode(', ') ?: '—' }}</td>
              <td class="tnum">{{ $artikel->paginas ?? '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="3"><p class="sis-muted">Geen artikelen geregistreerd.</p></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  @endforeach
@endif

<div style="margin-top:12px;"><a class="iuasr-dash-btn" href="{{ route('catalogus') }}">Terug naar zoeken</a></div>
@endsection
