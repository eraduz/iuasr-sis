@extends('layouts.app')

@section('titel', $student->volledigeNaam().' · '.$student->studentnummer)

@php
    $u = auth()->user();
    $magBsn = $u->magBsnInzien();
@endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ route('studenten.index') }}">Studenten</a><span class="sep">›</span><b>{{ $student->studentnummer }} — {{ $student->volledigeNaam() }}</b></div>

@if (auth()->user()->magFinancieelInzien() && $financieel['achterstand'])
  <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:16px;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <span><b>Betalingsachterstand: € {{ number_format($financieel['openstaand'], 2, ',', '.') }} openstaand.</b> Studievoortgang (herinschrijven) en het afgeven van documenten/verklaringen zijn voor deze student <b>geblokkeerd</b> tot de schuld is voldaan.</span>
  </div>
@endif

<div class="iuasr-dash-candidate">
  <div class="iuasr-dash-candidate__hd" style="margin-bottom:0;border-bottom:0;padding-bottom:0;">
    <span class="iuasr-dash-candidate__avatar" aria-hidden="true">{{ mb_substr($student->voornaam, 0, 1) }}</span>
    <div class="iuasr-dash-candidate__body">
      <h2 class="iuasr-dash-candidate__name">{{ $student->volledigeNaam() }}</h2>
      <div class="iuasr-dash-candidate__meta">
        <span>Studentnr. <b>{{ $student->studentnummer }}</b></span><span class="dot"></span>
        @if ($actieveInschrijvingen->isNotEmpty())
          <span>{{ $actieveInschrijvingen->map(fn ($i) => $i->opleiding?->naam)->filter()->implode('  +  ') }}</span>
          @if ($actieveInschrijvingen->count() > 1)<span class="dot"></span><span class="sis-pill-soft">dubbele inschrijving · {{ $actieveInschrijvingen->count() }} opleidingen</span>@endif
        @else
          <span>{{ $huidige?->opleiding?->naam ?? 'Geen inschrijving' }}</span>
        @endif
        @if ($huidige?->klas)<span class="dot"></span><span>Klas <b>{{ $huidige->klas->code }}</b></span>@endif
      </div>
    </div>
    @if ($huidige)
      <span class="iuasr-dash-status {{ $huidige->status->badge() }}" style="align-self:flex-start;">{{ $huidige->status->label() }}</span>
    @endif
  </div>
</div>

<div class="sis-tabs" role="tablist">
  <button class="sis-tab is-active" data-tab="persoon" role="tab">Persoonsgegevens</button>
  <button class="sis-tab" data-tab="inschrijving" role="tab">Inschrijving &amp; klas</button>
  <button class="sis-tab {{ $magCijfers ? '' : 'is-locked' }}" data-tab="cijfers" role="tab">
    Cijfers
    @unless ($magCijfers)
      <span class="lock"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
    @endunless
  </button>
</div>

