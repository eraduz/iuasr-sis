@extends('layouts.app')

@section('titel', 'Mijn HR')

@php
    $aankomend = $gesprekken->filter(fn ($g) => $g->status->value === 'gepland')->sortBy('datum');
    $historie = $gesprekken->filter(fn ($g) => $g->status->value !== 'gepland')->sortByDesc('datum');
    $checklistPerSoort = $checklisttaken->groupBy('soort');
@endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('modules.kiezen') }}">Platform</a><span class="sep">›</span><b>Mijn HR</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Mijn HR</h1>
    <div class="summary">{{ $medewerker->volledigeNaam() }} · {{ $medewerker->personeelsnummer }} · uw eigen personeelsdossier (alleen-lezen)</div>
  </div>
  <div class="iuasr-dash-vhead__actions">
    <a class="iuasr-dash-btn" href="{{ route('hr.mijn.agenda') }}">Agenda (iCal)</a>
    <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('verlof.create') }}">Verlof aanvragen</a>
  </div>
</div>

{{-- Mijn gegevens & dienstverband --}}
<div class="sis-card" style="margin-bottom:16px;">
  <div class="sis-card__hd"><b>Mijn gegevens</b></div>
  <table class="iuasr-dash-tbl">
    <tbody>
      <tr><th style="width:38%;">Functie</th><td>{{ $medewerker->functie?->naam ?? '—' }}</td></tr>
      <tr><th>Afdeling</th><td>{{ $medewerker->afdeling?->naam ?? '—' }}</td></tr>
      <tr><th>Leidinggevende</th><td>{{ $medewerker->manager?->volledigeNaam() ?? '—' }}</td></tr>
      <tr><th>Status</th><td><span class="iuasr-dash-status {{ $medewerker->status?->badge() }}">{{ $medewerker->status?->label() }}</span>@if ($medewerker->status === \App\Enums\MedewerkerStatus::UitDienst && $medewerker->uit_dienst_datum) <span class="sis-muted">per {{ $medewerker->uit_dienst_datum->format('d-m-Y') }}</span>@endif</td></tr>
      @if ($huidig)
        <tr><th>Contract</th><td>{{ $huidig->contracttype?->label() }} · {{ number_format((float) $huidig->uren_per_week, 1, ',', '.') }} uur/week · {{ number_format($huidig->fte(), 2, ',', '.') }} FTE</td></tr>
        <tr><th>In dienst sinds</th><td class="dt">{{ $huidig->startdatum?->format('d-m-Y') }}@if ($huidig->einddatum) <span class="sis-muted">t/m {{ $huidig->einddatum->format('d-m-Y') }}</span>@endif</td></tr>
      @else
        <tr><th>Contract</th><td class="sis-muted">Nog geen dienstverband vastgelegd.</td></tr>
      @endif
    </tbody>
  </table>
</div>

{{-- Verlofsaldo --}}
<div class="sis-card" style="margin-bottom:16px;">
  <div class="sis-card__hd"><b>Verlofsaldo {{ $jaar }}</b></div>
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Type</th><th style="text-align:right;">Recht (uren)</th><th style="text-align:right;">Opgenomen</th><th style="text-align:right;">Saldo</th></tr></thead>
    <tbody>
      @foreach ($saldo as $rij)
        <tr>
          <td>{{ $rij['type']->label() }}</td>
          <td class="tnum" style="text-align:right;">{{ number_format($rij['recht'], 1, ',', '.') }}</td>
          <td class="tnum" style="text-align:right;">{{ number_format($rij['opgenomen'], 1, ',', '.') }}</td>
          <td class="tnum" style="text-align:right;"><b>{{ number_format($rij['saldo'], 1, ',', '.') }}</b></td>
        </tr>
      @endforeach
    </tbody>
  </table>
  <div style="padding:10px 16px;"><a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('verlof.mijn') }}">Mijn verlofaanvragen beheren</a></div>
</div>

{{-- Mijn gesprekken --}}
<div class="sis-card" style="margin-bottom:16px;">
  <div class="sis-card__hd"><b>Mijn gesprekken ({{ $gesprekken->count() }})</b></div>
  @if ($gesprekken->isEmpty())
    <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">Er zijn nog geen gesprekken voor u vastgelegd.</p></div>
  @else
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Datum</th><th>Type</th><th>Gespreksvoerder</th><th style="text-align:center;">Status</th></tr></thead>
      <tbody>
        @foreach ($aankomend->concat($historie) as $g)
          <tr>
            <td class="dt">{{ $g->datum?->format('d-m-Y') }}</td>
            <td>{{ $g->type?->label() }}</td>
            <td>{{ $g->gespreksvoerder?->naam ?? '—' }}</td>
            <td style="text-align:center;"><span class="iuasr-dash-status {{ $g->status?->badge() }}">{{ $g->status?->label() }}</span></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>

{{-- Mijn documenten --}}
<div class="sis-card" style="margin-bottom:16px;">
  <div class="sis-card__hd"><b>Mijn documenten ({{ $documenten->count() }})</b></div>
  @if ($documenten->isEmpty())
    <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">Er zijn nog geen documenten aan uw dossier gekoppeld.</p></div>
  @else
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Categorie</th><th>Bestand</th><th class="dt">Toegevoegd</th><th class="row-act"></th></tr></thead>
      <tbody>
        @foreach ($documenten as $doc)
          <tr>
            <td>{{ $doc->categorieLabel() }}</td>
            <td>{{ $doc->titel ?: $doc->bestandsnaam }}</td>
            <td class="dt">{{ $doc->created_at?->format('d-m-Y') }}</td>
            <td class="row-act"><a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('hr.mijn.document', $doc) }}">Downloaden</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>

{{-- Mijn checklists --}}
@if ($checklisttaken->isNotEmpty())
  <div class="sis-card">
    <div class="sis-card__hd"><b>Mijn checklists</b></div>
    <div style="padding:6px 16px 14px;">
      @foreach ($checklistPerSoort as $soort => $taken)
        @php $gereed = $taken->where('gereed', true)->count(); @endphp
        <p style="margin:12px 0 6px;"><b>{{ ucfirst($soort) }}</b> <span class="sis-muted">({{ $gereed }}/{{ $taken->count() }} gereed)</span></p>
        <ul style="margin:0 0 8px;padding-left:18px;">
          @foreach ($taken as $taak)
            <li style="margin:2px 0;">
              @if ($taak->gereed)<span class="iuasr-dash-status ok">gereed</span>@else<span class="iuasr-dash-status">open</span>@endif
              {{ $taak->titel }}
              @if ($taak->gereed && $taak->gereed_op) <small class="sis-muted">· {{ $taak->gereed_op->format('d-m-Y') }}</small>@endif
            </li>
          @endforeach
        </ul>
      @endforeach
    </div>
  </div>
@endif
@endsection
