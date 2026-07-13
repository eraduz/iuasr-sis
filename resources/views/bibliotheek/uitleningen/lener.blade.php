@extends('layouts.app')

@section('titel', 'Lener')

@section('inhoud')
@php $magBeheer = auth()->user()->magBibliotheekBeheren(); @endphp

<div class="sis-crumb"><a href="{{ route('bibliotheek.dashboard') }}">Bibliotheek</a><span class="sep">›</span><a href="{{ route('bibliotheek.uitleningen') }}">Uitleningen</a><span class="sep">›</span><b>{{ $lener->volledigeNaam() }}</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>{{ $lener->volledigeNaam() }}</h1>
    <div class="summary">
      {{ $type === 'student' ? 'Student '.$lener->studentnummer : 'Medewerker' }}
      @if ($lener->email) · {{ $lener->email }}@endif
      @if ($lener->telefoon) · {{ $lener->telefoon }}@endif
    </div>
  </div>
</div>

<div class="iuasr-dash-stats">
  <div class="iuasr-dash-stat"><span class="lbl">Uitleningen</span><span class="val">{{ $uitleningen->count() }}</span><span class="delta">totaal</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Nu geleend</span><span class="val">{{ $uitleningen->whereNull('retour_op')->count() }}</span><span class="delta">niet retour</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Verzonden e-mails</span><span class="val">{{ $aantalMails }}</span><span class="delta">alle berichten</span></div>
</div>

<h2 style="margin:22px 0 10px;">Uitleningen</h2>
<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Publicatie</th><th>Uitgeleend</th><th>Retour verwacht</th><th>Status</th><th style="text-align:center;">Mails</th><th class="row-act"></th></tr></thead>
    <tbody>
      @forelse ($uitleningen as $u)
        <tr>
          <td class="nm" dir="auto">{{ $u->exemplaar->publicatie->volledigeTitel() }}<br><small class="sis-muted">{{ $u->exemplaar->serienummer }}</small></td>
          <td class="tnum">{{ $u->uitgeleend_op->format('d-m-Y') }}</td>
          <td class="tnum">{{ $u->verwachte_retour_op->format('d-m-Y') }}</td>
          <td>
            @if ($u->isRetour())
              <span class="iuasr-dash-status {{ $u->isOpTijdIngeleverd() ? 's-approved' : 's-incomplete' }}">Retour {{ $u->retour_op->format('d-m-Y') }}</span>
              @if ($u->staat)<br><small class="sis-muted">{{ $u->staat->label() }}</small>@endif
            @elseif ($u->isTeLaat())
              <span class="iuasr-dash-status s-rejected">{{ $u->dagenTeLaat() }} dagen te laat</span>
            @else
              <span class="iuasr-dash-status s-submitted">Uitgeleend</span>
            @endif
          </td>
          <td style="text-align:center;">{{ $u->emaillogs->count() }}</td>
          <td class="row-act">
            @if ($magBeheer && ! $u->isRetour())
              <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('bibliotheek.innemen', $u) }}">Innemen</a>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="6"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen uitleningen</h3></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<h2 style="margin:22px 0 10px;">Verzonden e-mails</h2>
<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Datum</th><th>Type</th><th>Ontvanger</th><th>CC</th><th>Verzonden</th></tr></thead>
    <tbody>
      @forelse ($uitleningen->flatMap->emaillogs->sortByDesc('verzonden_op') as $log)
        <tr>
          <td class="tnum">{{ $log->verzonden_op->format('d-m-Y H:i') }}</td>
          <td>{{ $log->soort->label() }}</td>
          <td>{{ $log->ontvanger }}</td>
          <td>{{ $log->cc ?? '—' }}</td>
          <td>
            <span class="iuasr-dash-status {{ $log->gelukt ? 's-approved' : 's-rejected' }}">{{ $log->gelukt ? 'Ja' : 'Nee' }}</span>
            @unless ($log->gelukt)<br><small class="sis-muted">{{ $log->foutmelding }}</small>@endunless
          </td>
        </tr>
      @empty
        <tr><td colspan="5"><div class="iuasr-dash-empty" style="border:0;"><h3>Nog geen e-mails</h3></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
