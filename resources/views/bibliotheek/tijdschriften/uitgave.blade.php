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
    <thead><tr><th>Artikel</th><th>Auteur(s)</th><th>Pagina's</th><th>Trefwoorden</th><th class="row-act"></th></tr></thead>
    <tbody>
      @forelse ($uitgave->artikelen as $a)
        <tr>
          <td class="nm" dir="auto">{{ $a->titel }}@if ($a->beschrijving)<br><small class="sis-muted" dir="auto">{{ \Illuminate\Support\Str::limit($a->beschrijving, 100) }}</small>@endif</td>
          <td dir="auto">{{ $a->auteurs->pluck('naam')->implode(', ') ?: '—' }}</td>
          <td class="tnum">{{ $a->paginas ?? '—' }}</td>
          <td dir="auto">{{ $a->trefwoorden ?? '—' }}</td>
          <td class="row-act">
            @if ($magBeheer)
              <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="#bewerk-{{ $a->id }}">Bewerken</a>
            @endif
          </td>
        </tr>

        @if ($magBeheer)
          {{-- Het bewerkformulier staat direct onder het artikel: openklappen,
               aanpassen, opslaan. Zo hoeft u de pagina niet te verlaten. --}}
          <tr>
            <td colspan="5" style="padding:0;">
              <details id="bewerk-{{ $a->id }}" class="sis-card" style="margin:0 0 6px;">
                <summary style="cursor:pointer;" class="sis-muted">Artikel bewerken of verwijderen</summary>

                <form method="POST" action="{{ route('bibliotheek.artikelen.update', $a) }}" class="sis-form" style="margin-top:10px;">
                  @csrf @method('PUT')

                  <div class="sis-fld"><label>Artikeltitel <span class="req">*</span></label><input type="text" name="titel" value="{{ $a->titel }}" maxlength="255" required dir="auto"></div>

                  <div class="sis-fld">
                    <label>Auteur(s)</label>
                    <div id="auteurs-{{ $a->id }}">
                      @forelse ($a->auteurs as $auteur)
                        <input type="text" name="auteurs[]" value="{{ $auteur->naam }}" maxlength="255" dir="auto" style="margin-bottom:6px;">
                      @empty
                        <input type="text" name="auteurs[]" value="" maxlength="255" dir="auto" placeholder="Naam van de auteur" style="margin-bottom:6px;">
                      @endforelse
                    </div>
                    <button type="button" class="iuasr-dash-btn iuasr-dash-btn--sm auteur-erbij" data-doel="auteurs-{{ $a->id }}">Auteur toevoegen</button>
                  </div>

                  <div class="sis-fld-row sis-fld-row--2">
                    <div class="sis-fld"><label>Pagina's</label><input type="text" name="paginas" value="{{ $a->paginas }}" maxlength="30" placeholder="bijv. 12-27"></div>
                    <div class="sis-fld"><label>Trefwoorden</label><input type="text" name="trefwoorden" value="{{ $a->trefwoorden }}" maxlength="255" dir="auto"></div>
                  </div>

                  <div class="sis-fld"><label>Beschrijving</label><textarea name="beschrijving" maxlength="2000" dir="auto">{{ $a->beschrijving }}</textarea>
                    <small class="sis-muted">Bij geïmporteerde artikelen staat hier de oorspronkelijke regel uit het bronbestand. Laat die staan; dan blijft het artikel vindbaar op elk woord uit de bron.</small>
                  </div>

                  <div class="sis-form__actions">
                    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div>
                  </div>
                </form>

                <form method="POST" action="{{ route('bibliotheek.artikelen.destroy', $a) }}" onsubmit="return confirm('Dit artikel definitief verwijderen?');" style="margin-top:6px;">
                  @csrf @method('DELETE')
                  <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">Artikel verwijderen</button>
                </form>
              </details>
            </td>
          </tr>
        @endif
      @empty
        <tr><td colspan="5"><div class="iuasr-dash-empty" style="border:0;"><h3>Nog geen artikelen</h3><p class="sis-muted">Voeg hieronder het eerste artikel toe.</p></div></td></tr>
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
  // Werkt voor het toevoegformulier én voor elk bewerkformulier op deze pagina:
  // elke knop weet via data-doel in welke lijst hij een veld moet bijzetten.
  var erbij = function (doelId) {
    var invoer = document.createElement('input');
    invoer.type = 'text';
    invoer.name = 'auteurs[]';
    invoer.placeholder = 'Naam van de auteur';
    invoer.setAttribute('dir', 'auto');
    invoer.style.marginBottom = '6px';
    document.getElementById(doelId).appendChild(invoer);
    invoer.focus();
  };

  var knop = document.getElementById('art-auteur-erbij');
  if (knop) {
    knop.addEventListener('click', function () { erbij('art-auteurs'); });
  }

  document.querySelectorAll('.auteur-erbij').forEach(function (k) {
    k.addEventListener('click', function () { erbij(k.getAttribute('data-doel')); });
  });

  // Klikt u op "Bewerken" in de tabel, dan klapt het formulier eronder open.
  window.addEventListener('hashchange', function () {
    var doel = document.querySelector(window.location.hash);
    if (doel && doel.tagName === 'DETAILS') { doel.open = true; }
  });
})();
</script>
@endpush
