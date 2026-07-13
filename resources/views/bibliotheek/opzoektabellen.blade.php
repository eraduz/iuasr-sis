@extends('layouts.app')

@section('titel', 'Soorten, talen, vakgebieden en kasten')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('bibliotheek.dashboard') }}">Bibliotheek</a><span class="sep">›</span><b>Soorten & tabellen</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Soorten, talen, vakgebieden en kasten</h1>
    <div class="summary">De keuzelijsten van de bibliotheek. Hier voegt u zelf waarden toe — een cd, een dvd, een nieuwe taal, een nieuw vakgebied of een nieuwe kast.</div>
  </div>
</div>

<div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-bottom:16px;">
  <span>
    <b>Verwijderen kan alleen als er niets aan hangt.</b> Is een waarde in gebruik, zet hem dan op <b>inactief</b>:
    hij verdwijnt uit de keuzelijsten, maar de bestaande titels blijven kloppen.
  </span>
</div>

{{-- ---------------------------------------------------------------- Soorten --}}
<h2 style="margin:22px 0 10px;">Publicatiesoorten</h2>
<p class="sis-muted" style="margin:0 0 10px;">
  De twee vinkjes bepalen hoe het systeem zich gedraagt. <b>Fysieke exemplaren</b>: er zijn boeken/schijfjes die uitgeleend worden (boek, cd, dvd) — een digitaal document niet.
  <b>Uitgaven met artikelen</b>: het verschijnt in afleveringen met artikelen (tijdschrift).
</p>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Code</th><th>Naam</th><th style="text-align:center;">Fysieke exemplaren</th><th style="text-align:center;">Uitgaven met artikelen</th><th style="text-align:center;">Actief</th><th style="text-align:right;">Titels</th><th class="row-act"></th></tr></thead>
    <tbody>
      @foreach ($soorten as $s)
        <tr>
          <form method="POST" action="{{ route('bibliotheek.opzoektabellen.soort.update', $s) }}" id="soort-{{ $s->id }}">@csrf @method('PUT')</form>
          <td class="tnum">{{ $s->code }}</td>
          <td><input form="soort-{{ $s->id }}" type="text" name="naam" value="{{ $s->naam }}" maxlength="255" required></td>
          <td style="text-align:center;"><input form="soort-{{ $s->id }}" type="checkbox" name="heeft_exemplaren" value="1" @checked($s->heeft_exemplaren)></td>
          <td style="text-align:center;"><input form="soort-{{ $s->id }}" type="checkbox" name="heeft_uitgaven" value="1" @checked($s->heeft_uitgaven)></td>
          <td style="text-align:center;"><input form="soort-{{ $s->id }}" type="checkbox" name="actief" value="1" @checked($s->actief)></td>
          <td class="tnum" style="text-align:right;">{{ number_format($s->publicaties_count, 0, ',', '.') }}</td>
          <td class="row-act" style="white-space:nowrap;">
            <button form="soort-{{ $s->id }}" class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Opslaan</button>
            @if ($s->publicaties_count === 0)
              <form method="POST" action="{{ route('bibliotheek.opzoektabellen.soort.destroy', $s) }}" style="display:inline;">
                @csrf @method('DELETE')
                <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">Verwijderen</button>
              </form>
            @endif
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

<form method="POST" action="{{ route('bibliotheek.opzoektabellen.soort.store') }}" class="sis-card sis-form" style="margin-top:12px; max-width:820px;">
  @csrf
  <h3>Soort toevoegen</h3>
  <div class="sis-fld-row sis-fld-row--3">
    <div class="sis-fld"><label>Code <span class="req">*</span></label><input type="text" name="code" maxlength="20" placeholder="bijv. cd, dvd, kaart" required><small class="sis-muted">Kleine letters, geen spaties.</small></div>
    <div class="sis-fld"><label>Naam <span class="req">*</span></label><input type="text" name="naam" maxlength="255" placeholder="bijv. Cd-rom" required></div>
    <div class="sis-fld"><label>Volgorde</label><input type="number" name="volgorde" min="0" max="999" placeholder="99"></div>
  </div>
  <div class="sis-fld">
    <label class="sis-check-inline"><input type="checkbox" name="heeft_exemplaren" value="1" checked> Heeft fysieke exemplaren (kan worden uitgeleend)</label><br>
    <label class="sis-check-inline"><input type="checkbox" name="heeft_uitgaven" value="1"> Verschijnt in uitgaven met artikelen (zoals een tijdschrift)</label>
  </div>
  <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Soort toevoegen</button></div></div>
</form>

