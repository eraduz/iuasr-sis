<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kies een module — IUASR</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Fira+Sans:wght@200;300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/sis.css') }}?v={{ filemtime(public_path('assets/css/sis.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/iuasr-plugin-dash.css') }}?v={{ filemtime(public_path('assets/css/iuasr-plugin-dash.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/sis-theme.css') }}?v={{ filemtime(public_path('assets/css/sis-theme.css')) }}">
{{-- Donker is de standaard (energiezuinig); een bewuste 'licht'-keuze blijft behouden. --}}
<script>try{var t=localStorage.getItem('sis-theme');document.documentElement.setAttribute('data-theme',t==='light'?'light':'dark');}catch(e){document.documentElement.setAttribute('data-theme','dark');}</script>
</head>
<body>
@php
  $u = auth()->user();
  // Naast de rol de opleiding(en)/cursus(sen) tonen, zodat meteen duidelijk is
  // van WELKE opleiding een directeur is (bijv. "Directie · MGV").
  $rolExtra = '';
  if ($u->rol === \App\Enums\Rol::Directie) {
    $rolExtra = $u->opleidingen->isNotEmpty()
      ? ' · '.$u->opleidingen->sortBy('code')->pluck('code')->implode(' · ')
      : ' · geen opleiding';
  } elseif ($u->rol === \App\Enums\Rol::Cursusadministratie && $u->gedirigeerdeCursussen->isNotEmpty()) {
    $rolExtra = ' · '.$u->gedirigeerdeCursussen->sortBy('code')->pluck('code')->implode(' · ');
  }
  $icon = function (?string $k): string {
    return match ($k) {
      'students' => '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
      'book' => '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
      'cert' => '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M8.21 13.89 7 22l5-3 5 3-1.21-8.11"/></svg>',
      'report' => '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg>',
      'users' => '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
      'db' => '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>',
      'log' => '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
      'backup' => '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><polyline points="21 3 21 8 16 8"/></svg>',
      'building' => '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 9h.01M15 9h.01M9 13h.01M15 13h.01M9 17h.01M15 17h.01"/></svg>',
      default => '',
    };
  };
@endphp

