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
</head>
<body>
@php
  $u = auth()->user();
  $icon = function (?string $k): string {
    return match ($k) {
      'students' => '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
      'book' => '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
      'cert' => '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M8.21 13.89 7 22l5-3 5 3-1.21-8.11"/></svg>',
      'report' => '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg>',
      'users' => '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
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
      <span class="role role--{{ $u->rol->value }}">{{ $u->rol->label() }}</span>
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
  </main>
</div>
</body>
</html>