{{-- ----------------------------------------------------------------- Talen --}}
<h2 style="margin:26px 0 10px;">Talen</h2>
<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th style="width:100px;">Code</th><th>Naam</th><th class="row-act"></th></tr></thead>
    <tbody>
      @foreach ($talen as $t)
        <tr>
          <td class="tnum">{{ $t->code }}</td>
          <td>{{ $t->naam }}</td>
          <td class="row-act">
            <form method="POST" action="{{ route('bibliotheek.opzoektabellen.taal.destroy', $t) }}" style="display:inline;">
              @csrf @method('DELETE')
              <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">Verwijderen</button>
            </form>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

<form method="POST" action="{{ route('bibliotheek.opzoektabellen.taal.store') }}" class="sis-card sis-form" style="margin-top:12px; max-width:620px;">
  @csrf
  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Taalcode <span class="req">*</span></label><input type="text" name="code" maxlength="5" placeholder="bijv. fa" required></div>
    <div class="sis-fld"><label>Naam <span class="req">*</span></label><input type="text" name="naam" maxlength="255" placeholder="bijv. Perzisch" required></div>
  </div>
  <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Taal toevoegen</button></div></div>
</form>

{{-- ----------------------------------------------------------- Vakgebieden --}}
<h2 style="margin:26px 0 10px;">Vakgebieden</h2>
<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th style="width:90px;">Rekletter</th><th>Naam</th><th>Omschrijving</th><th style="text-align:right;">Titels</th><th class="row-act"></th></tr></thead>
    <tbody>
      @foreach ($vakgebieden as $v)
        <tr>
          <td class="tnum">{{ $v->rekletter ?? '—' }}</td>
          <td class="nm">{{ $v->naam }}</td>
          <td dir="auto">{{ $v->omschrijving ?? '—' }}</td>
          <td class="tnum" style="text-align:right;">{{ number_format($v->publicaties_count, 0, ',', '.') }}</td>
          <td class="row-act">
            @if ($v->publicaties_count === 0)
              <form method="POST" action="{{ route('bibliotheek.opzoektabellen.vakgebied.destroy', $v) }}" style="display:inline;">
                @csrf @method('DELETE')
                <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">Verwijderen</button>
              </form>
            @endif
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

<form method="POST" action="{{ route('bibliotheek.opzoektabellen.vakgebied.store') }}" class="sis-card sis-form" style="margin-top:12px; max-width:820px;">
  @csrf
  <div class="sis-fld-row sis-fld-row--3">
    <div class="sis-fld"><label>Naam <span class="req">*</span></label><input type="text" name="naam" maxlength="255" required></div>
    <div class="sis-fld"><label>Rekletter</label><input type="text" name="rekletter" maxlength="1" placeholder="bijv. V"><small class="sis-muted">De letter waarmee de rekcode begint.</small></div>
    <div class="sis-fld"><label>Volgorde</label><input type="number" name="volgorde" min="0" max="999"></div>
  </div>
  <div class="sis-fld"><label>Omschrijving</label><input type="text" name="omschrijving" maxlength="255" dir="auto"></div>
  <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Vakgebied toevoegen</button></div></div>
</form>

{{-- ---------------------------------------------------------------- Kasten --}}
<h2 style="margin:26px 0 10px;">Kasten</h2>
<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th style="width:90px;">Code</th><th>Omschrijving</th><th style="text-align:right;">Exemplaren</th><th class="row-act"></th></tr></thead>
    <tbody>
      @foreach ($kasten as $k)
        <tr>
          <td class="tnum">{{ $k->code }}</td>
          <td>{{ $k->omschrijving ?? '—' }}</td>
          <td class="tnum" style="text-align:right;">{{ number_format($k->exemplaren_count, 0, ',', '.') }}</td>
          <td class="row-act">
            @if ($k->exemplaren_count === 0)
              <form method="POST" action="{{ route('bibliotheek.opzoektabellen.kast.destroy', $k) }}" style="display:inline;">
                @csrf @method('DELETE')
                <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">Verwijderen</button>
              </form>
            @endif
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

<form method="POST" action="{{ route('bibliotheek.opzoektabellen.kast.store') }}" class="sis-card sis-form" style="margin-top:12px; max-width:620px;">
  @csrf
  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Code <span class="req">*</span></label><input type="text" name="code" maxlength="20" placeholder="bijv. V" required></div>
    <div class="sis-fld"><label>Omschrijving</label><input type="text" name="omschrijving" maxlength="255" placeholder="bijv. Media (cd/dvd)"></div>
  </div>
  <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Kast toevoegen</button></div></div>
</form>
@endsection
