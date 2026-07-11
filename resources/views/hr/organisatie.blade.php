@extends('layouts.app')

@section('titel', 'Organisatiestructuur')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('hr.dashboard') }}">HR</a><span class="sep">›</span><b>Organisatie</b></div>

<div class="iuasr-dash-vhead"><div><h1>Organisatiestructuur</h1><div class="summary">Afdelingen, teams, leidinggevenden en functies</div></div></div>

@if (session('status'))
  <div class="iuasr-dash-flash iuasr-dash-flash--ok" style="margin-bottom:14px;">{{ session('status') }}</div>
@endif
@if ($errors->any())
  <div class="iuasr-dash-flash iuasr-dash-flash--alert" style="margin-bottom:14px;">{{ $errors->first() }}</div>
@endif

{{-- Afdelingenboom (overzicht, voor iedereen met inzage) --}}
<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Afdeling / team</th><th>Manager</th><th style="text-align:right;">Medewerkers</th></tr></thead>
    <tbody>
      @forelse ($wortels as $wortel)
        @include('hr.partials.afdelingrij', ['afdeling' => $wortel, 'diepte' => 0, 'perOuder' => $perOuder])
      @empty
        <tr><td colspan="3"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen afdelingen</h3><p class="sis-muted">Voeg hieronder een eerste afdeling toe.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

@unless ($magBeheer)
  <p class="sis-tblnote">Dit overzicht is alleen-lezen. Afdelingen en functies worden beheerd door HR.</p>
@endunless

