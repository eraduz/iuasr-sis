@extends('layouts.app')

@section('titel', 'Audit-log')

@php
  $badge = [
    'studentenzaken' => 'r-studentenzaken', 'docent' => 'r-docent',
    'examencommissie' => 'r-examencommissie', 'directie' => 'r-directie', 'beheerder' => 'r-beheerder',
  ];
@endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Audit-log</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Audit-log</h1>
    <div class="summary">Gevoelige acties — wie zag of wijzigde welk record, wanneer en vanaf welk IP</div>
  </div>
</div>

<form method="GET" action="{{ route('audit-log') }}" class="iuasr-dash-filters" style="grid-template-columns:1fr 1fr;">
  <select name="actie" onchange="this.form.submit()">
    <option value="">Alle acties</option>
    @foreach (['inzage'=>'Inzage','aanmaak'=>'Aanmaak','wijziging'=>'Wijziging','uitgifte'=>'Uitgifte','verwijdering'=>'Verwijdering'] as $k=>$v)
      <option value="{{ $k }}" @selected($actie===$k)>{{ $v }}</option>
    @endforeach
  </select>
  <select name="rol" onchange="this.form.submit()">
    <option value="">Alle rollen</option>
    @foreach (App\Enums\Rol::cases() as $r)<option value="{{ $r->value }}" @selected($rol===$r->value)>{{ $r->label() }}</option>@endforeach
  </select>
</form>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th style="width:160px;">Tijdstip</th><th>Gebruiker</th><th>Rol</th><th>Actie</th><th>Object</th><th>IP</th></tr></thead>
    <tbody>
      @forelse ($events as $e)
        <tr>
          <td class="dt">{{ $e->gelogd_op?->format('d-m-Y · H:i:s') }}</td>
          <td class="nm">{{ $e->user?->naam ?? 'onbekend' }}</td>
          <td>@if($e->rol)<span class="sis-rolebadge {{ $badge[$e->rol] ?? '' }}">{{ App\Enums\Rol::tryFrom($e->rol)?->label() ?? $e->rol }}</span>@endif</td>
          <td><b>{{ ucfirst($e->actie) }}</b>@if($e->veld) · {{ $e->veld }}@endif</td>
          <td>{{ $e->onderwerp_type }}@if($e->onderwerp_id) #{{ $e->onderwerp_id }}@endif</td>
          <td class="dt">{{ $e->ip_adres ?? '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="6"><div class="iuasr-dash-empty" style="border:0;"><h3>Nog geen events</h3><p>Zodra er gevoelige acties plaatsvinden (BSN-inzage, mutaties, uitgifte) verschijnen ze hier.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
  @if ($events->hasPages())
    <div class="iuasr-dash-pagination">
      <div class="iuasr-dash-pagination__range">Toont <b>{{ $events->firstItem() }}–{{ $events->lastItem() }}</b> van <b>{{ $events->total() }}</b> events</div>
      <div class="iuasr-dash-pagination__nav">
        <a href="{{ $events->previousPageUrl() ?: '#' }}"><button {{ $events->onFirstPage() ? 'disabled' : '' }}>‹</button></a>
        <button class="is-current">{{ $events->currentPage() }}</button>
        <a href="{{ $events->nextPageUrl() ?: '#' }}"><button {{ $events->hasMorePages() ? '' : 'disabled' }}>›</button></a>
      </div>
    </div>
  @endif
</div>
<p class="sis-tblnote">De audit-log is <b>alleen-lezen</b> en kan niet worden gewijzigd of verwijderd. Elke inzage van een BSN, elke mutatie en elke uitgifte wordt vastgelegd met gebruiker, rol, tijdstip en IP.</p>
@endsection
