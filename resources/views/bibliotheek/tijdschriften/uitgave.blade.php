@extends('layouts.app')

@section('titel', $uitgave->omschrijving())

@section('inhoud')
@php $magBeheer = auth()->user()->magBibliotheekBeheren(); @endphp

<div class="sis-crumb"><a href="{{ route('bibliotheek.dashboard') }}">Bibliotheek</a><span class="sep">›</span><a href="{{ route('bibliotheek.publicaties.show', $uitgave->tijdschrift) }}">{{ $uitgave->tijdschrift->titel }}</a><span class="sep">›</span><b>{{ $uitgave->uitgavenummer }}</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1 dir="auto">{{ $uitgave->tijdschrift->titel }} — {{ $uitgave->uitgavenummer }}</h1>
    <div class="summary">
      @if ($uitgave->publicatiedatum){{ $uitgave->publicatiedatum->format('d-m-Y') }} · @endif
      {{ $uitgave->artikelen->count() }} {{ $uitgave->artikelen->count() === 1 ? 'artikel' : 'artikelen' }}
      @if ($uitgave->locatie) · {{ $uitgave->locatie }}@endif
    </div>
  </div>
</div>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Artikel</th><th>Auteur(s)</th><th>Pagina's</th><th>Trefwoorden</th></tr></thead>
    <tbody>
      @forelse ($uitgave->artikelen as $a)
        <tr>
          <td class="nm" dir="auto">{{ $a->titel }}@if ($a->beschrijving)<br><small class="sis-muted" dir="auto">{{ \Illuminate\Support\Str::limit($a->beschrijving, 100) }}</small>@endif</td>
          <td>{{ $a->auteurs->pluck('naam')->implode(', ') ?: '—' }}</td>
          <td class="tnum">{{ $a->paginas ?? '—' }}</td>
          <td>{{ $a->trefwoorden ?? '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="4"><div class="iuasr-dash-empty" style="border:0;"><h3>Nog geen artikelen</h3><p class="sis-muted">Voeg hieronder het eerste artikel toe.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

@if ($magBeheer)
  <form method="POST" action="{{ route('bibliotheek.artikelen.store', $uitgave) }}" class="sis-card sis-form" style="margin-top:12px; max-width:820px;">
    @csrf
    <h3>Artikel toevoegen</h3>
    <div class="sis-fld"><label>Artikeltitel <span class="req">*</span></label><input type="text" name="titel" maxlength="255" required dir="auto"></div>
    <div class="sis-fld">
      <label>Auteur(s)</label>
      <div id="art-auteurs">
        <input type="text" name="auteurs[]" maxlength="255" dir="auto" placeholder="Naam van de auteur" style="margin-bottom:6px;">
      </div>
      <button type="button" class="iuasr-dash-btn iuasr-dash-btn--sm" id="art-auteur-erbij">Auteur toevoegen</button>
    </div>
    <div class="sis-fld-row sis-fld-row--2">
      <div class="sis-fld"><label>Pagina's</label><input type="text" name="paginas" maxlength="30" placeholder="bijv. 12-27"></div>
      <div class="sis-fld"><label>Trefwoorden</label><input type="text" name="trefwoorden" maxlength="255" dir="auto" placeholder="Komma-gescheiden"></div>
    </div>
    <div class="sis-fld"><label>Korte beschrijving</label><textarea name="beschrijving" maxlength="2000" dir="auto"></textarea></div>
    <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Artikel toevoegen</button></div></div>
  </form>
@endif
@endsection

@push('scripts')
<script>
(function () {
  var knop = document.getElementById('art-auteur-erbij');
  if (!knop) return;
  knop.addEventListener('click', function () {
    var invoer = document.createElement('input');
    invoer.type = 'text';
    invoer.name = 'auteurs[]';
    invoer.placeholder = 'Naam van de auteur';
    invoer.setAttribute('dir', 'auto');
    invoer.style.marginBottom = '6px';
    document.getElementById('art-auteurs').appendChild(invoer);
    invoer.focus();
  });
})();
</script>
@endpush
