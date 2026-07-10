@extends('layouts.app')

@section('titel', 'Cursusgelden')

@php
    use App\Support\Cursusgeldstatus;
    $euro = fn ($b) => '€ '.number_format((float) $b, 2, ',', '.');
@endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('cursussen.dashboard') }}">Cursussen</a><span class="sep">›</span><b>Cursusgelden</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Cursusgelden</h1>
    <div class="summary">Cursusgelden volgen en betalingen registreren of corrigeren (boekhouding)</div>
  </div>
</div>

<div class="iuasr-dash-stats" style="grid-template-columns:repeat(3,1fr);margin-bottom:16px;">
  <div class="iuasr-dash-stat"><span class="lbl">Inschrijvingen in beeld</span><span class="val">{{ $rijen->count() }}</span></div>
  <div class="iuasr-dash-stat iuasr-dash-stat--alert"><span class="lbl">Niet volledig voldaan</span><span class="val">{{ $aantalOpen }}</span></div>
  <div class="iuasr-dash-stat iuasr-dash-stat--alert"><span class="lbl">Totaal openstaand</span><span class="val" style="font-size:20px;">{{ $euro($totaalOpenstaand) }}</span></div>
</div>

@if (session('status'))
  <div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-bottom:16px;display:block;">
    <div style="display:flex;gap:8px;align-items:center;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><b>{{ session('status') }}</b></div>
  </div>
@endif

@if ($errors->any())
  <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:16px;display:block;">
    <ul style="margin:0 0 0 20px;font-size:13px;">
      @foreach ($errors->all() as $fout)<li>{{ $fout }}</li>@endforeach
    </ul>
  </div>
@endif

<form method="GET" action="{{ route('cursussen.betalingen') }}" class="iuasr-dash-filters">
  <div class="search" style="grid-column:1 / -1;">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op cursistnummer of naam…">
  </div>
  <div class="sis-fld" style="margin:0;">
    <label>Cursus</label>
    <select name="cursus" onchange="this.form.submit()">
      <option value="">Alle cursussen</option>
      @foreach ($cursussen as $cursus)
        <option value="{{ $cursus->id }}" @selected((string) $cursusId === (string) $cursus->id)>{{ $cursus->naam }}</option>
      @endforeach
    </select>
  </div>
  <div class="sis-fld" style="margin:0;">
    <label>Status</label>
    <select name="status" onchange="this.form.submit()">
      <option value="alle" @selected($filterStatus === 'alle')>Alle</option>
      <option value="{{ Cursusgeldstatus::OPEN }}" @selected($filterStatus === Cursusgeldstatus::OPEN)>Openstaand</option>
      <option value="{{ Cursusgeldstatus::DEELS }}" @selected($filterStatus === Cursusgeldstatus::DEELS)>Deels betaald</option>
      <option value="{{ Cursusgeldstatus::VOLDAAN }}" @selected($filterStatus === Cursusgeldstatus::VOLDAAN)>Voldaan</option>
    </select>
  </div>
  <button class="iuasr-dash-btn" type="submit">Filteren</button>
