@extends('layouts.app')

@section('titel', 'Financiën · '.$student->studentnummer)

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ route('financien') }}">Financiën</a><span class="sep">›</span><b>{{ $student->studentnummer }}</b></div>

<div class="iuasr-dash-candidate">
  <div class="iuasr-dash-candidate__hd" style="margin-bottom:0;border-bottom:0;padding-bottom:0;">
    <span class="iuasr-dash-candidate__avatar" aria-hidden="true">{{ mb_substr($student->voornaam,0,1) }}</span>
    <div class="iuasr-dash-candidate__body">
      <h2 class="iuasr-dash-candidate__name">{{ $student->volledigeNaam() }}</h2>
      <div class="iuasr-dash-candidate__meta"><span>Studentnr. <b>{{ $student->studentnummer }}</b></span></div>
    </div>
    @if ($status['openstaand'] > 0)
      <span class="iuasr-dash-status s-rejected" style="align-self:flex-start;">Nog te betalen € {{ number_format($status['openstaand'], 2, ',', '.') }}</span>
    @elseif ($status['terugbetaling'] > 0)
      <span class="iuasr-dash-status s-submitted" style="align-self:flex-start;">Terugbetaling € {{ number_format($status['terugbetaling'], 2, ',', '.') }}</span>
    @else
      <span class="iuasr-dash-status s-approved" style="align-self:flex-start;">Voldaan</span>
    @endif
  </div>
</div>

<div class="iuasr-dash-stats" style="grid-template-columns:repeat(3,1fr);">
  <div class="iuasr-dash-stat"><span class="lbl">Verschuldigd (pro rata)</span><span class="val" style="font-size:22px;">€ {{ number_format($status['verschuldigd'], 2, ',', '.') }}</span><span class="delta">{{ $status['maanden'] }} {{ $status['maanden'] === 1 ? 'maand' : 'maanden' }} ingeschreven</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Betaald</span><span class="val" style="font-size:22px;">€ {{ number_format($status['betaald'], 2, ',', '.') }}</span></div>
  @if ($status['terugbetaling'] > 0)
    <div class="iuasr-dash-stat iuasr-dash-stat--alert"><span class="lbl">Terug te betalen</span><span class="val" style="font-size:22px;">€ {{ number_format($status['terugbetaling'], 2, ',', '.') }}</span><span class="delta">teveel betaald</span></div>
  @else
    <div class="iuasr-dash-stat {{ $status['achterstand'] ? 'iuasr-dash-stat--alert' : 'iuasr-dash-stat--ok' }}"><span class="lbl">Openstaand</span><span class="val" style="font-size:22px;">€ {{ number_format($status['openstaand'], 2, ',', '.') }}</span><span class="delta">saldo</span></div>
  @endif
</div>

<div class="sis-grid-2">
  <div>
    <div class="sis-card">
      <div class="sis-card__hd"><h3>Verschuldigd per studiejaar</h3><span class="hint">pro rata · jaartarief ÷ 12 × maanden</span></div>
      <div class="iuasr-dash-tbl-card" style="border:0;">
        <table class="iuasr-dash-tbl">
          <thead><tr><th>Studiejaar</th><th>Opleiding</th><th style="text-align:right;">Jaartarief</th><th style="text-align:center;">Maanden</th><th style="text-align:right;">Verschuldigd</th></tr></thead>
          <tbody>
            @forelse ($regels as $r)
              <tr>
                <td class="nm">{{ $r['inschrijving']->periode?->naam ?? '—' }}<small>{{ $r['inschrijving']->status->label() }}</small></td>
                <td>{{ $r['inschrijving']->opleiding?->code ?? '—' }}</td>
                <td class="tnum" style="text-align:right;">{{ $r['tarief'] !== null ? '€ '.number_format($r['tarief'], 2, ',', '.') : 'geen tarief' }}</td>
                <td class="tnum" style="text-align:center;">{{ $r['maanden'] }}</td>
                <td class="tnum" style="text-align:right;"><b>€ {{ number_format($r['verschuldigd'], 2, ',', '.') }}</b></td>
              </tr>
            @empty
              <tr><td colspan="5" style="color:var(--blackAltText);padding:14px;">Geen inschrijvingen.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="sis-card">
      <div class="sis-card__hd"><h3>Geregistreerde betalingen</h3></div>
      <div class="iuasr-dash-tbl-card" style="border:0;">
        <table class="iuasr-dash-tbl">
          <thead><tr><th>Datum</th><th>Bedrag</th><th>Wijze</th><th>Door</th></tr></thead>
          <tbody>
            @forelse ($student->betalingen->sortByDesc('datum') as $b)
              <tr>
                <td class="dt">{{ $b->datum?->format('d-m-Y') }}</td>
                <td class="tnum">€ {{ number_format($b->bedrag, 2, ',', '.') }}</td>
                <td>{{ $b->betaalwijze ?? '—' }}</td>
                <td class="dt">{{ $b->geregistreerdDoor?->naam ?? '—' }}</td>
              </tr>
            @empty
              <tr><td colspan="4" style="color:var(--blackAltText);padding:14px;">Nog geen betalingen geregistreerd.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div>
    <form method="POST" action="{{ route('financien.betaling', $student) }}" class="sis-card sis-form">
      @csrf
      <div class="sis-card__hd"><h3>Betaling registreren</h3></div>
      @if ($errors->any())
        <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $errors->first() }}</span></div>
      @endif
      <div class="sis-fld">
        <label>Studiejaar / inschrijving <span class="req">*</span></label>
        <select name="inschrijving_id" required>
          @foreach ($regels as $r)
            <option value="{{ $r['inschrijving']->id }}">{{ $r['inschrijving']->periode?->naam }} · {{ $r['inschrijving']->opleiding?->code }}</option>
          @endforeach
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
          <option value="termijn">Termijn</option>
          <option value="contant">Contant</option>
        </select>
      </div>
      <div class="sis-fld"><label>Opmerking</label><textarea name="opmerking" placeholder="Optioneel">{{ old('opmerking') }}</textarea></div>
      <div class="sis-form__actions"><span></span><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Betaling opslaan</button></div></div>
    </form>
  </div>
</div>
@endsection
