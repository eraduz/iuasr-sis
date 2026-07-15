@extends('layouts.app')

@section('titel', $publicatie->volledigeTitel())

@section('inhoud')
@php $magBeheer = auth()->user()->magBibliotheekBeheren(); @endphp

<div class="sis-crumb"><a href="{{ route('bibliotheek.dashboard') }}">Bibliotheek</a><span class="sep">›</span><a href="{{ route('bibliotheek.publicaties') }}">Catalogus</a><span class="sep">›</span><b>{{ $publicatie->titel }}</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1 dir="auto">{{ $publicatie->volledigeTitel() }}</h1>
    <div class="summary">{{ $publicatie->soort->label() }} · {{ $publicatie->auteursTekst() }}</div>
  </div>
  @if ($magBeheer)
    <div class="iuasr-dash-vhead__actions">
      <a class="iuasr-dash-btn" href="{{ route('bibliotheek.publicaties.edit', $publicatie) }}">Bewerken</a>
    </div>
  @endif
</div>

<div class="sis-card" style="margin-bottom:16px;">
  <table class="iuasr-dash-tbl">
    <tbody>
      <tr><td class="sis-muted" style="width:200px;">Rek / plaats</td><td class="tnum"><b>{{ $publicatie->rekplaats() ?? '—' }}</b></td></tr>
      <tr><td class="sis-muted">ISBN</td><td>{{ $publicatie->isbn ?? '—' }}</td></tr>
      <tr><td class="sis-muted">Talen</td><td>{{ $publicatie->talenTekst() }}</td></tr>
      <tr><td class="sis-muted">Uitgavejaar</td><td>{{ $publicatie->uitgavejaar ?? '—' }}</td></tr>
      <tr><td class="sis-muted">Druknummer</td><td>{{ $publicatie->druknummer ?? '—' }}</td></tr>
      <tr><td class="sis-muted">Vakgebied</td><td>{{ $publicatie->vakgebied?->naam ?? '—' }}</td></tr>
      @if ($publicatie->reeks)
        <tr><td class="sis-muted">Boekreeks</td><td><a href="{{ route('bibliotheek.reeksen.show', $publicatie->reeks) }}">{{ $publicatie->reeks->titel }}</a> — deel {{ $publicatie->deelnummer }}</td></tr>
      @endif
      <tr><td class="sis-muted">Opmerking</td><td dir="auto">{{ $publicatie->opmerking ?? '—' }}</td></tr>
    </tbody>
  </table>
</div>

