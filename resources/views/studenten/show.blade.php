@extends('layouts.app')

@section('titel', $student->volledigeNaam().' · '.$student->studentnummer)

@php
    $u = auth()->user();
    $magBsn = $u->magBsnInzien();
@endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ route('studenten.index') }}">Studenten</a><span class="sep">›</span><b>{{ $student->studentnummer }} — {{ $student->volledigeNaam() }}</b></div>

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
      <span class="iuasr-dash-status s-approved" style="align-self:flex-start;">{{ ucfirst($huidige->status) }}</span>
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
    <div class="sis-card">
      <div class="sis-card__hd"><h3>Contact</h3></div>
      <dl class="sis-dl">
        <dt>E-mail (IUASR)</dt><dd>{{ $student->email ?? '—' }}</dd>
        <dt>E-mail privé</dt><dd>{{ $student->email_prive ?? '—' }}</dd>
        <dt>Telefoon</dt><dd>{{ $student->telefoon ?? '—' }}</dd>
        <dt>Adres</dt><dd>{{ $student->adres ?? '—' }}@if($student->postcode || $student->woonplaats)<br>{{ trim(($student->postcode ?? '').' '.($student->woonplaats ?? '')) }}@endif</dd>
      </dl>
    </div>
  </div>
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
          <dt>Status</dt><dd><span class="iuasr-dash-status s-approved">{{ ucfirst($huidige->status) }}</span></dd>
          <dt>Studentnummer</dt><dd class="tnum">{{ $student->studentnummer }}</dd>
        </dl>
      @else
        <p class="sis-muted">Geen actieve inschrijving.</p>
      @endif
    </div>
    <div class="sis-card">
      <div class="sis-card__hd"><h3>Inschrijfhistorie</h3></div>
      <ul class="iuasr-dash-log">
        @forelse ($student->inschrijvingen->sortByDesc('inschrijfdatum') as $i)
          <li><b>{{ ucfirst($i->status) }} — {{ $i->opleiding?->naam }}</b><time>{{ $i->inschrijfdatum?->format('d-m-Y') }} · {{ $i->periode?->code }}</time></li>
        @empty
          <li>Geen historie.</li>
        @endforelse
      </ul>
    </div>
  </div>
</section>

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
      <div class="sis-card__hd"><h3>Cijferoverzicht</h3><span class="hint">leesweergave</span></div>
      <div class="iuasr-dash-empty" style="border:0;padding:36px 20px;">
        <h3>Nog geen resultaten</h3>
        <p>Cijferinvoer en het genormaliseerde resultaatoverzicht worden gebouwd in <b>Fase 4</b>. De datamodel-structuur (resultaatregels met toetsonderdelen en weging) staat al.</p>
      </div>
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
