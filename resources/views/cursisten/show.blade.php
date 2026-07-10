@extends('layouts.app')

@section('titel', 'Cursist · '.$cursist->cursistnummer)

@php
    use App\Support\Cursusgeldstatus;
    $euro = fn ($b) => '€ '.number_format((float) $b, 2, ',', '.');
@endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('cursussen.dashboard') }}">Cursussen</a><span class="sep">›</span><a href="{{ route('cursisten') }}">Cursisten</a><span class="sep">›</span><b>{{ $cursist->cursistnummer }}</b></div>

<div class="iuasr-dash-candidate">
  <div class="iuasr-dash-candidate__hd" style="margin-bottom:0;border-bottom:0;padding-bottom:0;">
    <span class="iuasr-dash-candidate__avatar" aria-hidden="true">{{ mb_substr($cursist->voornaam, 0, 1) }}</span>
    <div class="iuasr-dash-candidate__body">
      <h2 class="iuasr-dash-candidate__name">{{ $cursist->volledigeNaam() }}</h2>
      <div class="iuasr-dash-candidate__meta"><span>Cursistnr. <b>{{ $cursist->cursistnummer }}</b></span></div>
    </div>
    <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('cursisten.edit', $cursist) }}" style="align-self:flex-start;">Wijzig gegevens</a>
  </div>
</div>

<div class="sis-grid-2" style="align-items:start;">
  <div>
    <div class="sis-card">
      <div class="sis-card__hd"><h3>Inschrijvingen</h3></div>
      <div class="iuasr-dash-tbl-card" style="border:0;">
        <table class="iuasr-dash-tbl">
          <thead><tr><th>Cursus</th><th>Ingeschreven</th><th style="text-align:right;">Bedrag</th><th style="text-align:center;">Betaling</th><th style="text-align:center;">Status</th><th class="row-act"></th></tr></thead>
          <tbody>
            @forelse ($cursist->inschrijvingen->sortByDesc('inschrijfdatum') as $i)
              @php $g = Cursusgeldstatus::voor($i); @endphp
              <tr>
                <td class="nm">{{ $i->cursus?->naam ?? '—' }}<small>{{ $i->cursus?->code }}</small></td>
                <td class="dt">{{ $i->inschrijfdatum?->format('d-m-Y') }}</td>
                <td class="tnum" style="text-align:right;">{{ $euro($i->totaalbedrag) }}</td>
                <td style="text-align:center;">
                  <span class="iuasr-dash-status {{ Cursusgeldstatus::statusBadge($g['status']) }}">{{ Cursusgeldstatus::statusLabel($g['status']) }}</span>
                  @if ($g['openstaand'] > 0)<small style="display:block;color:var(--blackAltText);">open: {{ $euro($g['openstaand']) }}</small>@endif
                </td>
                <td style="text-align:center;"><span class="iuasr-dash-status {{ $i->status->badge() }}">{{ $i->status->label() }}</span></td>
                <td class="row-act">
                  <form method="POST" action="{{ route('cursisten.inschrijving.update', [$cursist, $i]) }}" style="display:flex;gap:6px;align-items:center;">
                    @csrf @method('PUT')
                    <select name="status" class="sis-grade-input" style="width:auto;height:30px;">
                      @foreach ($statussen as $waarde => $label)
                        <option value="{{ $waarde }}" @selected($i->status->value === $waarde)>{{ $label }}</option>
                      @endforeach
                    </select>
                    <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Opslaan</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr><td colspan="6" style="color:var(--blackAltText);padding:14px;">Nog niet ingeschreven op een cursus.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- Inschrijven op een cursus --}}
      <form method="POST" action="{{ route('cursisten.inschrijven', $cursist) }}" style="margin-top:12px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
        @csrf
        <div class="sis-fld" style="margin:0;"><label>Inschrijven op cursus</label>
          <select name="cursus_id" required>
            <option value="">— kies een cursus —</option>
            @foreach ($cursussen as $cursus)
              <option value="{{ $cursus->id }}">{{ $cursus->naam }} (€ {{ number_format($cursus->cursusgeld, 2, ',', '.') }})</option>
            @endforeach
          </select>
        </div>
        <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Inschrijven</button>
      </form>
    </div>
  </div>

  <div>
    <div class="sis-card">
      <div class="sis-card__hd"><h3>Gegevens</h3></div>
      <dl class="sis-dl">
        <dt>Geboortedatum</dt><dd>{{ $cursist->geboortedatum?->format('d-m-Y') ?? '—' }}</dd>
        <dt>E-mail</dt><dd>{{ $cursist->email ?? '—' }}</dd>
        <dt>Telefoon</dt><dd>{{ $cursist->telefoon ?? '—' }}</dd>
        <dt>Adres</dt><dd>{{ trim(($cursist->adres ?? '').' '.($cursist->postcode ?? '').' '.($cursist->woonplaats ?? '')) ?: '—' }}</dd>
        <dt>Status</dt><dd>{{ ucfirst($cursist->status) }}</dd>
      </dl>
      @if ($cursist->opmerkingen)
        <p class="sis-muted" style="font-size:12.5px;margin-top:10px;">{{ $cursist->opmerkingen }}</p>
      @endif
    </div>
  </div>
</div>
@endsection