@if ($publicatie->heeftExemplaren())
  <h2 style="margin:22px 0 10px;">Exemplaren</h2>
  <div class="iuasr-dash-tbl-card">
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Serienummer</th><th>Kast</th><th>Status</th><th>Uitgeleend aan</th><th class="row-act"></th></tr></thead>
      <tbody>
        @forelse ($publicatie->exemplaren as $ex)
          @php $lopend = $ex->uitleningen->firstWhere('retour_op', null); @endphp
          <tr>
            <td class="tnum">{{ $ex->serienummer }}</td>
            <td>{{ $ex->kast?->code ?? '—' }}</td>
            <td><span class="iuasr-dash-status {{ $ex->status->badge() }}">{{ $ex->status->label() }}</span></td>
            <td>{{ $lopend?->lenerNaam() ?? '—' }}@if ($lopend)<br><small class="sis-muted">terug op {{ $lopend->verwachte_retour_op->format('d-m-Y') }}</small>@endif</td>
            <td class="row-act" style="white-space:nowrap;">
              @if ($magBeheer)
                @if ($lopend)
                  <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('bibliotheek.innemen', $lopend) }}">Innemen</a>
                @elseif ($ex->isUitleenbaar())
                  <a class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" href="{{ route('bibliotheek.uitlenen', ['exemplaar' => $ex->id]) }}">Uitlenen</a>
                @endif
                <form method="POST" action="{{ route('bibliotheek.exemplaren.status', $ex) }}" style="display:inline;">
                  @csrf @method('PUT')
                  <select name="status" onchange="this.form.submit()" style="width:auto;">
                    @foreach (['beschikbaar' => 'Beschikbaar', 'gereserveerd' => 'Gereserveerd', 'verloren' => 'Verloren', 'beschadigd' => 'Beschadigd'] as $waarde => $label)
                      <option value="{{ $waarde }}" @selected($ex->status->value === $waarde)>{{ $label }}</option>
                    @endforeach
                  </select>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="5"><div class="iuasr-dash-empty" style="border:0;"><h3>Nog geen exemplaren</h3><p class="sis-muted">Voeg hieronder het eerste fysieke exemplaar toe.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if ($magBeheer)
    <form method="POST" action="{{ route('bibliotheek.exemplaren.store', $publicatie) }}" class="sis-card sis-form" style="margin-top:12px; max-width:640px;">
      @csrf
      <div class="sis-fld-row sis-fld-row--2">
        <div class="sis-fld"><label>Serienummer <span class="req">*</span></label><input type="text" name="serienummer" maxlength="40" required></div>
        <div class="sis-fld">
          <label>Kast</label>
          <select name="kast_id">
            <option value="">— geen —</option>
            @foreach ($kasten as $k)
              <option value="{{ $k->id }}">{{ $k->code }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Exemplaar toevoegen</button></div></div>
    </form>
  @endif
@endif

@if ($publicatie->heeftUitgaven())
  @php $totaalArtikelen = $publicatie->uitgaven->sum(fn ($u) => $u->artikelen->count()); @endphp

  <h2 style="margin:22px 0 10px;">
    Uitgaven en artikelen
    <span class="sis-muted" style="font-size:14px; font-weight:400;">
      — {{ $publicatie->uitgaven->count() }} {{ $publicatie->uitgaven->count() === 1 ? 'uitgave' : 'uitgaven' }},
      {{ number_format($totaalArtikelen, 0, ',', '.') }} {{ $totaalArtikelen === 1 ? 'artikel' : 'artikelen' }}
    </span>
  </h2>

  <p class="sis-muted" style="margin:0 0 10px;">
    Klap een uitgave open om de artikelen te zien. Zoekt u één bepaald artikel, gebruik dan
    <a href="{{ route('bibliotheek.artikelen', ['tijdschrift' => $publicatie->id]) }}">Artikelen zoeken</a> —
    daar doorzoekt u alle artikelen van dit tijdschrift in één keer.
  </p>

  @forelse ($publicatie->uitgaven as $uitgave)
    <details class="sis-card" style="margin-bottom:8px;" @if ($loop->first) open @endif>
      <summary style="cursor:pointer; font-weight:600;">
        {{ $uitgave->uitgavenummer }}
        @if ($uitgave->jaar) <span class="sis-muted" style="font-weight:400;">({{ $uitgave->jaar }})</span>@endif
        <span class="iuasr-dash-status s-approved" style="margin-left:8px;">{{ $uitgave->artikelen->count() }} {{ $uitgave->artikelen->count() === 1 ? 'artikel' : 'artikelen' }}</span>
        @if ($uitgave->locatie) <small class="sis-muted">· {{ $uitgave->locatie }}</small>@endif
        <a class="iuasr-dash-btn iuasr-dash-btn--sm" style="float:right;" href="{{ route('bibliotheek.uitgaven.show', $uitgave) }}">
          {{ $magBeheer ? 'Artikelen beheren' : 'Openen' }}
        </a>
      </summary>

      <table class="iuasr-dash-tbl" style="margin-top:10px;">
        <thead><tr><th>Artikel</th><th>Auteur(s)</th><th style="width:110px;">Pagina's</th></tr></thead>
        <tbody>
          @forelse ($uitgave->artikelen as $artikel)
            <tr>
              <td class="nm" dir="auto">{{ $artikel->titel }}
                @if ($artikel->trefwoorden)<br><small class="sis-muted" dir="auto">{{ $artikel->trefwoorden }}</small>@endif
              </td>
              <td dir="auto">{{ $artikel->auteurs->pluck('naam')->implode(', ') ?: '—' }}</td>
              <td class="tnum">{{ $artikel->paginas ?? '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="3"><p class="sis-muted">Nog geen artikelen bij deze uitgave.</p></td></tr>
          @endforelse
        </tbody>
      </table>
    </details>
  @empty
    <div class="iuasr-dash-empty"><h3>Nog geen uitgaven</h3><p class="sis-muted">Voeg hieronder de eerste aflevering toe.</p></div>
  @endforelse

  @if ($magBeheer)
    {{-- Artikel toevoegen, direct vanaf de tijdschriftpagina. U kiest een
         bestaande uitgave of vult een nieuw uitgavenummer in — dan wordt die
         uitgave meteen aangemaakt. Zo hoeft u niet eerst door te klikken. --}}
    <form method="POST" action="{{ route('bibliotheek.tijdschrift.artikel', $publicatie) }}" class="sis-card sis-form" style="margin-top:16px; max-width:820px;">
      @csrf
      <h3>Artikel toevoegen</h3>

      <div class="sis-fld">
        <label>In welke uitgave? <span class="req">*</span></label>
        <select name="uitgave_id" id="artikel-uitgave">
          @foreach ($publicatie->uitgaven as $uitgave)
            <option value="{{ $uitgave->id }}">{{ $uitgave->uitgavenummer }}@if ($uitgave->jaar) ({{ $uitgave->jaar }})@endif</option>
          @endforeach
          <option value="">— nieuwe uitgave —</option>
        </select>
      </div>

      <div class="sis-fld-row sis-fld-row--2" data-veld="nieuwe-uitgave" style="{{ $publicatie->uitgaven->isEmpty() ? '' : 'display:none;' }}">
        <div class="sis-fld"><label>Nieuw uitgavenummer</label><input type="text" name="nieuw_uitgavenummer" maxlength="40" placeholder="bijv. 2026/1"></div>
        <div class="sis-fld"><label>Jaar</label><input type="number" name="nieuw_jaar" min="1000" max="{{ date('Y') + 1 }}"></div>
      </div>

      <div class="sis-fld"><label>Artikeltitel <span class="req">*</span></label><input type="text" name="titel" value="{{ old('titel') }}" maxlength="255" required dir="auto"></div>

      <div class="sis-fld">
        <label>Auteur(s)</label>
        <div id="snel-auteurs">
          <input type="text" name="auteurs[]" maxlength="255" dir="auto" placeholder="Naam van de auteur" style="margin-bottom:6px;">
        </div>
        <button type="button" class="iuasr-dash-btn iuasr-dash-btn--sm" id="snel-auteur-erbij">Auteur toevoegen</button>
      </div>

      <div class="sis-fld-row sis-fld-row--2">
        <div class="sis-fld"><label>Pagina's</label><input type="text" name="paginas" maxlength="30" placeholder="bijv. 12-27"></div>
        <div class="sis-fld"><label>Trefwoorden</label><input type="text" name="trefwoorden" maxlength="255" dir="auto"></div>
      </div>

      <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Artikel toevoegen</button></div></div>
    </form>

    <details class="sis-card" style="margin-top:12px; max-width:760px;">
      <summary style="cursor:pointer;" class="sis-muted">Alleen een lege uitgave toevoegen (zonder artikel)</summary>
      <form method="POST" action="{{ route('bibliotheek.uitgaven.store', $publicatie) }}" class="sis-form" style="margin-top:10px;">
        @csrf
        <div class="sis-fld-row sis-fld-row--3">
          <div class="sis-fld"><label>Uitgavenummer <span class="req">*</span></label><input type="text" name="uitgavenummer" maxlength="40" placeholder="bijv. 2026/1" required></div>
          <div class="sis-fld"><label>Publicatiedatum</label><input type="date" name="publicatiedatum"></div>
          <div class="sis-fld"><label>Jaar</label><input type="number" name="jaar" min="1000" max="{{ date('Y') + 1 }}"></div>
        </div>
        <div class="sis-fld"><label>Locatie</label><input type="text" name="locatie" maxlength="255" placeholder="Waar staat deze uitgave?"></div>
        <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn" type="submit">Uitgave toevoegen</button></div></div>
      </form>
    </details>
  @endif
@endif
@endsection

@push('scripts')
<script>
(function () {
  // Kiest de medewerker "nieuwe uitgave", toon dan de velden voor het nummer.
  var keuze = document.getElementById('artikel-uitgave');
  if (keuze) {
    var velden = document.querySelector('[data-veld="nieuwe-uitgave"]');
    var toon = function () { if (velden) velden.style.display = keuze.value === '' ? '' : 'none'; };
    keuze.addEventListener('change', toon);
    toon();
  }

  var knop = document.getElementById('snel-auteur-erbij');
  if (knop) {
    knop.addEventListener('click', function () {
      var invoer = document.createElement('input');
      invoer.type = 'text'; invoer.name = 'auteurs[]';
      invoer.placeholder = 'Naam van de auteur';
      invoer.setAttribute('dir', 'auto'); invoer.style.marginBottom = '6px';
      document.getElementById('snel-auteurs').appendChild(invoer);
      invoer.focus();
    });
  }
})();
</script>
@endpush