@if ($magBeheer)
  {{-- =============================== AFDELINGEN BEHEREN =============================== --}}
  <h2 style="font-family:'DM Serif Display',serif;font-size:20px;margin:28px 0 4px;color:var(--priColor100,#1E1446);">Afdelingen &amp; teams beheren</h2>
  <p class="sis-muted" style="margin:0 0 12px;">Voeg afdelingen toe of wijzig code, naam, bovenliggende afdeling (= team), leidinggevende en of de afdeling actief is. Verwijderen kan alleen als er geen medewerkers of onderliggende teams meer aan hangen.</p>

  {{-- Nieuwe afdeling --}}
  <form method="POST" action="{{ route('hr.afdeling.store') }}" class="sis-card" style="margin-bottom:14px;padding:14px 16px;">
    @csrf
    <div class="sis-formgrid" style="display:grid;grid-template-columns:1fr 1.5fr 1.5fr 1.5fr auto auto;gap:10px;align-items:end;">
      <label>Code<input class="iuasr-dash-input" name="code" maxlength="40" required placeholder="bv. FIN"></label>
      <label>Naam<input class="iuasr-dash-input" name="naam" maxlength="255" required placeholder="bv. Financiën"></label>
      <label>Bovenliggend (team)
        <select class="iuasr-dash-input" name="bovenliggende_afdeling_id">
          <option value="">— geen —</option>
          @foreach ($afdelingen as $opt)<option value="{{ $opt->id }}">{{ $opt->naam }}</option>@endforeach
        </select>
      </label>
      <label>Manager
        <select class="iuasr-dash-input" name="manager_id">
          <option value="">— geen —</option>
          @foreach ($medewerkers as $m)<option value="{{ $m->id }}">{{ $m->volledigeNaam() }}</option>@endforeach
        </select>
      </label>
      <label style="text-align:center;">Actief<br><input type="checkbox" name="actief" value="1" checked></label>
      <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Toevoegen</button>
    </div>
  </form>

  {{-- Verborgen edit-/delete-formulieren (HTML5 form-attribuut, zodat de rijen geldig blijven) --}}
  @foreach ($afdelingen as $a)
    <form id="afd-{{ $a->id }}" method="POST" action="{{ route('hr.afdeling.update', $a) }}">@csrf @method('PUT')</form>
    <form id="afd-del-{{ $a->id }}" method="POST" action="{{ route('hr.afdeling.destroy', $a) }}" onsubmit="return confirm('Afdeling &quot;{{ $a->naam }}&quot; verwijderen?');">@csrf @method('DELETE')</form>
  @endforeach

  <div class="iuasr-dash-tbl-card">
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Code</th><th>Naam</th><th>Bovenliggend</th><th>Manager</th><th style="text-align:center;">Actief</th><th class="row-act"></th></tr></thead>
      <tbody>
        @foreach ($afdelingen as $a)
          <tr>
            <td><input class="iuasr-dash-input" form="afd-{{ $a->id }}" name="code" value="{{ $a->code }}" maxlength="40" required style="width:90px;"></td>
            <td><input class="iuasr-dash-input" form="afd-{{ $a->id }}" name="naam" value="{{ $a->naam }}" maxlength="255" required></td>
            <td>
              <select class="iuasr-dash-input" form="afd-{{ $a->id }}" name="bovenliggende_afdeling_id">
                <option value="">— geen —</option>
                @foreach ($afdelingen as $opt)
                  @if ($opt->id !== $a->id)
                    <option value="{{ $opt->id }}" @selected($a->bovenliggende_afdeling_id === $opt->id)>{{ $opt->naam }}</option>
                  @endif
                @endforeach
              </select>
            </td>
            <td>
              <select class="iuasr-dash-input" form="afd-{{ $a->id }}" name="manager_id">
                <option value="">— geen —</option>
                @foreach ($medewerkers as $m)<option value="{{ $m->id }}" @selected($a->manager_id === $m->id)>{{ $m->volledigeNaam() }}</option>@endforeach
              </select>
            </td>
            <td style="text-align:center;"><input type="checkbox" form="afd-{{ $a->id }}" name="actief" value="1" @checked($a->actief)></td>
            <td class="row-act" style="white-space:nowrap;">
              <button class="iuasr-dash-btn iuasr-dash-btn--sm" form="afd-{{ $a->id }}" type="submit">Bewaren</button>
              <button class="iuasr-dash-btn iuasr-dash-btn--sm" form="afd-del-{{ $a->id }}" type="submit">Verwijderen</button>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{-- =============================== FUNCTIES BEHEREN =============================== --}}
  <h2 style="font-family:'DM Serif Display',serif;font-size:20px;margin:28px 0 4px;color:var(--priColor100,#1E1446);">Functies beheren</h2>
  <p class="sis-muted" style="margin:0 0 12px;">Voeg functies toe of wijzig code, naam en categorie. Verwijderen kan alleen als de functie niet meer aan medewerkers is gekoppeld.</p>

  {{-- Nieuwe functie --}}
  <form method="POST" action="{{ route('hr.functie.store') }}" class="sis-card" style="margin-bottom:14px;padding:14px 16px;">
    @csrf
    <div style="display:grid;grid-template-columns:1fr 2fr 1.5fr auto auto;gap:10px;align-items:end;">
      <label>Code<input class="iuasr-dash-input" name="code" maxlength="40" required placeholder="bv. COORD"></label>
      <label>Naam<input class="iuasr-dash-input" name="naam" maxlength="255" required placeholder="bv. Coördinator"></label>
      <label>Categorie
        <select class="iuasr-dash-input" name="categorie" required>
          @foreach ($categorieen as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
        </select>
      </label>
      <label style="text-align:center;">Actief<br><input type="checkbox" name="actief" value="1" checked></label>
      <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Toevoegen</button>
    </div>
  </form>

  @foreach ($functies as $f)
    <form id="fn-{{ $f->id }}" method="POST" action="{{ route('hr.functie.update', $f) }}">@csrf @method('PUT')</form>
    <form id="fn-del-{{ $f->id }}" method="POST" action="{{ route('hr.functie.destroy', $f) }}" onsubmit="return confirm('Functie &quot;{{ $f->naam }}&quot; verwijderen?');">@csrf @method('DELETE')</form>
  @endforeach

  <div class="iuasr-dash-tbl-card">
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Code</th><th>Naam</th><th>Categorie</th><th style="text-align:center;">Actief</th><th class="row-act"></th></tr></thead>
      <tbody>
        @foreach ($functies as $f)
          <tr>
            <td><input class="iuasr-dash-input" form="fn-{{ $f->id }}" name="code" value="{{ $f->code }}" maxlength="40" required style="width:90px;"></td>
            <td><input class="iuasr-dash-input" form="fn-{{ $f->id }}" name="naam" value="{{ $f->naam }}" maxlength="255" required></td>
            <td>
              <select class="iuasr-dash-input" form="fn-{{ $f->id }}" name="categorie" required>
                @foreach ($categorieen as $k => $v)<option value="{{ $k }}" @selected($f->categorie === $k)>{{ $v }}</option>@endforeach
              </select>
            </td>
            <td style="text-align:center;"><input type="checkbox" form="fn-{{ $f->id }}" name="actief" value="1" @checked($f->actief)></td>
            <td class="row-act" style="white-space:nowrap;">
              <button class="iuasr-dash-btn iuasr-dash-btn--sm" form="fn-{{ $f->id }}" type="submit">Bewaren</button>
              <button class="iuasr-dash-btn iuasr-dash-btn--sm" form="fn-del-{{ $f->id }}" type="submit">Verwijderen</button>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <p class="sis-tblnote">Wijzigingen worden gelogd. Verlof-, contract- en gesprekstypen zijn vaste systeemwaarden en worden niet hier beheerd.</p>
@endif
@endsection
