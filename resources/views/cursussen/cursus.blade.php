@extends('layouts.app')

@section('titel', 'Cursus · '.$cursus->naam)

@php
    use App\Support\Cursusgeldstatus;
    $euro = fn ($b) => '€ '.number_format((float) $b, 2, ',', '.');
    $gebruiker = auth()->user();
    $magInzien = $gebruiker->magCursusInzien();
    $inschrijvingen = $cursus->inschrijvingen->sortBy(fn ($i) => $i->cursist?->achternaam);
@endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('modules.kiezen') }}">Platform</a><span class="sep">›</span><a href="{{ route('cursussen.dashboard') }}">Cursussen</a><span class="sep">›</span><b>{{ $cursus->naam }}</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>{{ $cursus->naam }}</h1>
    <div class="summary">Cursuscode <b>{{ $cursus->code }}</b> · cursusgeld {{ $euro($cursus->cursusgeld) }} · <span class="iuasr-dash-status {{ $cursus->actief ? 's-approved' : 's-draft' }}">{{ $cursus->actief ? 'Actief' : 'Inactief' }}</span></div>
  </div>
  <div class="iuasr-dash-vhead__actions">
    <a class="iuasr-dash-btn" href="{{ route('cursussen.rapport') }}">Rapportage</a>
    @if ($gebruiker->magCursusFinancien())
      <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('cursussen.betalingen', ['cursus' => $cursus->id]) }}">Cursusgelden</a>
    @endif
    @if ($cursus->beheerbaarVoor($gebruiker))
      <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('cursussen.edit', $cursus) }}">Cursus bewerken</a>
    @endif
  </div>
</div>

<div class="iuasr-dash-stats" style="grid-template-columns:repeat(5,1fr);margin-bottom:16px;">
  <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Actieve inschrijvingen</span><span class="val">{{ $perStatus['actief'] }}</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Totaal inschrijvingen</span><span class="val">{{ $cursus->inschrijvingen->count() }}</span><span class="delta">{{ $perStatus['afgerond'] }} afgerond · {{ $perStatus['geannuleerd'] }} geannuleerd</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Cursusgeld voldaan</span><span class="val">{{ $financieel['betaalgraad'] }}%</span></div>
  <div class="iuasr-dash-stat {{ $financieel['openstaand'] > 0 ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Openstaand</span><span class="val" style="font-size:20px;">{{ $euro($financieel['openstaand']) }}</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Ontvangen</span><span class="val" style="font-size:20px;">{{ $euro($financieel['betaald']) }}</span></div>
</div>

<div class="iuasr-dash-tbl-card">
  <div class="sis-card__hd" style="padding:14px 16px 0;"><h3 style="margin:0;">Cursisten op deze cursus</h3></div>
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Cursistnr.</th><th>Cursist</th><th style="text-align:right;">Bedrag</th><th style="text-align:center;">Betaling</th><th style="text-align:center;">Inschrijving</th>@if($magInzien)<th class="row-act"></th>@endif</tr></thead>
    <tbody>
      @forelse ($inschrijvingen as $i)
        @php $g = Cursusgeldstatus::voor($i); @endphp
        <tr>
          <td class="tnum">{{ $i->cursist?->cursistnummer ?? '—' }}</td>
          <td class="nm">{{ $i->cursist?->volledigeNaam() ?? '—' }}</td>
          <td class="tnum" style="text-align:right;">{{ $euro($i->totaalbedrag) }}</td>
          <td style="text-align:center;">
            <span class="iuasr-dash-status {{ Cursusgeldstatus::statusBadge($g['status']) }}">{{ Cursusgeldstatus::statusLabel($g['status']) }}</span>
            @if ($g['openstaand'] > 0)<small style="display:block;color:var(--blackAltText);">open: {{ $euro($g['openstaand']) }}</small>@endif
          </td>
          <td style="text-align:center;"><span class="iuasr-dash-status {{ $i->status->badge() }}">{{ $i->status->label() }}</span></td>
          @if ($magInzien)
            <td class="row-act">
              @if ($i->cursist)<a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('cursisten.show', $i->cursist) }}">Openen</a>@endif
            </td>
          @endif
        </tr>
      @empty
        <tr><td colspan="{{ $magInzien ? 6 : 5 }}"><div class="iuasr-dash-empty" style="border:0;"><h3>Nog geen cursisten</h3><p>Er zijn nog geen inschrijvingen op deze cursus.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
