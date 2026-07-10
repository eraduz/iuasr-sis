@extends('layouts.app')

@section('titel', 'Financiën · '.$student->studentnummer)

@php
  $euro = fn ($b) => '€ '.number_format($b, 2, ',', '.');
  $T = \App\Support\Collegegeldtermijnen::class;
@endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ route('financien') }}">Financiën</a><span class="sep">›</span><b>{{ $student->studentnummer }}</b></div>

<div class="iuasr-dash-candidate">
  <div class="iuasr-dash-candidate__hd" style="margin-bottom:0;border-bottom:0;padding-bottom:0;">
    <span class="iuasr-dash-candidate__avatar" aria-hidden="true">{{ mb_substr($student->voornaam,0,1) }}</span>
    <div class="iuasr-dash-candidate__body">
      <h2 class="iuasr-dash-candidate__name">{{ $student->volledigeNaam() }}</h2>
      <div class="iuasr-dash-candidate__meta"><span>Studentnr. <b>{{ $student->studentnummer }}</b></span></div>
    </div>
    @if ($status['achterstallig'] > 0)
      <span class="iuasr-dash-status s-rejected" style="align-self:flex-start;">Achterstand {{ $euro($status['achterstallig']) }}</span>
    @elseif ($status['terugbetaling'] > 0)
      <span class="iuasr-dash-status s-submitted" style="align-self:flex-start;">Terugbetaling {{ $euro($status['terugbetaling']) }}</span>
    @elseif ($status['openstaand'] > 0)
      <span class="iuasr-dash-status s-approved" style="align-self:flex-start;">Bij · nog {{ $euro($status['openstaand']) }} te factureren</span>
    @else
      <span class="iuasr-dash-status s-approved" style="align-self:flex-start;">Voldaan</span>
    @endif
  </div>
</div>

<div class="iuasr-dash-stats" style="grid-template-columns:repeat(4,1fr);">
  <div class="iuasr-dash-stat"><span class="lbl">Verschuldigd</span><span class="val" style="font-size:22px;">{{ $euro($status['verschuldigd']) }}</span><span class="delta">{{ $status['maanden'] }} {{ $status['maanden'] === 1 ? 'maand' : 'maanden' }} ingeschreven</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Betaald</span><span class="val" style="font-size:22px;">{{ $euro($status['betaald']) }}</span></div>
  <div class="iuasr-dash-stat {{ $status['achterstallig'] > 0 ? 'iuasr-dash-stat--alert' : 'iuasr-dash-stat--ok' }}">
    <span class="lbl">Achterstallig</span><span class="val" style="font-size:22px;">{{ $euro($status['achterstallig']) }}</span><span class="delta">vervallen termijnen</span>
  </div>
  @if ($status['terugbetaling'] > 0)
    <div class="iuasr-dash-stat iuasr-dash-stat--alert"><span class="lbl">Terug te betalen</span><span class="val" style="font-size:22px;">{{ $euro($status['terugbetaling']) }}</span><span class="delta">inschrijving beëindigd</span></div>
  @else
    <div class="iuasr-dash-stat"><span class="lbl">Nog te factureren</span><span class="val" style="font-size:22px;">{{ $euro($status['openstaand']) }}</span><span class="delta">rest van het studiejaar</span></div>
  @endif
</div>

@if ($errors->any())
  <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $errors->first() }}</span>
  </div>
@endif

{{-- Termijnschema per studiejaar: één klik per termijn om te boeken --}}
@forelse ($regels as $r)
  @php $insch = $r['inschrijving']; $termijnen = $r['termijnen']; @endphp
  <div class="sis-card" style="margin-bottom:16px;">
    <div class="sis-card__hd">
      <h3>{{ $insch->periode?->naam ?? 'Studiejaar' }} · {{ $insch->opleiding?->code }}</h3>
      <span class="hint">{{ $r['regeling']->label() }} · jaartarief {{ $r['tarief'] !== null ? $euro($r['tarief']) : 'niet ingesteld' }} · {{ $insch->status->label() }}</span>
    </div>

    @if ($termijnen->isEmpty())
      <p class="sis-muted" style="font-size:13px;margin:0;">Geen termijnen: geen tarief ingesteld, of de student is aangemeld maar nog niet ingeschreven.</p>
    @else
      <div class="iuasr-dash-tbl-card" style="border:0;">
        <table class="iuasr-dash-tbl sis-termijn-tbl">
          <thead>
            <tr>
              <th style="width:34px;">#</th><th>Termijn</th>
              <th style="text-align:center;">Vervaldatum</th>
              <th style="text-align:right;">Bedrag</th><th style="text-align:right;">Betaald</th><th style="text-align:right;">Open</th>
              <th style="text-align:center;">Status</th><th class="row-act">Boeken</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($termijnen as $t)
              <tr class="{{ $t['status'] === $T::VERVALLEN ? 'is-vervallen' : '' }}">
                <td class="tnum">{{ $t['nr'] }}</td>
                <td class="nm">{{ $t['naam'] }}</td>
                <td class="dt" style="text-align:center;">{{ $t['vervaldatum']->format('d-m-Y') }}</td>
                <td class="tnum" style="text-align:right;">{{ $t['vervallen'] ? '—' : $euro($t['bedrag']) }}</td>
                <td class="tnum" style="text-align:right;">{{ $t['betaald'] > 0 ? $euro($t['betaald']) : '—' }}</td>
                <td class="tnum" style="text-align:right;">{{ $t['open'] > 0 ? $euro($t['open']) : '—' }}</td>
                <td style="text-align:center;"><span class="iuasr-dash-status {{ $T::statusBadge($t['status']) }}">{{ $T::statusLabel($t['status']) }}</span></td>
                <td class="row-act">
                  @if ($t['open'] > 0 && ! $t['vervallen'])
                    <form method="POST" action="{{ route('financien.betaling', $student) }}" style="display:inline;">
                      @csrf
                      <input type="hidden" name="inschrijving_id" value="{{ $insch->id }}">
                      <input type="hidden" name="termijn" value="{{ $t['nr'] }}">
                      <input type="hidden" name="bedrag" value="{{ number_format($t['open'], 2, '.', '') }}">
                      <input type="hidden" name="datum" value="{{ now()->toDateString() }}">
                      <input type="hidden" name="betaalwijze" value="overboeking">
                      <input type="hidden" name="opmerking" value="Termijn {{ $t['nr'] }} voldaan">
                      <button class="iuasr-dash-btn iuasr-dash-btn--sm {{ $t['status'] === $T::ACHTERSTALLIG ? 'iuasr-dash-btn--primary' : '' }}" type="submit">
                        Boek {{ $euro($t['open']) }}
                      </button>
                    </form>
                  @else
                    <span class="sis-muted">—</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3"><b>Totaal</b></td>
              <td class="tnum" style="text-align:right;"><b>{{ $euro($termijnen->sum('bedrag')) }}</b></td>
              <td class="tnum" style="text-align:right;"><b>{{ $euro($termijnen->sum('betaald')) }}</b></td>
              <td class="tnum" style="text-align:right;"><b>{{ $euro($termijnen->sum('open')) }}</b></td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    @endif
  </div>
