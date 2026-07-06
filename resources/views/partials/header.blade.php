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
