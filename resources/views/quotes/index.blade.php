@extends('layouts.app')

@section('titel', 'Quotes')

@php use App\Enums\Quotesoort; @endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Quotes</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Quotes in de zijbalk</h1>
    <div class="summary"><b>{{ $aantalActief }}</b> actief · wisselt elke <b>{{ $intervalMinuten }}</b> minuten · iedereen ziet op hetzelfde moment dezelfde quote</div>
  </div>
</div>

<div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-bottom:18px;">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
  <div>
    <b>Afbeeldingen aanleveren:</b> vierkant, <b>456 × 456 pixels</b>, PNG met een <b>doorzichtige achtergrond</b>.
    In de zijbalk wordt hij op 152 pixels getoond — dat is precies 4 cm; drie keer zo groot aanleveren houdt hem scherp op een goed beeldscherm.
    Maak het lijnwerk <b>goud of wit</b>: de tegel heeft in zowel de lichte als de donkere modus dezelfde donkere achtergrond, dus één versie volstaat.
    Een quote zonder afbeelding toont gewoon de Arabische tekst — u kunt dus alvast beginnen en de afbeeldingen later toevoegen.
  </div>
</div>

@if ($huidige)
  <div class="sis-card" style="margin-bottom:18px;">
    <div class="sis-card__hd"><h3>Nu in beeld</h3><span class="hint">Dit ziet iedereen op dit moment in de zijbalk</span></div>
    <div style="display:flex;gap:16px;align-items:center;">
      @if ($huidige->heeftAfbeelding())
        <img src="{{ route('quotes.afbeelding', $huidige) }}" alt="" width="76" height="76"
             style="width:76px;height:76px;border-radius:10px;background:var(--priColor100);padding:4px;object-fit:contain;">
      @elseif ($huidige->arabisch)
        <div style="font-size:26px;color:var(--goud,#D69A2D);" dir="rtl" lang="ar">{{ $huidige->arabisch }}</div>
      @endif
      <div>
        @if ($huidige->kop())<b>{{ $huidige->kop() }}</b><br>@endif
        <span class="sis-muted" style="font-size:13px;">{{ $huidige->betekenis }}</span>
      </div>
    </div>
  </div>
@endif

<div class="sis-card" style="margin-bottom:18px;">
  <div class="sis-card__hd"><h3>Quote toevoegen</h3><span class="hint">Een eigen spreuk of een extra Schone Naam</span></div>
  <form method="POST" action="{{ route('quotes.store') }}" class="sis-form" enctype="multipart/form-data">
    @csrf
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 16px;">
      <div class="sis-fld">
        <label for="soort">Soort <span class="req">*</span></label>
        <select id="soort" name="soort" required>
          @foreach (Quotesoort::cases() as $s)
            <option value="{{ $s->value }}" @selected(old('soort', Quotesoort::Quote->value) === $s->value)>{{ $s->label() }}</option>
          @endforeach
        </select>
      </div>
      <div class="sis-fld">
        <label for="titel">Kop</label>
        <input id="titel" name="titel" type="text" value="{{ old('titel') }}" placeholder="Bijv. Ar-Rahman">
      </div>
    </div>
    <div class="sis-fld">
      <label for="arabisch">Arabische tekst</label>
      <input id="arabisch" name="arabisch" type="text" dir="rtl" lang="ar" value="{{ old('arabisch') }}"
             style="font-size:18px;">
    </div>
    <div class="sis-fld">
      <label for="betekenis">Nederlandse betekenis of spreuk <span class="req">*</span></label>
      <textarea id="betekenis" name="betekenis" required maxlength="2000">{{ old('betekenis') }}</textarea>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 16px;">
      <div class="sis-fld">
        <label for="bron">Bron</label>
        <input id="bron" name="bron" type="text" value="{{ old('bron') }}" placeholder="Bijv. Soera Al-Baqara 2:286">
      </div>
      <div class="sis-fld">
        <label for="afbeelding">Afbeelding (456 × 456 px, PNG)</label>
        <input id="afbeelding" name="afbeelding" type="file" accept="image/png,image/jpeg,image/webp">
      </div>
    </div>
    @error('afbeelding')<p style="color:var(--secColor100);font-size:12.5px;margin:-8px 0 12px;">{{ $message }}</p>@enderror
    @error('betekenis')<p style="color:var(--secColor100);font-size:12.5px;margin:-8px 0 12px;">{{ $message }}</p>@enderror
    <label class="sis-check-inline" style="font-size:12.5px;margin-bottom:12px;display:block;">
      <input type="hidden" name="actief" value="0">
      <input type="checkbox" name="actief" value="1" @checked(old('actief', true))> Meteen tonen in de zijbalk
    </label>
    <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--primary">Toevoegen</button>
  </form>
