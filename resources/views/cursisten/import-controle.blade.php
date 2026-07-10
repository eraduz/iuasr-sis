@extends('layouts.app')

@section('titel', 'Import controleren')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('cursussen.dashboard') }}">Cursussen</a><span class="sep">›</span><a href="{{ route('cursisten') }}">Cursisten</a><span class="sep">›</span><b>Import controleren</b></div>

<div class="iuasr-dash-vhead"><div><h1>Import controleren</h1><div class="summary">{{ $bestandsnaam }}</div></div></div>

<div class="iuasr-dash-stats" style="margin-bottom:16px;">
  <div class="iuasr-dash-stat iuasr-dash-stat--ok"><span class="lbl">Wordt geïmporteerd</span><span class="val">{{ count($geldig) }}</span></div>
  <div class="iuasr-dash-stat {{ count($fouten) ? 'iuasr-dash-stat--alert' : '' }}"><span class="lbl">Overgeslagen</span><span class="val">{{ count($fouten) }}</span></div>
</div>

@if ($fouten)
  <div class="sis-card" style="margin-bottom:16px;">
    <div class="sis-card__hd"><h3>Overgeslagen regels</h3></div>
    <ul class="sis-muted" style="font-size:13px;margin:0;padding-left:18px;">
      @foreach ($fouten as $f)<li>{{ $f }}</li>@endforeach
    </ul>
  </div>
@endif

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Naam</th><th>E-mail</th><th>Telefoon</th><th>Woonplaats</th><th>Cursus</th></tr></thead>
    <tbody>
      @forelse ($geldig as $r)
        <tr>
          <td class="nm">{{ $r['naam'] }}</td>
          <td>{{ $r['email'] ?? '—' }}</td>
          <td>{{ $r['telefoon'] ?? '—' }}</td>
          <td>{{ $r['woonplaats'] ?? '—' }}</td>
          <td>{{ $r['cursus'] ?? '— (geen)' }}</td>
        </tr>
      @empty
        <tr><td colspan="5" style="padding:14px;color:var(--blackAltText);">Geen geldige regels om te importeren.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="sis-savebar">
  <span class="status">Controleer de gegevens; pas na <b>Definitief importeren</b> worden de cursisten aangemaakt.</span>
  <span class="grow"></span>
  <a class="iuasr-dash-btn" href="{{ route('cursisten') }}">Annuleren</a>
  @if (count($geldig))
    <form method="POST" action="{{ route('cursisten.import') }}" style="display:inline;">
      @csrf
      <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Definitief importeren ({{ count($geldig) }})</button>
    </form>
  @endif
</div>
@endsection
