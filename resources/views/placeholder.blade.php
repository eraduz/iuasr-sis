@extends('layouts.app')

@section('titel', 'In ontwikkeling')

@section('inhoud')
<div class="iuasr-dash-empty">
  <span class="iuasr-dash-empty__icon">
    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
  </span>
  <h3>Dit scherm volgt in een latere fase</h3>
  <p>De routestructuur en de rolscheiding staan al. Het functionele scherm — overgenomen uit de leidende designs in <b>IUASR/iuasr-sis</b> — wordt gebouwd in Fase 3 (kern-CRUD) of Fase 4 (cijfers).</p>
  <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('dashboard') }}">Terug naar dashboard</a>
</div>
@endsection