</div>

<form method="GET" action="{{ route('quotes') }}" class="iuasr-dash-filters" style="grid-template-columns:1fr;">
  <select name="soort" onchange="this.form.submit()">
    <option value="">Alle soorten</option>
    @foreach (Quotesoort::cases() as $s)
      <option value="{{ $s->value }}" @selected($soort === $s->value)>{{ $s->meervoud() }}</option>
    @endforeach
  </select>
</form>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl" style="min-width:760px;">
    <thead><tr><th style="width:64px;">#</th><th style="width:70px;">Beeld</th><th>Quote</th><th>Soort</th><th style="width:80px;">Status</th><th class="row-act">Acties</th></tr></thead>
    <tbody>
      @forelse ($quotes as $q)
        <tr @class(['sis-row-uit' => ! $q->actief])>
          <td class="dt">{{ $q->volgorde }}</td>
          <td>
            @if ($q->heeftAfbeelding())
              <img src="{{ route('quotes.afbeelding', $q) }}" alt="" width="40" height="40"
                   style="width:40px;height:40px;border-radius:7px;background:var(--priColor100);padding:2px;object-fit:contain;">
            @elseif ($q->arabisch)
              <span style="font-size:19px;color:var(--goud,#D69A2D);" dir="rtl" lang="ar">{{ $q->arabisch }}</span>
            @else
              <span class="sis-muted">—</span>
            @endif
          </td>
          <td class="nm">
            @if ($q->kop())<b>{{ $q->kop() }}</b> — @endif{{ $q->betekenis }}
            @if ($q->bron)<div class="sis-muted" style="font-size:11px;font-style:italic;">{{ $q->bron }}</div>@endif
          </td>
          <td class="dt">{{ $q->soort->label() }}</td>
          <td class="dt">
            <form method="POST" action="{{ route('quotes.toggle', $q) }}" style="display:inline;">
              @csrf @method('PUT')
              <button type="submit" class="sis-pill-soft" style="cursor:pointer;border:0;{{ $q->actief ? '' : 'opacity:.55;' }}"
                      title="{{ $q->actief ? 'Verbergen uit de zijbalk' : 'Tonen in de zijbalk' }}">
                {{ $q->actief ? 'Actief' : 'Uit' }}
              </button>
            </form>
          </td>
          <td class="row-act">
            <details>
              <summary class="iuasr-dash-btn iuasr-dash-btn--sm" style="cursor:pointer;">Wijzigen</summary>
              <form method="POST" action="{{ route('quotes.update', $q) }}" class="sis-form" enctype="multipart/form-data"
                    style="margin-top:10px;text-align:left;min-width:280px;">
                @csrf @method('PUT')
                <input type="hidden" name="soort" value="{{ $q->soort->value }}">
                <div class="sis-fld"><label>Kop</label><input name="titel" type="text" value="{{ $q->titel }}"></div>
                <div class="sis-fld"><label>Arabisch</label><input name="arabisch" type="text" dir="rtl" lang="ar" value="{{ $q->arabisch }}" style="font-size:17px;"></div>
                <div class="sis-fld"><label>Betekenis <span class="req">*</span></label><textarea name="betekenis" required>{{ $q->betekenis }}</textarea></div>
                <div class="sis-fld"><label>Bron</label><input name="bron" type="text" value="{{ $q->bron }}"></div>
                <div class="sis-fld"><label>Volgorde</label><input name="volgorde" type="number" min="0" max="65535" value="{{ $q->volgorde }}"></div>
                <div class="sis-fld"><label>Nieuwe afbeelding (456 × 456 px)</label><input name="afbeelding" type="file" accept="image/png,image/jpeg,image/webp"></div>
                @if ($q->heeftAfbeelding())
                  <label class="sis-check-inline" style="font-size:12px;display:block;margin-bottom:10px;">
                    <input type="checkbox" name="afbeelding_verwijderen" value="1"> Huidige afbeelding verwijderen
                  </label>
                @endif
                <label class="sis-check-inline" style="font-size:12px;display:block;margin-bottom:10px;">
                  <input type="hidden" name="actief" value="0">
                  <input type="checkbox" name="actief" value="1" @checked($q->actief)> Actief
                </label>
                <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--primary iuasr-dash-btn--sm">Opslaan</button>
              </form>
            </details>
            <form method="POST" action="{{ route('quotes.destroy', $q) }}" style="display:inline;"
                  onsubmit="return confirm('Deze quote definitief verwijderen?');">
              @csrf @method('DELETE')
              <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--danger iuasr-dash-btn--sm">Verwijderen</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="sis-muted" style="text-align:center;padding:22px;">Nog geen quotes.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

{{ $quotes->links() }}
@endsection
