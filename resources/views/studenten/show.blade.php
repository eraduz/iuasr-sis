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
        <span>{{ $huidige?->opleiding?->naam ?? 'Geen inschrijving' }}</span>
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

      {{-- Collegegeld — pro rata o.b.v. inschrijvingsduur, altijd actueel --}}
      @if (auth()->user()->magFinancieelInzien())
        <div class="sis-card" style="margin-top:16px;">
          <div class="sis-card__hd"><h3>Collegegeld</h3><span class="hint">pro rata · studiejaar 1 sep – 31 jul</span></div>
          @if ($financieel['jaarbedrag'] === null)
            <p class="sis-muted" style="font-size:13px;margin:0;">Geen collegegeldtarief ingesteld voor dit studiejaar.</p>
          @else
            <dl class="sis-dl">
              <dt>Jaarcollegegeld</dt><dd>€ {{ number_format($financieel['jaarbedrag'], 2, ',', '.') }}</dd>
              <dt>Maandbedrag</dt><dd>€ {{ number_format($financieel['maandbedrag'], 2, ',', '.') }} <span class="sis-muted" style="font-size:11px;">· ÷ 12</span></dd>
              <dt>Ingeschreven</dt><dd>{{ $financieel['maanden'] }} {{ $financieel['maanden'] === 1 ? 'maand' : 'maanden' }}</dd>
              <dt>Verschuldigd</dt><dd><b>€ {{ number_format($financieel['verschuldigd'], 2, ',', '.') }}</b> <span class="sis-muted" style="font-size:11px;">o.b.v. huidige maand</span></dd>
              <dt>Betaald</dt><dd>€ {{ number_format($financieel['betaald'], 2, ',', '.') }}</dd>
            </dl>
            @if ($financieel['openstaand'] > 0)
              <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-top:12px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                <span>Nog te betalen: <b>€ {{ number_format($financieel['openstaand'], 2, ',', '.') }}</b></span>
              </div>
            @elseif ($financieel['terugbetaling'] > 0)
              <div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-top:12px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                <span>Terug te betalen aan student: <b>€ {{ number_format($financieel['terugbetaling'], 2, ',', '.') }}</b></span>
              </div>
            @elseif ($financieel['vooruitbetaald'] > 0)
              <div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-top:12px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span>Vooruitbetaald (tegoed): <b>€ {{ number_format($financieel['vooruitbetaald'], 2, ',', '.') }}</b> <span class="sis-muted" style="font-size:11px;">· nog ingeschreven, verrekend met resterende maanden</span></span>
              </div>
            @else
              <div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-top:12px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                <span>Collegegeld volledig voldaan.</span>
              </div>
            @endif
          @endif
        </div>
      @endif

      {{-- Interne notities — direct onder Contact, altijd zichtbaar (Studentenzaken/Beheer) --}}
      @if (auth()->user()->magInschrijvingBeheren())
        <div class="sis-card" id="notities" style="margin-top:16px;">
          <div class="sis-card__hd"><h3>Notities</h3><span class="hint">Intern · Studentenzaken &amp; Beheer</span></div>

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

          <div class="iuasr-dash-note-list">
            @forelse ($student->notities as $n)
              <div class="iuasr-dash-note">
                <small>{{ $n->created_at->format('d-m-Y · H:i') }} · {{ $n->gebruiker?->naam ?? 'onbekend' }}</small>
                <div style="display:flex;gap:10px;align-items:flex-start;justify-content:space-between;">
                  <span style="white-space:pre-wrap;">{{ $n->tekst }}</span>
                  <form method="POST" action="{{ route('studenten.notities.destroy', [$student, $n]) }}" onsubmit="return confirm('Deze notitie verwijderen?');" style="flex:none;">
                    @csrf @method('DELETE')
                    <button type="submit" title="Verwijderen" style="background:none;border:0;cursor:pointer;color:var(--blackAltText);padding:2px;line-height:0;">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                  </form>
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
        </dl>
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
                        <tr><td class="tnum">{{ $vak->code }}</td><td class="nm">{{ $vak->naam }}</td><td class="tnum">{{ $vak->ec }}</td><td>{{ $vak->docent?->achternaam ?? '—' }}</td></tr>
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
                  <td class="tnum">{{ $t->vrijstelling_ec ?? $t->vak?->ec }}</td>
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
                  <option value="{{ $t->id }}" @selected(old('vaktoewijzing_id') == $t->id)>{{ $t->vak->code }} · {{ $t->vak->naam }} ({{ $t->vak->ec }} EC)</option>
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
                <td class="tnum">{{ $vak->ec }}</td>
                <td>
                  @if ($eind['status']==='vr')<span class="sis-pill-soft">VR</span>
                  @elseif ($eind['status']==='cijfer')<b class="tnum">{{ number_format($eind['cijfer'],1,',','') }}</b>
                  @elseif ($eind['status']==='onvolledig')<span class="sis-muted">onvolledig</span>
                  @else<span class="sis-muted">—</span>@endif
                </td>
                <td class="tnum">{{ $c['ec'] === null ? '—' : $c['ec'] }}</td>
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
