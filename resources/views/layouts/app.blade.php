<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('titel', 'IUASR SIS') — IUASR SIS</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Fira+Sans:wght@200;300;400;500;600;700&display=swap" rel="stylesheet">
{{-- Leidend design system: overgenomen uit IUASR/iuasr-sis. Laadvolgorde: sis.css → plugin-dash.css --}}
<link rel="stylesheet" href="{{ asset('assets/css/sis.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/iuasr-plugin-dash.css') }}">
@stack('head')
</head>
<body>

<div id="sis-header"></div>

<div class="iuasr-dash-app">
  <nav class="iuasr-dash-sidebar" id="sis-sidebar" aria-label="Hoofdmenu"></nav>

  <main class="iuasr-dash-main">
    @yield('inhoud')
  </main>
</div>

{{-- Rolbewuste shell (header + sidebar + rolwisselaar). In Fase 3 volgt de
     rol uit de Entra-sessie i.p.v. localStorage; nu blijft de demo-wisselaar. --}}
<script src="{{ asset('assets/js/sis-shell.js') }}"></script>
<script>SIS.render({ active: '@yield('actief', 'dashboard')' });</script>
@stack('scripts')
</body>
</html>
