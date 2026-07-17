<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('titel', 'IUASR Management Systeem') — IUASR Management Systeem</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Fira+Sans:wght@200;300;400;500;600;700&display=swap" rel="stylesheet">
{{-- Leidend design system (IUASR/iuasr-sis). Laadvolgorde: sis.css → plugin-dash.css --}}
<link rel="stylesheet" href="{{ asset('assets/css/sis.css') }}?v={{ filemtime(public_path('assets/css/sis.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/iuasr-plugin-dash.css') }}?v={{ filemtime(public_path('assets/css/iuasr-plugin-dash.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/sis-theme.css') }}?v={{ filemtime(public_path('assets/css/sis-theme.css')) }}">
{{-- Thema direct toepassen om een 'flash' te voorkomen. Donker is de STANDAARD
     (energiezuiniger); alleen wie bewust 'licht' koos, krijgt licht. --}}
<script>try{var t=localStorage.getItem('sis-theme');document.documentElement.setAttribute('data-theme',t==='light'?'light':'dark');}catch(e){document.documentElement.setAttribute('data-theme','dark');}</script>
@stack('head')
</head>
<body data-role="{{ auth()->user()?->rol?->value }}">

{{-- Header + sidebar worden server-side gerenderd op basis van de ingelogde rol
     (rolscheiding), niet vanuit de browser. --}}
@include('partials.header')

<div class="iuasr-dash-app">
  <nav class="iuasr-dash-sidebar" aria-label="Hoofdmenu">
    @include('partials.sidebar')
  </nav>

  <main class="iuasr-dash-main">
    {{-- Systeemmeldingen van de Beheerder (onderhoud, storing). Staan bovenaan
         ELKE pagina van elke module, dus vóór de flash-melding over de eigen
         actie: een storing gaat voor "Gelukt". --}}
    @include('partials.meldingen')

    @if (session('status'))
      <div class="iuasr-dash-alert iuasr-dash-alert--ok iuasr-dash-alert--flash" style="margin-bottom:16px;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <span><b>Gelukt.</b> {{ session('status') }}</span>
      </div>
    @endif
    @if (session('fout'))
      <div class="iuasr-dash-alert iuasr-dash-alert--danger iuasr-dash-alert--flash" style="margin-bottom:16px;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span><b>Let op.</b> {{ session('fout') }}</span>
      </div>
    @endif

    @yield('inhoud')

    <footer class="sis-appfoot" style="margin-top:28px; padding:14px 2px; border-top:1px solid var(--borderColor); color:var(--blackAltText); font-size:12px; display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap;">
      <span>IUASR Management Systeem</span>
      <span>versie {{ config('sis.versie') }}</span>
    </footer>
  </main>
</div>

@stack('scripts')
</body>
</html>
