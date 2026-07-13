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

@if ($publicatie->soort->heeftExemplaren())
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

@if ($publicatie->soort->heeftUitgaven())
  <h2 style="margin:22px 0 10px;">Uitgaven</h2>
  <div class="iuasr-dash-tbl-card">
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Uitgavenummer</th><th>Datum</th><th>Jaar</th><th>Locatie</th><th style="text-align:right;">Artikelen</th><th class="row-act"></th></tr></thead>
      <tbody>
        @forelse ($publicatie->uitgaven as $uitgave)
          <tr>
            <td class="nm"><a href="{{ route('bibliotheek.uitgaven.show', $uitgave) }}">{{ $uitgave->uitgavenummer }}</a></td>
            <td class="tnum">{{ $uitgave->publicatiedatum?->format('d-m-Y') ?? '—' }}</td>
            <td class="tnum">{{ $uitgave->jaar ?? '—' }}</td>
            <td>{{ $uitgave->locatie ?? '—' }}</td>
            <td class="tnum" style="text-align:right;">{{ $uitgave->artikelen->count() }}</td>
            <td class="row-act"><a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('bibliotheek.uitgaven.show', $uitgave) }}">Artikelen</a></td>
          </tr>
        @empty
          <tr><td colspan="6"><div class="iuasr-dash-empty" style="border:0;"><h3>Nog geen uitgaven</h3><p class="sis-muted">Voeg de eerste aflevering toe.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if ($magBeheer)
    <form method="POST" action="{{ route('bibliotheek.uitgaven.store', $publicatie) }}" class="sis-card sis-form" style="margin-top:12px; max-width:760px;">
      @csrf
      <div class="sis-fld-row sis-fld-row--3">
        <div class="sis-fld"><label>Uitgavenummer <span class="req">*</span></label><input type="text" name="uitgavenummer" maxlength="40" placeholder="bijv. 2026/1" required></div>
        <div class="sis-fld"><label>Publicatiedatum</label><input type="date" name="publicatiedatum"></div>
        <div class="sis-fld"><label>Jaar</label><input type="number" name="jaar" min="1000" max="{{ date('Y') + 1 }}"></div>
      </div>
      <div class="sis-fld"><label>Locatie</label><input type="text" name="locatie" maxlength="255" placeholder="Waar staat deze uitgave?"></div>
      <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Uitgave toevoegen</button></div></div>
    </form>
  @endif
@endif
@endsection
