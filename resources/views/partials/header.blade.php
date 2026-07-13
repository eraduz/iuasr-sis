@php
    /** @var \App\Models\User $u */
    $u = auth()->user();
    $rol = $u->rol;
@endphp
<header class="iuasr-dash-header">
  <div class="iuasr-dash-header__inner">
    <div class="iuasr-dash-header__brand">
      <a class="iuasr-dash-header__logo" href="{{ route('dashboard') }}" aria-label="IUASR Management Systeem">
        <img src="{{ asset('assets/img/logo-dark.png') }}" alt="IUASR">
      </a>
      <span class="role role--{{ $rol->value }}">{{ $rol->label() }}</span>
      @if ($rol === App\Enums\Rol::Directie)
        @if ($u->opleidingen->isNotEmpty())
          <span class="sis-pill-soft" style="letter-spacing:.03em;" title="Uw opleiding(en)">{{ $u->opleidingen->sortBy('code')->pluck('code')->implode(' · ') }}</span>
        @else
          <span class="sis-pill-soft" style="letter-spacing:.03em;color:var(--secColor100,#C8102E);" title="Geen opleiding toegewezen — vraag Beheer om toewijzing">geen opleiding</span>
        @endif
      @elseif ($rol === App\Enums\Rol::Cursusadministratie && $u->gedirigeerdeCursussen->isNotEmpty())
        <span class="sis-pill-soft" style="letter-spacing:.03em;" title="Uw cursus(sen)">{{ $u->gedirigeerdeCursussen->sortBy('code')->pluck('code')->implode(' · ') }}</span>
      @endif
      @php $inCursus = request()->routeIs('cursussen.*') || request()->routeIs('cursisten*'); @endphp
      <span class="sis-pill-soft" style="letter-spacing:0.04em;">{{ $inCursus ? 'Cursussen Administratie' : 'Studentbeheer' }}</span>
      <a class="sis-help-link" href="{{ route('modules.kiezen') }}" title="Naar het modulekeuzescherm">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        <span>Modules</span>
      </a>
      <a class="sis-help-link" href="{{ route('handleiding.web') }}" target="_blank" rel="noopener" title="Handleiding — met hoofdstuknavigatie">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <span>Help</span>
      </a>
    </div>
    <div class="iuasr-dash-header__spacer"></div>

    <div class="sis-headtools">
      {{-- Read-only weekkalender met weeknummers (informatief, elke module). --}}
      <div class="sis-weekcal-wrap">
        <button type="button" class="sis-iconbtn" id="weekcalBtn" aria-haspopup="true" aria-expanded="false" aria-controls="weekcalPop" title="Weekkalender (weeknummers)">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </button>
        <div class="sis-weekcal-pop" id="weekcalPop" hidden>
          @include('partials.weekkalender')
        </div>
      </div>

      {{-- Donkere modus aan/uit (voorkeur onthouden). --}}
      <button type="button" class="sis-iconbtn sis-theme-btn" id="themeBtn" title="Licht/donker wisselen" aria-label="Licht of donker thema">
        <span class="ic-moon" aria-hidden="true"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></span>
        <span class="ic-sun" aria-hidden="true"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="6.34" y2="6.34"/><line x1="17.66" y1="17.66" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="6.34" y2="17.66"/><line x1="17.66" y1="6.34" x2="19.07" y2="4.93"/></svg></span>
      </button>
    </div>

    <div class="iuasr-dash-header__user" style="margin-left:6px;">
      <span class="iuasr-dash-header__user-link">
        <span class="avatar" aria-hidden="true">{{ mb_substr($u->naam, 0, 1) }}</span>
        <span class="name">{{ $u->naam }}</span>
      </span>
      <form method="POST" action="{{ route('logout') }}" style="display:inline;">
        @csrf
        <button type="submit" class="logout" style="background:none;border:0;cursor:pointer;font:inherit;">Uitloggen</button>
      </form>
    </div>
  </div>
</header>

@push('scripts')
<script>
  (function () {
    // Licht/donker wisselen + onthouden.
    var tbtn = document.getElementById('themeBtn');
    if (tbtn) {
      tbtn.addEventListener('click', function () {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var next = isDark ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        try { localStorage.setItem('sis-theme', next); } catch (e) {}
      });
    }
    // Weekkalender-popover openen/sluiten.
    var cbtn = document.getElementById('weekcalBtn');
    var pop = document.getElementById('weekcalPop');
    if (cbtn && pop) {
      var sluit = function () { pop.setAttribute('hidden', ''); cbtn.setAttribute('aria-expanded', 'false'); };
      cbtn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (pop.hasAttribute('hidden')) { pop.removeAttribute('hidden'); cbtn.setAttribute('aria-expanded', 'true'); }
        else { sluit(); }
      });
      document.addEventListener('click', function (e) {
        if (!pop.hasAttribute('hidden') && !pop.contains(e.target) && !cbtn.contains(e.target)) { sluit(); }
      });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { sluit(); } });
    }
  })();
</script>
@endpush
