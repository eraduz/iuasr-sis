@extends('layouts.app')

@section('titel', 'Boekreeksen')

@section('inhoud')
@php $magBeheer = auth()->user()->magBibliotheekBeheren(); @endphp

<div class="sis-crumb"><a href="{{ route('bibliotheek.dashboard') }}">Bibliotheek</a><span class="sep">›</span><b>Boekreeksen</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Boekreeksen</h1>
    <div class="summary">{{ $reeksen->total() }} {{ $reeksen->total() === 1 ? 'reeks' : 'reeksen' }}</div>
  </div>
  @if ($magBeheer)
    <div class="iuasr-dash-vhead__actions">
      <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('bibliotheek.reeksen.create') }}">Boekreeks aanmaken</a>
    </div>
  @endif
</div>

<form method="GET" action="{{ route('bibliotheek.reeksen') }}" class="sis-toolbar" style="margin-bottom:12px;">
  <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op reekstitel">
  <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Zoeken</button>
</form>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Reeks</th><th style="text-align:right;">Delen</th><th class="row-act"></th></tr></thead>
    <tbody>
      @forelse ($reeksen as $reeks)
        <tr>
          <td class="nm" dir="auto"><a href="{{ route('bibliotheek.reeksen.show', $reeks) }}">{{ $reeks->titel }}</a></td>
          <td class="tnum" style="text-align:right;">{{ $reeks->delen_count }}</td>
          <td class="row-act"><a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('bibliotheek.reeksen.show', $reeks) }}">Bekijken</a></td>
        </tr>
      @empty
        <tr><td colspan="3"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen boekreeksen</h3><p class="sis-muted">Een reeks (bijv. Tafsir Ibn Kathir) bundelt de losse delen.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div style="margin-top:12px;">{{ $reeksen->links() }}</div>
@endsection