{{-- PANEEL: Persoonsgegevens --}}
<section class="sis-tabpanel is-active" data-panel="persoon">

  {{-- Acties — bovenaan, direct bereikbaar --}}
  @if (auth()->user()->magInschrijvingBeheren())
    <div class="sis-card" style="margin-bottom:16px;">
      <div class="sis-card__hd"><h3>Acties</h3></div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('studenten.edit', $student) }}">Wijzig gegevens</a>
        @if ($financieel['achterstand'])
          <span class="iuasr-dash-btn iuasr-dash-btn--sm" aria-disabled="true" title="Geblokkeerd wegens betalingsachterstand" style="opacity:.5;cursor:not-allowed;">Herinschrijven (geblokkeerd)</span>
          <span class="iuasr-dash-btn iuasr-dash-btn--sm" aria-disabled="true" title="Geblokkeerd wegens betalingsachterstand" style="opacity:.5;cursor:not-allowed;">Verklaring (geblokkeerd)</span>
        @else
          <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('herinschrijven.form', $student) }}">Herinschrijven</a>
          @if ($huidige && $huidige->status === App\Enums\InschrijvingStatus::Actief)
            <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('herinschrijven.form', ['student' => $student, 'modus' => 'tweede']) }}">Tweede opleiding</a>
          @endif
          <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('verklaringen', ['student' => $student->id]) }}">Verklaring</a>
        @endif
        @if ($huidige)
          {{-- Schorsen / opheffen met één klik --}}
          <form method="POST" action="{{ route('studenten.schors', $student) }}" style="display:inline;">
            @csrf
            @if ($huidige->status === App\Enums\InschrijvingStatus::Geschorst)
              <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--sm">Schorsing opheffen</button>
            @else
              <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger">Schorsen</button>
            @endif
          </form>
          @if ($huidige->status !== App\Enums\InschrijvingStatus::Uitgeschreven)
            <a class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" href="{{ route('uitschrijven.form', $student) }}">Uitschrijven</a>
          @endif
        @endif
      </div>
    </div>
  @endif

  <div class="sis-grid-2" style="align-items:start;">
    {{-- Linkerkolom --}}
    <div>
      <div class="sis-card">
        <div class="sis-card__hd"><h3>Persoonsgegevens</h3><span class="hint">Bron: inschrijving</span></div>
        <dl class="sis-dl">
          <dt>Volledige naam</dt><dd><b>{{ $student->volledigeNaam() }}</b></dd>
          <dt>Geboortedatum</dt><dd>{{ $student->geboortedatum?->format('d-m-Y') ?? '—' }}</dd>
          <dt>Geboorteplaats</dt><dd>{{ $student->geboorteplaats ?? '—' }}</dd>
          <dt>Nationaliteit</dt><dd>{{ $student->nationaliteit?->naam ?? '—' }}</dd>
          <dt>BSN</dt><dd>
            <span class="sis-masked" id="bsn-field">
              <span id="bsn-value">••••••••</span>
              @if ($magBsn)
                <button class="reveal" type="button" id="bsn-toggle" data-url="{{ route('studenten.bsn', $student) }}">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                  Tonen
                </button>
              @else
                <span class="sis-shield"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> afgeschermd</span>
              @endif
            </span>
          </dd>
        </dl>
        <div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-top:14px;">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <span>Het BSN is <b>versleuteld opgeslagen</b> en wordt gemaskeerd getoond. Tonen wordt <b>gelogd</b> in de audit-log. Het BSN wordt nooit geëxporteerd.</span>
        </div>
      </div>

      <div class="sis-card" style="margin-top:16px;">
        <div class="sis-card__hd"><h3>Vooropleiding</h3></div>
        <dl class="sis-dl">
          <dt>Hoogst behaalde diploma</dt><dd>{{ $student->diploma ?? '—' }}</dd>
          <dt>Onderwijsinstelling</dt><dd>{{ $student->vorige_instelling ?? '—' }}</dd>
          <dt>Afstudeerjaar</dt><dd>{{ $student->afstudeerjaar ?? '—' }}</dd>
        </dl>
      </div>

      <div class="sis-card" style="margin-top:16px;">
        <div class="sis-card__hd"><h3>Taalbeheersing</h3></div>
        <dl class="sis-dl">
          <dt>Nederlandse taal</dt><dd>{{ $student->taal_nederlands?->label() ?? '—' }}</dd>
          <dt>Arabische taal</dt><dd>{{ $student->taal_arabisch?->label() ?? '—' }} <span class="sis-muted" style="font-size:11px;">· info</span></dd>
          <dt>NT2-examen</dt><dd>
            @switch($student->nt2Status())
              @case('behaald')<span class="iuasr-dash-status s-approved">Behaald op {{ $student->nt2_behaald_op->format('d-m-Y') }}</span>@break
              @case('verlopen')<span class="iuasr-dash-status s-rejected">Termijn verstreken · deadline {{ $student->nt2Deadline()?->format('d-m-Y') }}</span>@break
              @case('open')<span class="iuasr-dash-status s-incomplete">Deadline {{ $student->nt2Deadline()?->format('d-m-Y') }} · nog {{ $student->nt2DagenResterend() }} dagen</span>@break
              @default<span class="sis-muted">Niet vereist</span>
            @endswitch
          </dd>
        </dl>
      </div>

      @if ($kennistoetsen['vereist'])
        @php $ktMagBew = auth()->user()->magInschrijvingBeheren(); @endphp
        <div class="sis-card" style="margin-top:16px;">
          <div class="sis-card__hd">
            <h3>Landelijke kennistoetsen</h3>
            <span class="hint">{{ $kennistoetsen['behaald'] }}/{{ $kennistoetsen['totaal'] }} behaald · deadline {{ optional($kennistoetsen['deadline'])->format('d-m-Y') ?? '—' }}</span>
          </div>
          <div class="iuasr-dash-tbl-card" style="border:0;">
            <table class="iuasr-dash-tbl">
              <thead>
                <tr><th>Toets</th><th>Status</th>@if ($ktMagBew)<th></th>@endif</tr>
              </thead>
              <tbody>
                @foreach ($kennistoetsen['toetsen'] as $rij)
                  <tr>
                    <td class="nm">{{ $rij['toets']->naam }}</td>
                    <td>
                      @if ($rij['status'] === 'behaald')
                        <span class="iuasr-dash-status s-approved">Behaald op {{ optional($rij['behaald_op'])->format('d-m-Y') }}</span>
                      @elseif ($rij['status'] === 'verlopen')
                        <span class="iuasr-dash-status s-rejected">Termijn verstreken</span>
                      @else
                        <span class="iuasr-dash-status s-incomplete">Openstaand</span>
                      @endif
                    </td>
                    @if ($ktMagBew)
                      <td style="text-align:right;">
                        <form method="POST" action="{{ route('studenten.kennistoetsen.bijwerken', $student) }}" style="display:flex;gap:6px;justify-content:flex-end;">
                          @csrf
                          <input type="hidden" name="kennistoets_id" value="{{ $rij['toets']->id }}">
                          <input type="date" name="behaald_op" value="{{ optional($rij['behaald_op'])->format('Y-m-d') }}" style="padding:5px 8px;border:1px solid var(--borderColor);border-radius:6px;font-size:12px;">
                          <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Opslaan</button>
                        </form>
                      </td>
                    @endif
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          <p class="sis-tblnote" style="margin-top:6px;">PABO-kennistoetsen · te halen binnen {{ config('sis.kennistoetsen.termijn_jaren', 2) }} jaar.@if ($ktMagBew) Vul een datum in en klik Opslaan; leeg = wissen.@endif</p>
        </div>
      @endif
    </div>

    {{-- Rechterkolom --}}
    <div>
      <div class="sis-card">
        <div class="sis-card__hd"><h3>Contact</h3></div>
        <dl class="sis-dl">
          <dt>E-mail (IUASR)</dt><dd>{{ $student->email ?? '—' }}</dd>
          <dt>E-mail privé</dt><dd>{{ $student->email_prive ?? '—' }}</dd>
          <dt>Telefoon</dt><dd>{{ $student->telefoon ?? '—' }}</dd>
          <dt>Adres</dt><dd>
            {{ trim(($student->adres ?? '').' '.($student->huisnummer ?? '')) ?: '—' }}
            @if($student->postcode || $student->woonplaats)<br>{{ trim(($student->postcode ?? '').' '.($student->woonplaats ?? '')) }}@endif
            @if($student->provincie || $student->land)<br><span class="sis-muted">{{ trim(($student->provincie ?? '').(($student->provincie && $student->land) ? ' · ' : '').($student->land?->naam ?? '')) }}</span>@endif
          </dd>
          @if (auth()->user()->magInschrijvingBeheren())
            <dt>IBAN</dt><dd>{{ $student->rekeningnummer ?? '—' }} <span class="sis-muted" style="font-size:11px;">· versleuteld</span></dd>
          @endif
        </dl>
      </div>

      {{-- Collegegeld — termijntabel: per factuur direct zichtbaar wat betaald is --}}
      @if (auth()->user()->magFinancieelInzien())
        @php
          $euro = fn ($b) => '€ '.number_format($b, 2, ',', '.');
          $regeling = $huidige ? \App\Support\Collegegeldtermijnen::regeling($huidige) : null;
        @endphp
        <div class="sis-card" style="margin-top:16px;">
          <div class="sis-card__hd">
            <h3>Collegegeld</h3>
            <span class="hint">{{ $regeling?->label() ?? 'geen inschrijving' }}</span>
          </div>

          @if ($financieel['jaarbedrag'] === null || $termijnen->isEmpty())
            <p class="sis-muted" style="font-size:13px;margin:0;">
              {{ $financieel['jaarbedrag'] === null
                  ? 'Geen collegegeldtarief ingesteld voor dit studiejaar.'
                  : 'Nog geen termijnen: de student is aangemeld maar nog niet ingeschreven.' }}
            </p>
          @else
            <div class="sis-termijn-kop">
              <div><span class="lbl">Jaarcollegegeld</span><b>{{ $euro($financieel['jaarbedrag']) }}</b></div>
              <div><span class="lbl">Verschuldigd</span><b>{{ $euro($financieel['verschuldigd']) }}</b></div>
              <div><span class="lbl">Betaald</span><b>{{ $euro($financieel['betaald']) }}</b></div>
              <div><span class="lbl">Achterstallig</span><b class="{{ $financieel['achterstallig'] > 0 ? 'is-fail' : '' }}">{{ $euro($financieel['achterstallig']) }}</b></div>
            </div>

            <div class="iuasr-dash-tbl-card" style="border:0;margin-top:10px;">
              <table class="iuasr-dash-tbl sis-termijn-tbl">
                <thead>
                  <tr>
                    <th style="width:34px;">#</th>
                    <th>Termijn</th>
                    <th style="text-align:center;">Vervaldatum</th>
                    <th style="text-align:right;">Bedrag</th>
                    <th style="text-align:right;">Betaald</th>
                    <th style="text-align:right;">Open</th>
                    <th style="text-align:center;">Status</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($termijnen as $t)
                    <tr class="{{ $t['status'] === \App\Support\Collegegeldtermijnen::VERVALLEN ? 'is-vervallen' : '' }}">
                      <td class="tnum">{{ $t['nr'] }}</td>
                      <td class="nm">{{ $t['naam'] }}</td>
                      <td class="dt" style="text-align:center;">{{ $t['vervaldatum']->format('d-m-Y') }}</td>
                      <td class="tnum" style="text-align:right;">{{ $t['vervallen'] ? '—' : $euro($t['bedrag']) }}</td>
                      <td class="tnum" style="text-align:right;">{{ $t['betaald'] > 0 ? $euro($t['betaald']) : '—' }}</td>
                      <td class="tnum" style="text-align:right;">{{ $t['open'] > 0 ? $euro($t['open']) : '—' }}</td>
                      <td style="text-align:center;">
                        <span class="iuasr-dash-status {{ \App\Support\Collegegeldtermijnen::statusBadge($t['status']) }}">{{ \App\Support\Collegegeldtermijnen::statusLabel($t['status']) }}</span>
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
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>

            @if ($financieel['achterstallig'] > 0)
              <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-top:12px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                <span>Betalingsachterstand: <b>{{ $euro($financieel['achterstallig']) }}</b> <span class="sis-muted" style="font-size:11px;">· vervallen termijnen die nog niet zijn voldaan</span></span>
              </div>
            @elseif ($financieel['terugbetaling'] > 0)
              <div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-top:12px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                <span>Terug te betalen aan student: <b>{{ $euro($financieel['terugbetaling']) }}</b></span>
              </div>
            @elseif ($financieel['openstaand'] > 0)
              <div class="iuasr-dash-alert iuasr-dash-alert--ok" style="margin-top:12px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                <span>Geen achterstand. Nog te factureren dit studiejaar: <b>{{ $euro($financieel['openstaand']) }}</b></span>
              </div>
            @else
              <div class="iuasr-dash-alert iuasr-dash-alert--ok" style="margin-top:12px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                <span>Collegegeld volledig voldaan.</span>
              </div>
            @endif
          @endif

          @if ($huidige && auth()->user()->magCollegegeldBeheren())
            {{-- De regeling geldt per studiejaar; bij herinschrijving opnieuw vast te stellen. --}}
            <form method="POST" action="{{ route('inschrijving.betaalregeling', $huidige) }}" style="margin-top:14px;border-top:1px solid var(--borderColor);padding-top:12px;">
              @csrf
              <label style="display:block;font-size:12px;font-weight:600;color:var(--priColor100);margin-bottom:6px;">Betaalregeling</label>
              @foreach (App\Enums\Betaalregeling::cases() as $optie)
                <label class="sis-check-inline" style="display:flex;margin-bottom:4px;">
                  <input type="radio" name="betaalregeling" value="{{ $optie->value }}" @checked($regeling === $optie)>
                  <span>{{ $optie->label() }} <span class="sis-muted" style="font-size:11px;">— {{ $optie->omschrijving() }}</span></span>
                </label>
              @endforeach
              <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit" style="margin-top:8px;">Opslaan</button>
            </form>
          @endif
        </div>
      @endif

      {{-- Interne notities — SZ/Beheer beheren; Directie/Bestuur lezen mee --}}
      @php
        $magNotitiesBeheren = auth()->user()->magInschrijvingBeheren();
        $magNotitiesZien = $magNotitiesBeheren || in_array(auth()->user()->rol, [App\Enums\Rol::Directie, App\Enums\Rol::Bestuur], true);
      @endphp
      @if ($magNotitiesZien)
        <div class="sis-card" id="notities" style="margin-top:16px;">
          <div class="sis-card__hd"><h3>Notities</h3><span class="hint">Intern · SZ &amp; Beheer beheren · Directie &amp; Bestuur lezen mee</span></div>

          @if ($magNotitiesBeheren)
          <form method="POST" action="{{ route('studenten.notities.store', $student) }}" class="iuasr-dash-note-form" style="margin-bottom:12px;">
            @csrf
            <div class="iuasr-dash-note-form__hd">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
              Nieuwe notitie · {{ now()->format('d-m-Y') }}
            </div>
            <textarea name="tekst" required maxlength="2000" placeholder="Bijv. telefonisch contact, gemaakte afspraak, ontbrekend document…">{{ old('tekst') }}</textarea>
            <div style="display:flex;justify-content:flex-end;">
              <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Notitie opslaan</button>
            </div>
          </form>
          @endif

          <div class="iuasr-dash-note-list">
            @forelse ($student->notities as $n)
              <div class="iuasr-dash-note">
                <small>{{ $n->created_at->format('d-m-Y · H:i') }} · {{ $n->gebruiker?->naam ?? 'onbekend' }}</small>
                <div style="display:flex;gap:10px;align-items:flex-start;justify-content:space-between;">
                  <span style="white-space:pre-wrap;">{{ $n->tekst }}</span>
                  @if ($magNotitiesBeheren)
                  <form method="POST" action="{{ route('studenten.notities.destroy', [$student, $n]) }}" onsubmit="return confirm('Deze notitie verwijderen?');" style="flex:none;">
                    @csrf @method('DELETE')
                    <button type="submit" title="Verwijderen" style="background:none;border:0;cursor:pointer;color:var(--blackAltText);padding:2px;line-height:0;">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                  </form>
                  @endif
                </div>
              </div>
            @empty
              <p class="sis-muted" style="font-size:13px;margin:4px 2px;">Nog geen notities voor deze student.</p>
            @endforelse
          </div>
        </div>
      @endif
    </div>
  </div>

  {{-- Documenten van de student (privé opgeslagen; inzage gelogd) — halve breedte --}}
  @if (auth()->user()->magInschrijvingBeheren())
    <div class="sis-grid-2" style="margin-top:16px;align-items:start;">
      <div class="sis-card" id="documenten">
      <div class="sis-card__hd"><h3>Documenten</h3><span class="hint">identiteitsbewijs, diploma, cijferlijst, pasfoto — privé opgeslagen, inzage gelogd</span></div>

      <form method="POST" action="{{ route('studenten.documenten.later', $student) }}" style="margin-bottom:12px;">
        @csrf
        <label class="sis-check-inline">
          <input type="checkbox" name="documenten_later" value="1" @checked($student->documenten_later) onchange="this.form.submit()">
          <b>Student levert later aan</b> (diploma / cijferlijst e.d. volgen nog)
        </label>
      </form>

      @error('bestand')<div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $message }}</span></div>@enderror

      @php $perSoort = $student->documenten->groupBy('soort'); @endphp
      <div class="iuasr-doc-lijst">
        @foreach (App\Models\StudentDocument::SOORTEN as $key => $label)
          @php $docs = $perSoort[$key] ?? collect(); @endphp
          <div style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap;padding:12px 0;border-top:1px solid var(--borderColor, #e5e5ea);">
            <div style="flex:0 0 150px;min-width:140px;">
              <div style="font-weight:600;color:var(--priColor100);font-size:13px;">{{ $label }}</div>
              @if ($docs->isNotEmpty())
                <span class="iuasr-dash-status s-approved" style="margin-top:6px;display:inline-block;">{{ $docs->count() }} geüpload</span>
              @else
                <span class="sis-muted" style="font-size:12px;">nog niet geüpload</span>
              @endif
            </div>
            <div style="flex:1;min-width:190px;">
              @foreach ($docs as $doc)
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:8px;">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="flex:none;color:var(--blackAltText);"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                  <span style="flex:1;min-width:120px;word-break:break-all;font-size:13px;">{{ $doc->bestandsnaam }} <span class="sis-muted" style="font-size:11px;">· {{ number_format($doc->grootte / 1024, 0, ',', '.') }} kB · {{ $doc->created_at->format('d-m-Y') }}</span></span>
                  <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('documenten.download', [$doc, 'bekijken' => 1]) }}" target="_blank">Bekijken</a>
                  <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('documenten.download', $doc) }}">Downloaden</a>
                  <form method="POST" action="{{ route('documenten.destroy', $doc) }}" onsubmit="return confirm('Dit document verwijderen?');" style="display:inline;">
                    @csrf @method('DELETE')
                    <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger">Verwijderen</button>
                  </form>
                </div>
              @endforeach
              <form method="POST" action="{{ route('studenten.documenten.upload', $student) }}" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                @csrf
                <input type="hidden" name="soort" value="{{ $key }}">
                <input type="file" name="bestand" accept=".pdf,.jpg,.jpeg,.png,.webp" required style="flex:1;min-width:170px;font-size:12.5px;">
                <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Upload</button>
              </form>
            </div>
          </div>
        @endforeach
      </div>
      <p class="sis-tblnote" style="margin-top:10px;">Toegestaan: pdf, jpg, png · max 8 MB per bestand. Inzage en afgifte worden gelogd.</p>
      </div>
    </div>
  @endif

  {{-- Gevaarlijke acties — uitsluitend Beheerder: student volledig verwijderen --}}
  @if (auth()->user()->rol === App\Enums\Rol::Beheerder)
    <div class="sis-card" style="margin-top:16px;border:1px solid var(--secColor100);">
      <div class="sis-card__hd"><h3 style="color:var(--secColor100);">Student volledig verwijderen</h3><span class="hint">alleen voor foutieve records · Beheer</span></div>
      <p class="sis-muted" style="font-size:13px;margin:0 0 12px;">
        Verwijdert deze student en <b>alle</b> gekoppelde gegevens (inschrijvingen, cijfers, betalingen, documenten, notities, vrijstellingen) <b>onherstelbaar</b>. Ondertekende documenten blijven bewaard (losgekoppeld). Deze actie kan niet ongedaan worden gemaakt.
      </p>
      <form method="POST" action="{{ route('studenten.destroy', $student) }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;" onsubmit="return confirm('Weet u zeker dat u {{ $student->volledigeNaam() }} ({{ $student->studentnummer }}) VOLLEDIG en ONHERSTELBAAR wilt verwijderen?');">
        @csrf
        @method('DELETE')
        <input type="text" name="bevestig_nummer" required autocomplete="off" placeholder="Typ {{ $student->studentnummer }} ter bevestiging" style="padding:8px 10px;border:1px solid var(--secColor100);border-radius:6px;min-width:220px;">
        <button class="iuasr-dash-btn iuasr-dash-btn--sm" style="background:var(--secColor100);color:#fff;border-color:var(--secColor100);" type="submit">Definitief verwijderen</button>
      </form>
    </div>
  @endif

