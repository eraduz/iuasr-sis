@extends('layouts.app')

@section('titel', 'Mijn verlof')

@section('inhoud')
<div class="iuasr-dash-vhead">
  <div><h1>Mijn verlof</h1><div class="summary">{{ $medewerker->volledigeNaam() }} · {{ $jaar }}</div></div>
  <div class="iuasr-dash-vhead__actions"><a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('verlof.create') }}">Verlof aanvragen</a></div>
</div>

<div class="sis-card" style="margin-bottom:16px;">
  <div class="sis-card__hd"><b>Saldo {{ $jaar }}</b></div>
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
</div>

<div class="sis-card">
  <div class="sis-card__hd"><b>Mijn aanvragen ({{ $aanvragen->count() }})</b></div>
  @if ($aanvragen->isEmpty())
    <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">U hebt nog geen verlof aangevraagd.</p></div>
  @else
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Type</th><th>Van</th><th>Tot</th><th style="text-align:right;">Uren</th><th style="text-align:center;">Status</th><th>Opmerking</th><th class="row-act"></th></tr></thead>
      <tbody>
        @foreach ($aanvragen as $a)
          <tr>
            <td>{{ $a->verloftype?->label() }}</td>
            <td class="dt">{{ $a->van?->format('d-m-Y') }}</td>
            <td class="dt">{{ $a->tot?->format('d-m-Y') }}</td>
            <td class="tnum" style="text-align:right;">{{ number_format((float) $a->uren, 1, ',', '.') }}</td>
            <td style="text-align:center;"><span class="iuasr-dash-status {{ $a->status?->badge() }}">{{ $a->status?->label() }}</span></td>
            <td><small class="sis-muted">{{ $a->opmerking_beoordelaar ?? '—' }}</small></td>
            <td class="row-act">
              @if ($a->status->value === 'aangevraagd')
                <form method="POST" action="{{ route('verlof.intrekken', $a) }}" onsubmit="return confirm('Aanvraag intrekken?');" style="display:inline;">@csrf<button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Intrekken</button></form>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>
@endsection
