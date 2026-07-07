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
  <div class="sis-grid-2">
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
    <div>
      <div class="sis-card">
        <div class="sis-card__hd"><h3>Contact</h3></div>
        <dl class="sis-dl">
          <dt>E-mail (IUASR)</dt><dd>{{ $student->email ?? '—' }}</dd>
          <dt>E-mail privé</dt><dd>{{ $student->email_prive ?? '—' }}</dd>
          <dt>Telefoon</dt><dd>{{ $student->telefoon ?? '—' }}</dd>
          <dt>Adres</dt><dd>{{ $student->adres ?? '—' }}@if($student->postcode || $student->woonplaats)<br>{{ trim(($student->postcode ?? '').' '.($student->woonplaats ?? '')) }}@endif</dd>
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

  {{-- Acties — direct beschikbaar bij de persoonsgegevens --}}
  @if (auth()->user()->magInschrijvingBeheren())
    <div class="sis-card" style="margin-top:16px;">
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