@empty
  <div class="sis-card"><p class="sis-muted" style="margin:0;">Geen inschrijvingen.</p></div>
@endforelse

<div class="sis-grid-2">
  <div class="sis-card">
    <div class="sis-card__hd"><h3>Geregistreerde betalingen</h3></div>
    <div class="iuasr-dash-tbl-card" style="border:0;">
      <table class="iuasr-dash-tbl">
        <thead><tr><th>Datum</th><th style="text-align:right;">Bedrag</th><th style="text-align:center;">Termijn</th><th>Wijze</th><th>Door</th></tr></thead>
        <tbody>
          @forelse ($student->betalingen->sortByDesc('datum') as $b)
            <tr>
              <td class="dt">{{ $b->datum?->format('d-m-Y') }}</td>
              <td class="tnum" style="text-align:right;">{{ $euro($b->bedrag) }}</td>
              <td style="text-align:center;">{{ $b->termijn ? 'Termijn '.$b->termijn : '— automatisch' }}</td>
              <td>{{ $b->betaalwijze ?? '—' }}</td>
              <td class="dt">{{ $b->geregistreerdDoor?->naam ?? '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="5" style="color:var(--blackAltText);padding:14px;">Nog geen betalingen geregistreerd.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div>
    <form method="POST" action="{{ route('financien.betaling', $student) }}" class="sis-card sis-form">
      @csrf
      <div class="sis-card__hd"><h3>Betaling registreren</h3><span class="hint">deelbetaling of afwijkend bedrag</span></div>
      <div class="sis-fld">
        <label>Studiejaar / inschrijving <span class="req">*</span></label>
        <select name="inschrijving_id" required>
          @foreach ($regels as $r)
            <option value="{{ $r['inschrijving']->id }}">{{ $r['inschrijving']->periode?->naam }} · {{ $r['inschrijving']->opleiding?->code }}</option>
          @endforeach
        </select>
      </div>
      <div class="sis-fld">
        <label>Termijn</label>
        <select name="termijn">
          <option value="">— automatisch: oudste openstaande termijn —</option>
          @for ($nr = 1; $nr <= 5; $nr++)
            <option value="{{ $nr }}" @selected(old('termijn') == $nr)>Termijn {{ $nr }}</option>
          @endfor
        </select>
      </div>
      <div class="sis-fld-row sis-fld-row--2">
        <div class="sis-fld"><label>Bedrag (€) <span class="req">*</span></label>
          <div class="sis-inputwrap"><span class="prefix">€</span><input type="number" step="0.01" min="0.01" name="bedrag" value="{{ old('bedrag') }}" required style="padding-left:26px;"></div>
        </div>
        <div class="sis-fld"><label>Datum <span class="req">*</span></label><input type="date" name="datum" value="{{ old('datum', now()->toDateString()) }}" required></div>
      </div>
      <div class="sis-fld">
        <label>Betaalwijze</label>
        <select name="betaalwijze">
          <option value="">— n.v.t. —</option>
          <option value="overboeking">Overboeking</option>
          <option value="incasso">Incasso</option>
          <option value="contant">Contant</option>
        </select>
      </div>
      <div class="sis-fld"><label>Opmerking</label><textarea name="opmerking" placeholder="Optioneel">{{ old('opmerking') }}</textarea></div>
      <div class="sis-form__actions"><span></span><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Betaling opslaan</button></div></div>
    </form>
  </div>
</div>

<p class="sis-tblnote">Er wordt gefactureerd in <b>september, november, januari, maart en mei</b>; kiest de student voor één factuur, dan vervalt het volledige jaarbedrag in september. Een termijn is <b>achterstallig</b> zodra de vervaldatum is verstreken en zij niet volledig is voldaan — de som daarvan is de betalingsachterstand. Schrijft een student zich tussentijds uit, dan <b>vervallen</b> de termijnen ná de uitschrijfdatum en wordt de laatste geldende termijn pro rata bijgesteld. Een betaling zonder termijnnummer wordt toegerekend aan de <b>oudste openstaande</b> termijn.</p>
@endsection