</form>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Cursistnr.</th><th>Cursist</th><th>Cursus</th><th style="text-align:right;">Cursusgeld</th><th style="text-align:right;">Betaald</th><th style="text-align:right;">Openstaand</th><th style="text-align:center;">Status</th><th class="row-act"></th></tr></thead>
    <tbody>
      @forelse ($rijen as $r)
        @php $i = $r['inschrijving']; $g = $r['geld']; $betalingen = $i->betalingen->sortByDesc('betaaldatum'); @endphp
        <tr>
          <td class="tnum">{{ $i->cursist->cursistnummer }}</td>
          <td class="nm">{{ $i->cursist->volledigeNaam() }}</td>
          <td class="nm">{{ $i->cursus?->naam ?? '—' }}<small>{{ $i->cursus?->code }}</small></td>
          <td class="tnum" style="text-align:right;">{{ $euro($g['totaal']) }}</td>
          <td class="tnum" style="text-align:right;">{{ $euro($g['betaald']) }}</td>
          <td class="tnum" style="text-align:right;">{{ $euro($g['openstaand']) }}</td>
          <td style="text-align:center;"><span class="iuasr-dash-status {{ Cursusgeldstatus::statusBadge($g['status']) }}">{{ Cursusgeldstatus::statusLabel($g['status']) }}</span></td>
          <td class="row-act">
            <details class="sis-details">
              <summary class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary">Beheer</summary>
              <div class="sis-details__paneel" style="position:absolute;right:0;z-index:20;min-width:420px;max-width:520px;background:var(--white,#fff);border:1px solid var(--lineColor,#e3e3ea);border-radius:12px;box-shadow:0 12px 40px rgba(20,20,50,.16);padding:16px;margin-top:6px;text-align:left;">
                <h4 style="margin:0 0 10px;font-size:14px;">Betalingen — {{ $i->cursist->volledigeNaam() }}</h4>

                {{-- Historie --}}
                @if ($betalingen->isNotEmpty())
                  <table class="iuasr-dash-tbl" style="margin-bottom:12px;">
                    <thead><tr><th>Datum</th><th>Methode</th><th style="text-align:right;">Bedrag</th><th>Status</th><th class="row-act"></th></tr></thead>
                    <tbody>
                      @foreach ($betalingen as $b)
                        <tr>
                          <td class="dt">{{ $b->betaaldatum?->format('d-m-Y') }}</td>
                          <td>{{ $b->betaalmethode->label() }}</td>
                          <td class="tnum" style="text-align:right;">{{ $euro($b->bedrag) }}</td>
                          <td><span class="iuasr-dash-status {{ $b->betalingsstatus->badge() }}">{{ $b->betalingsstatus->label() }}</span></td>
                          <td class="row-act">
                            <details class="sis-details">
                              <summary class="iuasr-dash-btn iuasr-dash-btn--sm">Wijzig</summary>
                              <div class="sis-details__paneel" style="position:absolute;right:0;z-index:30;min-width:300px;background:var(--white,#fff);border:1px solid var(--lineColor,#e3e3ea);border-radius:10px;box-shadow:0 12px 40px rgba(20,20,50,.16);padding:12px;margin-top:6px;">
                                <form method="POST" action="{{ route('cursussen.betaling.bijwerken', $b) }}">
                                  @csrf @method('PUT')
                                  <div class="sis-fld" style="margin:0 0 8px;"><label>Bedrag (€)</label><input type="number" step="0.01" min="0.01" name="bedrag" value="{{ number_format((float) $b->bedrag, 2, '.', '') }}" required></div>
                                  <div class="sis-fld" style="margin:0 0 8px;"><label>Methode</label><select name="betaalmethode" required>@foreach ($methoden as $w => $l)<option value="{{ $w }}" @selected($b->betaalmethode->value === $w)>{{ $l }}</option>@endforeach</select></div>
                                  <div class="sis-fld" style="margin:0 0 8px;"><label>Datum</label><input type="date" name="betaaldatum" value="{{ $b->betaaldatum?->format('Y-m-d') }}" required></div>
                                  <div class="sis-fld" style="margin:0 0 8px;"><label>Status</label><select name="betalingsstatus" required>@foreach ($statussen as $w => $l)<option value="{{ $w }}" @selected($b->betalingsstatus->value === $w)>{{ $l }}</option>@endforeach</select></div>
                                  <div class="sis-fld" style="margin:0 0 8px;"><label>Referentie</label><input type="text" name="referentienummer" maxlength="100" value="{{ $b->referentienummer }}"></div>
                                  <div class="sis-fld" style="margin:0 0 10px;"><label>Opmerking</label><input type="text" name="opmerking" maxlength="500" value="{{ $b->opmerking }}"></div>
                                  <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Opslaan</button>
                                </form>
                                <form method="POST" action="{{ route('cursussen.betaling.verwijderen', $b) }}" onsubmit="return confirm('Deze betaling verwijderen? Dit wordt gelogd.');" style="margin-top:8px;">
                                  @csrf @method('DELETE')
                                  <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">Verwijderen</button>
                                </form>
                              </div>
                            </details>
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                @else
                  <p class="sis-muted" style="font-size:12.5px;margin:0 0 12px;">Nog geen betalingen geregistreerd.</p>
                @endif

                {{-- Nieuwe betaling --}}
                <form method="POST" action="{{ route('cursussen.betaling.registreer', $i) }}">
                  @csrf
                  <h5 style="margin:0 0 8px;font-size:12.5px;text-transform:uppercase;letter-spacing:.04em;color:var(--blackAltText,#6b6b7b);">Betaling registreren</h5>
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                    <div class="sis-fld" style="margin:0;"><label>Bedrag (€)</label><input type="number" step="0.01" min="0.01" name="bedrag" value="{{ number_format((float) $g['openstaand'], 2, '.', '') }}" required></div>
                    <div class="sis-fld" style="margin:0;"><label>Datum</label><input type="date" name="betaaldatum" value="{{ now()->format('Y-m-d') }}" required></div>
                    <div class="sis-fld" style="margin:0;"><label>Methode</label><select name="betaalmethode" required>@foreach ($methoden as $w => $l)<option value="{{ $w }}">{{ $l }}</option>@endforeach</select></div>
                    <div class="sis-fld" style="margin:0;"><label>Status</label><select name="betalingsstatus" required>@foreach ($statussen as $w => $l)<option value="{{ $w }}" @selected($w === 'betaald')>{{ $l }}</option>@endforeach</select></div>
                    <div class="sis-fld" style="margin:0;"><label>Referentie</label><input type="text" name="referentienummer" maxlength="100" placeholder="optioneel"></div>
                    <div class="sis-fld" style="margin:0;"><label>Opmerking</label><input type="text" name="opmerking" maxlength="500" placeholder="optioneel"></div>
                  </div>
                  <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit" style="margin-top:10px;">Registreren</button>
                </form>
              </div>
            </details>
          </td>
        </tr>
      @empty
        <tr><td colspan="8"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen inschrijvingen</h3><p>Er zijn geen cursusinschrijvingen die aan de filters voldoen.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="sis-tblnote" style="margin-top:10px;">Alleen betalingen met status <b>Betaald</b> tellen mee voor het voldane bedrag. Wijzigingen en verwijderingen worden vastgelegd in het audit-logboek.</div>

<style>
  .sis-details { position:relative; display:inline-block; }
  .sis-details > summary { list-style:none; cursor:pointer; }
  .sis-details > summary::-webkit-details-marker { display:none; }
</style>
@endsection
