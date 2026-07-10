@php
    /** @var \App\Models\User $u */
    $u = auth()->user();
    $rol = $u->rol;
@endphp
<header class="iuasr-dash-header">
  <div class="iuasr-dash-header__inner">
    <div class="iuasr-dash-header__brand">
      <a class="iuasr-dash-header__logo" href="{{ route('dashboard') }}" aria-label="IUASR SIS">
        <img src="{{ asset('assets/img/logo-dark.png') }}" alt="IUASR">
      </a>
      <span class="role role--{{ $rol->value }}">{{ $rol->label() }}</span>
      <span class="sis-pill-soft" style="letter-spacing:0.04em;">SIS · Studentbeheer</span>
      <a class="sis-help-link" href="{{ route('modules.kiezen') }}" title="Naar het modulekeuzescherm">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        <span>Modules</span>
      </a>
      <a class="sis-help-link" href="{{ route('handleiding.medewerkers') }}" target="_blank" rel="noopener" title="Handleiding voor medewerkers (PDF)">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <span>Help</span>
      </a>
    </div>
    <div class="iuasr-dash-header__spacer"></div>
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