</section>

{{-- PANEEL: Inschrijving --}}
<section class="sis-tabpanel" data-panel="inschrijving">
  <div class="sis-grid-2">
    <div class="sis-card">
      <div class="sis-card__hd"><h3>Huidige inschrijving</h3></div>
      @if ($huidige)
        <dl class="sis-dl">
          <dt>Opleiding</dt><dd><b>{{ $huidige->opleiding?->naam }}</b></dd>
          <dt>Klas</dt><dd>{{ $huidige->klas?->code ?? '—' }}</dd>
          <dt>Leerjaar</dt><dd>{{ $huidige->leerjaar ?? '—' }}</dd>
          <dt>Periode</dt><dd>{{ $huidige->periode?->naam ?? '—' }}</dd>
          <dt>Inschrijfdatum</dt><dd>{{ $huidige->inschrijfdatum?->format('d-m-Y') ?? '—' }}</dd>
          @if ($huidige->uitschrijfdatum && $huidige->status === App\Enums\InschrijvingStatus::Uitgeschreven)
            <dt>Uitschrijfdatum</dt><dd>{{ $huidige->uitschrijfdatum->format('d-m-Y') }}</dd>
          @endif
          <dt>Status</dt><dd><span class="iuasr-dash-status {{ $huidige->status->badge() }}">{{ $huidige->status->label() }}</span></dd>
          <dt>Studentnummer</dt><dd class="tnum">{{ $student->studentnummer }}</dd>
          @if (auth()->user()->magAanwezigheidsregelingZien())
            <dt>Aanwezigheid</dt>
            <dd>
              @if ($huidige->aanwezigheidsregeling_50)
                <span class="iuasr-dash-status s-approved">50%-aanwezigheidsregeling</span>
              @else
                <span class="sis-muted">Reguliere norm ({{ (int) round(config('sis.presentie.norm') * 100) }}%)</span>
              @endif
            </dd>
          @endif
        </dl>

        @if (auth()->user()->magAanwezigheidsregelingBeheren())
          {{-- De regeling geldt per opleiding én studiejaar: zij hangt aan DEZE inschrijving
               en moet bij herinschrijving bewust opnieuw worden toegekend. --}}
          <form method="POST" action="{{ route('inschrijving.aanwezigheidsregeling', $huidige) }}" style="margin-top:14px;border-top:1px solid var(--borderColor);padding-top:12px;">
            @csrf
            <label class="sis-check-inline">
              <input type="checkbox" name="aanwezigheidsregeling_50" value="1" @checked($huidige->aanwezigheidsregeling_50)>
              50% Aanwezigheidsregeling
            </label>
            <p class="sis-muted" style="font-size:12px;margin:6px 0 10px;">
              De student hoeft dan minimaal de helft van de lessen, practica en colleges bij te wonen.
              Alleen aanvinken met toestemming van de directie. Geldt voor {{ $huidige->opleiding?->code }} in {{ $huidige->periode?->naam ?? 'dit studiejaar' }}; de wijziging wordt gelogd.
            </p>
            <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Opslaan</button>
          </form>
        @endif
      @else
        <p class="sis-muted">Geen actieve inschrijving.</p>
      @endif
    </div>
    <div>
      <div class="sis-card">
        <div class="sis-card__hd"><h3>Inschrijfhistorie</h3></div>
        <ul class="iuasr-dash-log">
          @forelse ($student->inschrijvingen->sortByDesc('inschrijfdatum') as $i)
            <li><b>{{ $i->status->label() }} — {{ $i->opleiding?->naam }}</b><time>{{ $i->inschrijfdatum?->format('d-m-Y') }} · {{ $i->periode?->code }}</time></li>
          @empty
            <li>Geen historie.</li>
          @endforelse
        </ul>
      </div>
    </div>
  </div>

  {{-- Toegewezen vakken — volledige studiehistorie per studiejaar en periode --}}
  <div class="sis-card" style="margin-top:16px;">
    <div class="sis-card__hd"><h3>Toegewezen vakken</h3><span class="hint">studiehistorie per studiejaar en periode (blok)</span></div>
    @if ($vakHistorie->isEmpty())
      <p class="sis-muted" style="font-size:13px;margin:0;">Nog geen vakken toegewezen.</p>
    @else
      <div class="sis-subtabs" role="tablist">
        @foreach ($vakHistorie as $i => $h)
          <button class="sis-subtab {{ $i===0 ? 'is-active' : '' }}" data-vh="vh{{ $i }}">{{ $h['inschrijving']->periode?->naam ?? 'Studiejaar' }} · jaar {{ $h['inschrijving']->leerjaar }}</button>
        @endforeach
      </div>

      @foreach ($vakHistorie as $i => $h)
        <div class="vh-panel" data-vhpanel="vh{{ $i }}" @if($i!==0) hidden @endif>
          @if (auth()->user()->magInschrijvingBeheren())
            <div style="display:flex;justify-content:flex-end;margin-bottom:10px;">
              <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('inschrijving.vakken', $h['inschrijving']) }}">Vakken aanpassen</a>
            </div>
          @endif
          @php $blokken = $h['perBlok']; @endphp
          @if ($blokken->isEmpty())
            <p class="sis-muted" style="font-size:13px;">Geen vakken in dit studiejaar.</p>
          @else
            <div class="sis-subtabs" role="tablist">
              @foreach ($blokken as $blok => $vakken)
                <button class="sis-subtab vh-blok-btn {{ $loop->first ? 'is-active' : '' }}" data-vhb="vh{{ $i }}b{{ $blok }}" data-group="vh{{ $i }}">Blok {{ $blok ?: '—' }}<span class="n">{{ $vakken->count() }}</span></button>
              @endforeach
            </div>
            @foreach ($blokken as $blok => $vakken)
              <div class="vh-blok" data-vhbpanel="vh{{ $i }}b{{ $blok }}" @if(!$loop->first) hidden @endif>
                <div class="iuasr-dash-tbl-card" style="border:0;">
                  <table class="iuasr-dash-tbl">
                    <thead><tr><th>Code</th><th>Vak</th><th>EC</th><th>Docent</th></tr></thead>
                    <tbody>
                      @foreach ($vakken as $vak)
                        <tr><td class="tnum">{{ $vak->code }}</td><td class="nm">{{ $vak->naam }}</td><td class="tnum">{{ \App\Support\Ec::toon($vak->ec) }}</td><td>{{ $vak->docent?->achternaam ?? '—' }}</td></tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            @endforeach
          @endif
        </div>
      @endforeach
    @endif
  </div>

  {{-- Vrijstellingen (administratief; SZ legt het examencommissie-besluit vast) --}}
  @if ($huidige)
    @php
      $toewijzingen = $huidige->vaktoewijzingen->sortBy(fn ($t) => $t->vak?->code);
      $vrijLijst = $toewijzingen->where('vrijgesteld', true);
      $nietVrij = $toewijzingen->where('vrijgesteld', false)->filter(fn ($t) => $t->vak);
      $magVrij = auth()->user()->magInschrijvingBeheren();
      $magBesluit = in_array(auth()->user()->rol, [App\Enums\Rol::Examencommissie, App\Enums\Rol::Directie], true);
      $openBesluitVakIds = $besluiten->where('status', App\Enums\VrijstellingsbesluitStatus::Open)->pluck('vak_id')->flip();
      $besluitBaar = $nietVrij->filter(fn ($t) => ! isset($openBesluitVakIds[$t->vak_id]));
    @endphp
    <div class="sis-card" style="margin-top:16px;">
      <div class="sis-card__hd"><h3>Vrijstellingen</h3><span class="hint">{{ $huidige->periode?->naam }} · verleend door de examencommissie, vastgelegd door Studentenzaken</span></div>

      @if ($vrijLijst->isEmpty())
        <p class="sis-muted" style="font-size:13px;margin:0 0 12px;">Geen vrijstellingen vastgelegd voor dit studiejaar.</p>
      @else
        <div class="iuasr-dash-tbl-card" style="border:0;margin-bottom:8px;">
          <table class="iuasr-dash-tbl">
            <thead><tr><th>Code</th><th>Vak</th><th class="tnum">EC</th><th>Grondslag</th><th>Besluit</th><th>Vastgelegd</th><th></th></tr></thead>
            <tbody>
              @foreach ($vrijLijst as $t)
                <tr>
                  <td class="tnum">{{ $t->vak?->code }}</td>
                  <td class="nm">{{ $t->vak?->naam }} <span class="iuasr-dash-status s-approved">VR</span></td>
                  <td class="tnum">{{ \App\Support\Ec::toon($t->vrijstelling_ec ?? $t->vak?->ec) }}</td>
                  <td>{{ $t->vrijstelling_grondslag?->label() ?? '—' }}</td>
                  <td>{{ $t->vrijstelling_besluit }}<br><small class="sis-muted">{{ $t->vrijstelling_besluit_datum?->format('d-m-Y') }}</small></td>
                  <td><small class="sis-muted">{{ $t->vrijgesteldDoor?->naam ?? '—' }}<br>{{ $t->vrijgesteld_op?->format('d-m-Y') }}</small></td>
                  <td style="text-align:right;">
                    @if ($magVrij)
                      <form method="POST" action="{{ route('studenten.vrijstellingen.destroy', [$student, $t]) }}" onsubmit="return confirm('Vrijstelling voor {{ $t->vak?->code }} intrekken?');">
                        @csrf @method('DELETE')
                        <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Intrekken</button>
                      </form>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif

      @if ($magVrij && $nietVrij->isNotEmpty())
        <form method="POST" action="{{ route('studenten.vrijstellingen.store', $student) }}" class="sis-form" style="border-top:1px solid var(--borderSubtleColor);padding-top:14px;margin-top:6px;">
          @csrf
          <h4 style="margin:0 0 10px;font-size:13px;">Vrijstelling toevoegen</h4>
          <div class="sis-fld-row sis-fld-row--2">
            <div class="sis-fld"><label>Vak</label>
              <select name="vaktoewijzing_id" required>
                <option value="">Kies een toegewezen vak…</option>
                @foreach ($nietVrij as $t)
                  <option value="{{ $t->id }}" @selected(old('vaktoewijzing_id') == $t->id)>{{ $t->vak->code }} · {{ $t->vak->naam }} ({{ \App\Support\Ec::toon($t->vak->ec) }} EC)</option>
                @endforeach
              </select>
              @error('vaktoewijzing_id')<small style="color:var(--secColor100);">{{ $message }}</small>@enderror
            </div>
            <div class="sis-fld"><label>Grondslag</label>
              <select name="grondslag" required>
                @foreach ($grondslagen as $val => $lbl)<option value="{{ $val }}" @selected(old('grondslag') == $val)>{{ $lbl }}</option>@endforeach
              </select>
            </div>
          </div>
          <div class="sis-fld-row sis-fld-row--2">
            <div class="sis-fld"><label>Besluit-referentie (examencommissie)</label><input type="text" name="besluit" required placeholder="bv. EC-2026-014" value="{{ old('besluit') }}">@error('besluit')<small style="color:var(--secColor100);">{{ $message }}</small>@enderror</div>
            <div class="sis-fld"><label>Besluitdatum</label><input type="date" name="besluit_datum" required value="{{ old('besluit_datum') }}">@error('besluit_datum')<small style="color:var(--secColor100);">{{ $message }}</small>@enderror</div>
          </div>
          <div class="sis-fld"><label>Toelichting (optioneel)</label><textarea name="toelichting" rows="2" placeholder="bv. o.b.v. eerder behaald vak aan andere HBO-instelling">{{ old('toelichting') }}</textarea></div>
          <div style="display:flex;justify-content:flex-end;">
            <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Vrijstelling vastleggen</button>
          </div>
        </form>
      @elseif ($magVrij)
        <p class="sis-muted" style="font-size:12.5px;margin:8px 0 0;">Alle toegewezen vakken zijn al vrijgesteld, of er zijn nog geen vakken toegewezen.</p>
      @endif

      {{-- Workflow: besluiten van de examencommissie richting Studentenzaken --}}
      @if ($besluiten->isNotEmpty() || $magBesluit)
        <div style="border-top:1px solid var(--borderSubtleColor);padding-top:14px;margin-top:14px;">
          <h4 style="margin:0 0 8px;font-size:13px;">Besluiten van de examencommissie</h4>
          @if ($besluiten->isEmpty())
            <p class="sis-muted" style="font-size:12.5px;margin:0 0 10px;">Nog geen vrijstellingsbesluiten voor deze student.</p>
          @else
            <div class="iuasr-dash-tbl-card" style="border:0;margin-bottom:8px;">
              <table class="iuasr-dash-tbl">
                <thead><tr><th>Vak</th><th>Grondslag</th><th>Besluit</th><th>Status</th><th></th></tr></thead>
                <tbody>
                  @foreach ($besluiten as $b)
                    <tr>
                      <td class="nm">{{ $b->vak?->code }} · {{ $b->vak?->naam }}</td>
                      <td>{{ $b->grondslag?->label() }}</td>
                      <td>{{ $b->besluit }}<br><small class="sis-muted">{{ $b->besluit_datum?->format('d-m-Y') }} · {{ $b->aangemaaktDoor?->naam }}</small></td>
                      <td><span class="iuasr-dash-status {{ $b->status->badge() }}">{{ $b->status->label() }}</span></td>
                      <td style="text-align:right;white-space:nowrap;">
                        @if ($b->status === App\Enums\VrijstellingsbesluitStatus::Open)
                          @if ($magVrij)
                            <form method="POST" action="{{ route('vrijstellingsbesluiten.verwerken', $b) }}" style="display:inline;">@csrf<button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Verwerken</button></form>
                          @endif
                          @if ($magBesluit)
                            <form method="POST" action="{{ route('vrijstellingsbesluiten.annuleren', $b) }}" style="display:inline;" onsubmit="return confirm('Besluit annuleren?');">@csrf<button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Annuleren</button></form>
                          @endif
                        @elseif ($b->status === App\Enums\VrijstellingsbesluitStatus::Verwerkt)
                          <small class="sis-muted">{{ $b->verwerktDoor?->naam }}<br>{{ $b->verwerkt_op?->format('d-m-Y') }}</small>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif

          @if ($magBesluit && $besluitBaar->isNotEmpty())
            <form method="POST" action="{{ route('vrijstellingsbesluiten.store', $student) }}" class="sis-form" style="margin-top:6px;">
              @csrf
              <h4 style="margin:0 0 10px;font-size:13px;">Vrijstelling voorstellen aan Studentenzaken</h4>
              <div class="sis-fld-row sis-fld-row--2">
                <div class="sis-fld"><label>Vak</label>
                  <select name="vak_id" required>
                    <option value="">Kies een toegewezen vak…</option>
                    @foreach ($besluitBaar as $t)<option value="{{ $t->vak_id }}">{{ $t->vak->code }} · {{ $t->vak->naam }} ({{ \App\Support\Ec::toon($t->vak->ec) }} EC)</option>@endforeach
                  </select>
                </div>
                <div class="sis-fld"><label>Grondslag</label>
                  <select name="grondslag" required>@foreach ($grondslagen as $val => $lbl)<option value="{{ $val }}">{{ $lbl }}</option>@endforeach</select>
                </div>
              </div>
              <div class="sis-fld-row sis-fld-row--2">
                <div class="sis-fld"><label>Besluit-referentie</label><input type="text" name="besluit" required placeholder="bv. EC-2026-014"></div>
                <div class="sis-fld"><label>Besluitdatum</label><input type="date" name="besluit_datum" required></div>
              </div>
              <div class="sis-fld"><label>Toelichting (optioneel)</label><textarea name="toelichting" rows="2"></textarea></div>
              <div style="display:flex;justify-content:flex-end;">
                <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Naar Studentenzaken sturen</button>
              </div>
            </form>
          @endif
        </div>
      @endif

      <p class="sis-tblnote" style="margin-top:12px;">Een vrijstelling is <b>geen cijfer</b>: het vak telt als behaald met de volledige EC (vermelding <b>VR</b> op de cijferlijst), zonder eindcijfer. Leg altijd de referentie van het examencommissie-besluit vast.</p>
    </div>
  @endif
