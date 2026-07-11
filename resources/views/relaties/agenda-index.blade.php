@extends('layouts.app')

@section('titel', 'Agenda & taken')

@section('inhoud')
<div class="sis-crumb"><b>Relatiebeheer</b><span class="sep">›</span><b>Agenda &amp; taken</b></div>

<div class="iuasr-dash-vhead">
  <div><h1>Agenda &amp; taken</h1><div class="summary">Aankomende afspraken en openstaande taken binnen uw bereik</div></div>
  <div class="iuasr-dash-vhead__actions"><a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('relatiebeheer.agenda.ics') }}">Exporteren naar agenda (.ics)</a></div>
</div>

<div class="sis-card" style="margin-bottom:16px;">
  <div class="sis-card__hd"><b>Aankomende afspraken ({{ $afspraken->count() }})</b></div>
  @if ($afspraken->isEmpty())
    <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">Geen geplande afspraken.</p></div>
  @else
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Datum</th><th>Tijd</th><th>Type</th><th>Organisatie</th><th>Onderwerp</th><th>Door</th></tr></thead>
      <tbody>
        @foreach ($afspraken as $af)
          <tr>
            <td class="dt">{{ $af->datum?->format('d-m-Y') }}</td>
            <td class="dt">{{ $af->tijd_van ? \Illuminate\Support\Str::of($af->tijd_van)->substr(0,5) : '—' }}</td>
            <td>{{ $af->type?->label() ?? '—' }}</td>
            <td class="nm"><a href="{{ route('relaties.show', $af->organisatie) }}#agenda">{{ $af->organisatie?->naam }}</a></td>
            <td>{{ $af->omschrijving ? \Illuminate\Support\Str::limit($af->omschrijving, 60) : '—' }}</td>
            <td>{{ $af->medewerker?->naam ?? '—' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>

<div class="sis-card" style="margin-bottom:16px;">
  <div class="sis-card__hd"><b>Contracten die verlopen ({{ $overeenkomsten->count() }})</b></div>
  @if ($overeenkomsten->isEmpty())
    <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">Geen aflopende overeenkomsten binnen 60 dagen.</p></div>
  @else
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Organisatie</th><th>Type</th><th>Verloopt</th><th style="text-align:center;">Status</th></tr></thead>
      <tbody>
        @foreach ($overeenkomsten as $ovk)
          <tr>
            <td class="nm"><a href="{{ route('relaties.show', $ovk->organisatie) }}#overeenkomsten">{{ $ovk->organisatie?->naam }}</a></td>
            <td>{{ $ovk->type?->label() }}</td>
            <td class="dt">{{ $ovk->verloopdatum?->format('d-m-Y') }} @if($ovk->isVerlopen())<span class="iuasr-dash-status s-rejected">Verlopen</span>@else<span class="iuasr-dash-status s-requested">{{ $ovk->dagenTotVerloop() }} dagen</span>@endif</td>
            <td style="text-align:center;"><span class="iuasr-dash-status {{ $ovk->status?->badge() }}">{{ $ovk->status?->label() }}</span></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>

<div class="sis-card">
  <div class="sis-card__hd"><b>Openstaande taken ({{ $taken->count() }})</b></div>
  @if ($taken->isEmpty())
    <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">Geen openstaande taken.</p></div>
  @else
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Taak</th><th>Organisatie</th><th>Toegewezen</th><th>Prioriteit</th><th>Vervaldatum</th><th style="text-align:center;">Status</th></tr></thead>
      <tbody>
        @foreach ($taken as $taak)
          <tr>
            <td class="nm">{{ $taak->titel }}</td>
            <td><a href="{{ route('relaties.show', $taak->organisatie) }}#taken">{{ $taak->organisatie?->naam }}</a></td>
            <td>{{ $taak->toegewezenAan?->naam ?? 'Vrij' }}</td>
            <td>{{ $taak->prioriteit->label() }}</td>
            <td class="dt">{{ $taak->vervaldatum?->format('d-m-Y') ?? '—' }} @if($taak->isTeLaat())<span class="iuasr-dash-status s-rejected">Te laat</span>@endif</td>
            <td style="text-align:center;"><span class="iuasr-dash-status {{ $taak->status->badge() }}">{{ $taak->status->label() }}</span></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>
@endsection