<div class="sis-modulepage">
  <header class="sis-modulepage__bar">
    <div class="sis-modulepage__brand">
      <img src="{{ asset('assets/img/logo-dark.png') }}" alt="IUASR">
      <span class="sis-pill-soft" style="letter-spacing:0.04em;">Platform</span>
    </div>
    <div class="sis-modulepage__user">
      <span class="role role--{{ $u->rol->value }}">{{ $u->rol->label() }}{{ $rolExtra }}</span>
      <span class="name">{{ $u->naam }}</span>
      <form method="POST" action="{{ route('logout') }}" style="display:inline;">
        @csrf
        <button type="submit" class="logout" style="background:none;border:0;cursor:pointer;font:inherit;color:inherit;">Uitloggen</button>
      </form>
    </div>
  </header>

  <main class="sis-modulepage__body">
    <h1>Kies een module</h1>
    <p class="sub">Welkom, {{ $u->naam }}. Selecteer met welk onderdeel u wilt werken.</p>

    <div class="sis-modulegrid">
      @foreach ($modules as $rij)
        @php $m = $rij['module']; @endphp
        @if ($rij['bruikbaar'] && $rij['route'])
          <a class="sis-moduletile" href="{{ route($rij['route']) }}">
            <span class="sis-moduletile__icon">{!! $icon($m->icoon) !!}</span>
            <span class="sis-moduletile__naam">{{ $m->naam }}</span>
            <span class="sis-moduletile__oms">{{ $m->omschrijving }}</span>
            <span class="sis-moduletile__cta">Openen →</span>
          </a>
        @else
          <div class="sis-moduletile is-disabled" aria-disabled="true">
            <span class="sis-moduletile__icon">{!! $icon($m->icoon) !!}</span>
            <span class="sis-moduletile__naam">{{ $m->naam }}</span>
            <span class="sis-moduletile__oms">{{ $m->omschrijving }}</span>
            <span class="sis-moduletile__badge">{{ $rij['toegankelijk'] ? 'Binnenkort' : (! $m->actief ? 'Binnenkort' : 'Geen toegang') }}</span>
          </div>
        @endif
      @endforeach
    </div>

    @if ($cursussen->isNotEmpty())
      <h2 style="font-family:'DM Serif Display',serif;font-size:22px;margin:34px 0 6px;color:var(--priColor100,#1E1446);">Cursussen</h2>
      <p class="sub" style="margin:0 0 14px;">Kies uw cursus om er direct in te werken.</p>
      <div class="sis-modulegrid">
        @foreach ($cursussen as $cursus)
          <a class="sis-moduletile" href="{{ route('cursussen.cursus', $cursus) }}">
            <span class="sis-moduletile__icon">{!! $icon('book') !!}</span>
            <span class="sis-moduletile__naam">{{ $cursus->naam }}</span>
            <span class="sis-moduletile__oms">Cursuscode {{ $cursus->code }}</span>
            <span class="sis-moduletile__cta">Openen →</span>
          </a>
        @endforeach
      </div>
    @endif

    @if ($u->rol->magCursusInzien() && $u->rol->value === 'bestuur')
      <h2 style="font-family:'DM Serif Display',serif;font-size:22px;margin:34px 0 14px;color:var(--priColor100,#1E1446);">Bestuur</h2>
      <div class="sis-modulegrid">
        <a class="sis-moduletile" href="{{ route('bestuur') }}">
          <span class="sis-moduletile__icon">{!! $icon('building') !!}</span>
          <span class="sis-moduletile__naam">Globaal overzicht</span>
          <span class="sis-moduletile__oms">Instellingsbreed beeld: studenten, onderwijs, aanwezigheid, financiën en cursussen.</span>
          <span class="sis-moduletile__cta">Openen →</span>
        </a>
      </div>
    @endif

    @if ($u->rol->value === 'beheerder')
      <h2 style="font-family:'DM Serif Display',serif;font-size:22px;margin:34px 0 14px;color:var(--priColor100,#1E1446);">Systeembeheer</h2>
      <div class="sis-modulegrid">
        <a class="sis-moduletile" href="{{ route('bestuur') }}">
          <span class="sis-moduletile__icon">{!! $icon('building') !!}</span>
          <span class="sis-moduletile__naam">Bestuursoverzicht</span>
          <span class="sis-moduletile__oms">Instellingsbreed overzicht (zoals het Schoolbestuur ziet).</span>
          <span class="sis-moduletile__cta">Openen →</span>
        </a>
        <a class="sis-moduletile" href="{{ route('gebruikers') }}">
          <span class="sis-moduletile__icon">{!! $icon('users') !!}</span>
          <span class="sis-moduletile__naam">Gebruikers &amp; rollen</span>
          <span class="sis-moduletile__oms">Accounts, rollen en toewijzingen beheren.</span>
          <span class="sis-moduletile__cta">Openen →</span>
        </a>
        <a class="sis-moduletile" href="{{ route('opzoektabellen') }}">
          <span class="sis-moduletile__icon">{!! $icon('db') !!}</span>
          <span class="sis-moduletile__naam">Opzoektabellen</span>
          <span class="sis-moduletile__oms">Referentiedata: opleidingen, klassen, tarieven en meer.</span>
          <span class="sis-moduletile__cta">Openen →</span>
        </a>
        <a class="sis-moduletile" href="{{ route('audit-log') }}">
          <span class="sis-moduletile__icon">{!! $icon('log') !!}</span>
          <span class="sis-moduletile__naam">Audit-log</span>
          <span class="sis-moduletile__oms">Wie deed wat en wanneer — inzage en mutaties.</span>
          <span class="sis-moduletile__cta">Openen →</span>
        </a>
        <a class="sis-moduletile" href="{{ route('backup') }}">
          <span class="sis-moduletile__icon">{!! $icon('backup') !!}</span>
          <span class="sis-moduletile__naam">Back-up &amp; herstel</span>
          <span class="sis-moduletile__oms">Versleutelde recovery-back-up downloaden.</span>
          <span class="sis-moduletile__cta">Openen →</span>
        </a>
        <a class="sis-moduletile" href="{{ route('handleiding.technisch') }}">
          <span class="sis-moduletile__icon">{!! $icon('report') !!}</span>
          <span class="sis-moduletile__naam">Technische handleiding</span>
          <span class="sis-moduletile__oms">Beheer, architectuur en data-recovery.</span>
          <span class="sis-moduletile__cta">Openen →</span>
        </a>
      </div>
    @endif
  </main>
</div>
</body>
</html>