</section>

@push('scripts')
<script>
  document.querySelectorAll('.sis-subtab[data-vh]').forEach(function (b) {
    b.addEventListener('click', function () {
      document.querySelectorAll('.sis-subtab[data-vh]').forEach(function (x){ x.classList.remove('is-active'); });
      b.classList.add('is-active');
      var t = b.getAttribute('data-vh');
      document.querySelectorAll('.vh-panel').forEach(function (p){ p.hidden = p.getAttribute('data-vhpanel') !== t; });
    });
  });
  document.querySelectorAll('.vh-blok-btn').forEach(function (b) {
    b.addEventListener('click', function () {
      var group = b.getAttribute('data-group');
      document.querySelectorAll('.vh-blok-btn[data-group="' + group + '"]').forEach(function (x){ x.classList.remove('is-active'); });
      b.classList.add('is-active');
      var t = b.getAttribute('data-vhb');
      var panel = document.querySelector('.vh-panel[data-vhpanel="' + group + '"]');
      if (panel) panel.querySelectorAll('.vh-blok').forEach(function (p){ p.hidden = p.getAttribute('data-vhbpanel') !== t; });
    });
  });
</script>
@endpush

{{-- PANEEL: Cijfers --}}
<section class="sis-tabpanel" data-panel="cijfers">
  @unless ($magCijfers)
    <div class="sis-locked-panel">
      <span class="sis-locked-panel__ic"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
      <h3>Cijfers zijn afgeschermd voor {{ $u->rol->label() }}</h3>
      <p>Deze rol beheert inschrijvingen en persoonsgegevens, maar heeft geen inzage in behaalde cijfers of resultaten. Cijferinzage is voorbehouden aan docenten (eigen vak), de examencommissie en de directie.</p>
      <span class="who"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> Toegang via rol <b>Examencommissie</b> of <b>Directie</b></span>
    </div>
  @else
    <div class="sis-card">
      <div class="sis-card__hd"><h3>Cijferoverzicht</h3><span class="hint">leesweergave · inzage gelogd</span></div>
      @php $totaalEc = $cijferVakken->sum(fn($c) => $c['ec'] ?? 0); @endphp
      <div class="iuasr-dash-tbl-card" style="border:0;">
        <table class="iuasr-dash-tbl">
          <thead><tr><th>Vak</th><th>Code</th><th>EC-waarde</th><th>Eindcijfer</th><th>EC behaald</th></tr></thead>
          <tbody>
            @forelse ($cijferVakken as $c)
              @php $vak = $c['vak']; $eind = $c['eind']; @endphp
              <tr>
                <td class="nm">{{ $vak->naam }}</td>
                <td class="tnum">{{ $vak->code }}</td>
                <td class="tnum">{{ \App\Support\Ec::toon($vak->ec) }}</td>
                <td>
                  @if ($eind['status']==='vr')<span class="sis-pill-soft">VR</span>
                  @elseif ($eind['status']==='cijfer')<b class="tnum">{{ number_format($eind['cijfer'],1,',','') }}</b>
                  @elseif ($eind['status']==='onvolledig')<span class="sis-muted">onvolledig</span>
                  @else<span class="sis-muted">—</span>@endif
                </td>
                <td class="tnum">{{ \App\Support\Ec::toon($c['ec']) }}</td>
              </tr>
            @empty
              <tr><td colspan="5" style="padding:24px;text-align:center;color:var(--blackAltText);">Nog geen resultaten geregistreerd voor deze student.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if ($cijferVakken->isNotEmpty())
        <dl class="sis-dl" style="margin-top:14px;grid-template-columns:150px 1fr;">
          <dt>EC totaal behaald</dt><dd><b class="tnum">{{ $totaalEc }}</b></dd>
        </dl>
      @endif
    </div>
  @endunless
