@extends('layouts.app')

@section('titel', 'Signaleringen')

@section('inhoud')
@php
  // Mijlpaalstatus → statuskleur van het design system.
  $mijlpaalBadge = ['verstreken' => 's-rejected', 'binnenkort' => 's-requested', 'gepland' => 's-draft'];
  $teamnaam = auth()->user()->isHrTeamBeperkt() ? ' (eigen team)' : '';
@endphp

<div class="sis-crumb"><a href="{{ route('hr.dashboard') }}">HR</a><span class="sep">›</span><b>Signaleringen</b></div>

<div class="iuasr-dash-vhead">
  <div><h1>Signaleringen</h1><div class="summary">Aflopende contracten en verzuim{{ $teamnaam }}</div></div>
</div>

<div class="iuasr-dash-stats" style="grid-template-columns:repeat(3,1fr);">
  <div class="iuasr-dash-stat {{ $aflopend->count() > 0 ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Aflopende contracten</span><span class="val">{{ $aflopend->count() }}</span><span class="delta">binnen {{ $contractDagen }} dagen</span></div>
  <div class="iuasr-dash-stat {{ $langdurig->count() > 0 ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Langdurig verzuim</span><span class="val">{{ $langdurig->count() }}</span><span class="delta">Poortwachter-traject</span></div>
  <div class="iuasr-dash-stat {{ $frequent->count() > 0 ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Frequent verzuim</span><span class="val">{{ $frequent->count() }}</span><span class="delta">meerdere meldingen</span></div>
</div>

{{-- Aflopende contracten --}}
<div class="sis-card" style="margin-top:16px;">
  <div class="sis-card__hd"><b>Aflopende contracten ({{ $aflopend->count() }})</b></div>
  @if ($aflopend->isEmpty())
    <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">Geen contracten die binnen {{ $contractDagen }} dagen aflopen.</p></div>
  @else
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Medewerker</th><th>Functie</th><th>Contract</th><th>Einddatum</th><th style="text-align:right;">Nog</th></tr></thead>
      <tbody>
        @foreach ($aflopend as $dv)
          <tr>
            <td class="nm"><a href="{{ route('medewerkers.show', $dv->medewerker) }}">{{ $dv->medewerker?->volledigeNaam() }}</a></td>
            <td>{{ $dv->functie?->naam ?? '—' }}</td>
            <td>{{ $dv->contracttype?->label() ?? '—' }}</td>
            <td>{{ $dv->einddatum?->format('d-m-Y') }}</td>
            <td class="tnum" style="text-align:right;">{{ (int) now()->startOfDay()->diffInDays($dv->einddatum, false) }} dgn</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>

{{-- Langdurig verzuim — Wet Verbetering Poortwachter --}}
<div class="sis-card" style="margin-top:16px;">
  <div class="sis-card__hd"><b>Langdurig verzuim — Wet Verbetering Poortwachter ({{ $langdurig->count() }})</b></div>
  @if ($langdurig->isEmpty())
    <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">Geen open ziekmeldingen.</p></div>
  @else
    @foreach ($langdurig as $r)
      <div style="padding:14px 16px;border-top:1px solid var(--lineSoft, #eee);">
        <div style="display:flex;justify-content:space-between;align-items:baseline;gap:12px;flex-wrap:wrap;">
          <div>
            <a class="nm" href="{{ route('medewerkers.show', $r['medewerker']) }}"><b>{{ $r['medewerker']?->volledigeNaam() }}</b></a>
            <span class="sis-muted">· ziek sinds {{ $r['melding']->ziek_van?->format('d-m-Y') }} · week {{ $r['weken'] }} ({{ $r['dagen'] }} dagen)</span>
          </div>
          @if ($r['eerstvolgende'])
            <span class="sis-muted">Eerstvolgende: <b>{{ $r['eerstvolgende']['label'] }}</b> — {{ $r['eerstvolgende']['datum']->format('d-m-Y') }}</span>
          @else
            <span class="sis-muted">Alle mijlpalen verstreken</span>
          @endif
        </div>
        <table class="iuasr-dash-tbl" style="margin-top:8px;">
          <thead><tr><th>Mijlpaal</th><th>Uiterlijk</th><th>Datum</th><th style="text-align:center;">Status</th></tr></thead>
          <tbody>
            @foreach ($r['mijlpalen'] as $m)
              <tr>
                <td class="nm">{{ $m['label'] }}</td>
                <td>week {{ $m['week'] }}</td>
                <td>{{ $m['datum']->format('d-m-Y') }}</td>
                <td style="text-align:center;"><span class="iuasr-dash-status {{ $mijlpaalBadge[$m['status']] }}">{{ ucfirst($m['status']) }}</span></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endforeach
  @endif
</div>
<p class="sis-tblnote">De mijlpaaldata zijn afgeleid uit de eerste ziektedag volgens de Wet Verbetering Poortwachter; het systeem houdt geen afgehandelde status per mijlpaal bij. Raadpleeg de bedrijfsarts/arbodienst voor de formele re-integratie.</p>

{{-- Frequent verzuim --}}
<div class="sis-card" style="margin-top:16px;">
  <div class="sis-card__hd"><b>Frequent verzuim ({{ $frequent->count() }})</b></div>
  @if ($frequent->isEmpty())
    <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">Geen medewerkers met frequent verzuim.</p></div>
  @else
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Medewerker</th><th style="text-align:right;">Ziekmeldingen</th><th>Periode</th><th>Laatste melding</th></tr></thead>
      <tbody>
        @foreach ($frequent as $r)
          <tr>
            <td class="nm"><a href="{{ route('medewerkers.show', $r['medewerker']) }}">{{ $r['medewerker']?->volledigeNaam() }}</a></td>
            <td class="tnum" style="text-align:right;">{{ $r['aantal'] }}</td>
            <td>laatste {{ $r['maanden'] }} maanden</td>
            <td>{{ \Illuminate\Support\Carbon::parse($r['laatste'])->format('d-m-Y') }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>
<p class="sis-tblnote">Frequent verzuim signaleert medewerkers met meerdere losse ziekmeldingen in de periode — aanleiding voor een verzuimgesprek.</p>
@endsection
