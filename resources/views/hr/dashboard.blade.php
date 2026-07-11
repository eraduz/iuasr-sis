@extends('layouts.app')

@section('titel', 'HR / Personeelszaken')

@section('inhoud')
@php use App\Enums\MedewerkerStatus; @endphp

<div class="iuasr-dash-vhead">
  <div><h1>HR / Personeelszaken</h1><div class="summary">Overzicht van uw {{ auth()->user()->isHrTeamBeperkt() ? 'team' : 'personeel' }}</div></div>
  <div class="iuasr-dash-vhead__actions"><a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('medewerkers') }}">Medewerkers</a></div>
</div>

<div class="iuasr-dash-stats" style="grid-template-columns:repeat(3,1fr);">
  <div class="iuasr-dash-stat"><span class="lbl">Actieve medewerkers</span><span class="val">{{ $aantal }}</span><span class="delta">{{ $statusVerdeling['uit_dienst'] ?? 0 }} uit dienst</span></div>
  <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Totaal FTE</span><span class="val">{{ number_format($fteTotaal, 2, ',', '.') }}</span><span class="delta">lopende dienstverbanden</span></div>
  <div class="iuasr-dash-stat {{ ($statusVerdeling['ziek'] ?? 0) > 0 ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Ziek gemeld</span><span class="val">{{ $statusVerdeling['ziek'] ?? 0 }}</span><span class="delta">{{ $statusVerdeling['verlof'] ?? 0 }} met verlof</span></div>
</div>

<div class="iuasr-dash-stats" style="grid-template-columns:repeat(4,1fr);margin-top:12px;">
  @foreach (MedewerkerStatus::cases() as $s)
    <div class="iuasr-dash-stat"><span class="lbl">{{ $s->label() }}</span><span class="val">{{ $statusVerdeling[$s->value] ?? 0 }}</span><span class="delta">medewerkers</span></div>
  @endforeach
</div>

<div class="sis-card" style="margin-top:16px;">
  <div class="sis-card__hd"><b>Aflopende contracten ({{ $aflopend->count() }})</b></div>
  @if ($aflopend->isEmpty())
    <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">Geen contracten die binnen 60 dagen aflopen.</p></div>
  @else
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Medewerker</th><th>Functie</th><th>Contract</th><th>Einddatum</th></tr></thead>
      <tbody>
        @foreach ($aflopend as $dv)
          <tr>
            <td class="nm"><a href="{{ route('medewerkers.show', $dv->medewerker) }}">{{ $dv->medewerker?->volledigeNaam() }}</a></td>
            <td>{{ $dv->functie?->naam ?? '—' }}</td>
            <td>{{ $dv->contracttype?->label() }}</td>
            <td class="dt">{{ $dv->einddatum?->format('d-m-Y') }} <span class="iuasr-dash-status s-requested">loopt af</span></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>

<div class="sis-card" style="margin-top:16px;">
  <div class="sis-card__hd"><b>Volgende fasen</b></div>
  <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">Verlof &amp; verzuim, gesprekken, self-service en HR-rapportages verschijnen hier zodra de bijbehorende fasen van de module HR / Personeelszaken zijn opgeleverd.</p></div>
</div>
@endsection