</section>

@push('scripts')
<script>
  // Tabs
  document.querySelectorAll('.sis-tab').forEach(function (t) {
    t.addEventListener('click', function () {
      if (t.classList.contains('is-locked')) return;
      var key = t.getAttribute('data-tab');
      document.querySelectorAll('.sis-tab').forEach(function (x){ x.classList.remove('is-active'); });
      t.classList.add('is-active');
      document.querySelectorAll('.sis-tabpanel').forEach(function (p){
        p.classList.toggle('is-active', p.getAttribute('data-panel') === key);
      });
    });
  });

  // BSN tonen (gelogd via server)
  (function () {
    var btn = document.getElementById('bsn-toggle');
    if (!btn) return;
    var val = document.getElementById('bsn-value');
    var shown = false, opgehaald = null;
    btn.addEventListener('click', async function () {
      if (!shown && opgehaald === null) {
        try {
          var r = await fetch(btn.dataset.url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
          var j = await r.json();
          opgehaald = j.bsn || 'niet vastgelegd';
        } catch (e) { opgehaald = 'fout bij ophalen'; }
      }
      shown = !shown;
      val.textContent = shown ? opgehaald : '••••••••';
      btn.lastChild.textContent = shown ? ' Verbergen' : ' Tonen';
    });
  })();
</script>
@endpush
@endsection
