@extends('layouts.app')

@section('titel', 'Systeemmeldingen')

@php
  use App\Enums\Meldingniveau;
  use App\Enums\Rol;
  $nu = now();
@endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Systeemmeldingen</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Systeemmeldingen</h1>
    <div class="summary"><b>{{ $lopend }}</b> {{ $lopend === 1 ? 'melding loopt' : 'meldingen lopen' }} nu · verschijnt bovenaan elke pagina van elke module</div>
  </div>
</div>

<div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-bottom:18px;">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
  <div>
    Een melding verschijnt <b>vanzelf</b> op het tijdstip bij <i>Vanaf</i> en verdwijnt <b>vanzelf</b> bij <i>Tot</i> — standaard {{ $standaardDuur }} uur later.
    Daar hoeft niemand iets voor te doen; er draait geen taak die kan uitvallen. U kunt een melding ook vooruit klaarzetten door <i>Vanaf</i> in de toekomst te leggen.
    Wijzigt u een lopende melding, dan verschijnt hij opnieuw bij iedereen die hem al had weggeklikt — zo bereikt een correctie juist de mensen die de oude versie zagen.
  </div>
</div>

<div class="sis-card" style="margin-bottom:18px;">
  <div class="sis-card__hd"><h3>Nieuwe melding</h3><span class="hint">Bijvoorbeeld: vandaag onderhoud vanaf 18:00</span></div>
  <form method="POST" action="{{ route('meldingen.store') }}" class="sis-form">
    @csrf
    <div style="display:grid;grid-template-columns:180px 1fr;gap:0 16px;">
      <div class="sis-fld">
        <label for="niveau">Soort <span class="req">*</span></label>
        <select id="niveau" name="niveau" required>
          @foreach (Meldingniveau::cases() as $n)
            <option value="{{ $n->value }}" @selected(old('niveau', Meldingniveau::Waarschuwing->value) === $n->value)>{{ $n->label() }}</option>
          @endforeach
        </select>
      </div>
      <div class="sis-fld">
        <label for="titel">Kop <span class="req">*</span></label>
        <input id="titel" name="titel" type="text" required maxlength="120" value="{{ old('titel') }}" placeholder="Gepland onderhoud">
      </div>
    </div>
    <div class="sis-fld">
      <label for="tekst">Bericht <span class="req">*</span></label>
      <textarea id="tekst" name="tekst" required maxlength="1000" placeholder="Vandaag is het systeem vanaf 18.00 uur niet beschikbaar wegens onderhoud. Rond de klok van 20.00 uur kunt u weer inloggen.">{{ old('tekst') }}</textarea>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 16px;">
      <div class="sis-fld">
        <label for="van">Vanaf</label>
        <input id="van" name="van" type="datetime-local" value="{{ old('van', $nu->format('Y-m-d\TH:i')) }}">
        <span class="sis-muted" style="font-size:11.5px;">Leeg of nu = meteen zichtbaar.</span>
      </div>
      <div class="sis-fld">
        <label for="tot">Tot <span class="req">*</span></label>
        <input id="tot" name="tot" type="datetime-local" required
               value="{{ old('tot', $nu->copy()->addHours($standaardDuur)->format('Y-m-d\TH:i')) }}">
        <span class="sis-muted" style="font-size:11.5px;">Hierna verdwijnt de melding vanzelf.</span>
      </div>
    </div>
    @error('tot')<p style="color:var(--secColor100);font-size:12.5px;margin:-8px 0 12px;">{{ $message }}</p>@enderror
    @error('titel')<p style="color:var(--secColor100);font-size:12.5px;margin:-8px 0 12px;">{{ $message }}</p>@enderror
    @error('tekst')<p style="color:var(--secColor100);font-size:12.5px;margin:-8px 0 12px;">{{ $message }}</p>@enderror

    <div class="sis-fld">
      <label>Voor wie?</label>
      <span class="sis-muted" style="font-size:11.5px;display:block;margin-bottom:6px;">Niets aanvinken = iedereen. Vink alleen aan als de melding maar voor een deel van de organisatie bedoeld is.</span>
      <div style="display:flex;flex-wrap:wrap;gap:6px 14px;">
        @foreach (Rol::cases() as $r)
          <label class="sis-check-inline" style="font-size:12px;">
            <input type="checkbox" name="rollen[]" value="{{ $r->value }}" @checked(in_array($r->value, old('rollen', []), true))> {{ $r->label() }}
          </label>
        @endforeach
      </div>
    </div>

    <label class="sis-check-inline" style="font-size:12.5px;display:block;margin-bottom:12px;">
      <input type="hidden" name="afsluitbaar" value="0">
      <input type="checkbox" name="afsluitbaar" value="1" @checked(old('afsluitbaar', true))>
      Medewerker mag de melding wegklikken
      <span class="sis-muted">(zet dit uit bij een storing die iedereen moet zien)</span>
    </label>

    <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--primary">Melding plaatsen</button>
  </form>
</div>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl" style="min-width:860px;">
    <thead><tr><th style="width:92px;">Status</th><th>Melding</th><th style="width:104px;">Soort</th><th style="width:150px;">Zichtbaar</th><th style="width:130px;">Voor wie</th><th class="row-act">Acties</th></tr></thead>
    <tbody>
      @forelse ($meldingen as $m)
        <tr @class(['sis-row-uit' => $m->isVerlopen()])>
          <td class="dt">
            <span class="sis-pill-soft" @style(['color:var(--secColor100);border-color:var(--secColor100);' => $m->isLopend()])>{{ $m->status() }}</span>
          </td>
          <td class="nm">
            <b>{{ $m->titel }}</b>
            <div class="sis-muted" style="font-size:11.5px;">{{ Str::limit($m->tekst, 110) }}</div>
            <div class="sis-muted" style="font-size:10.5px;">door {{ $m->aangemaaktDoor?->naam ?? '—' }}@unless ($m->afsluitbaar) · niet weg te klikken @endunless</div>
          </td>
          <td class="dt">{{ $m->niveau->label() }}</td>
          <td class="dt" style="font-size:11.5px;">
            {{ $m->van->format('d-m-Y H:i') }}<br>t/m {{ $m->tot->format('d-m-Y H:i') }}
          </td>
          <td class="dt" style="font-size:11.5px;">
            @if ($m->voorIedereen())
              Iedereen
            @else
              @foreach ($m->rollen as $sleutel)
                <span class="sis-pill-soft" style="font-size:10px;">{{ Rol::tryFrom($sleutel)?->label() ?? $sleutel }}</span>
              @endforeach
            @endif
          </td>
          <td class="row-act">
            @unless ($m->isVerlopen())
              <form method="POST" action="{{ route('meldingen.intrekken', $m) }}" style="display:inline;"
                    onsubmit="return confirm('Deze melding nu van alle schermen halen?');">
                @csrf @method('PUT')
                <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--sm">Nu stoppen</button>
              </form>
            @endunless
            <details style="display:inline-block;">
              <summary class="iuasr-dash-btn iuasr-dash-btn--sm" style="cursor:pointer;">Wijzigen</summary>
              <form method="POST" action="{{ route('meldingen.update', $m) }}" class="sis-form" style="margin-top:10px;text-align:left;min-width:300px;">
                @csrf @method('PUT')
                <div class="sis-fld">
                  <label>Soort</label>
                  <select name="niveau">
                    @foreach (Meldingniveau::cases() as $n)
                      <option value="{{ $n->value }}" @selected($m->niveau === $n)>{{ $n->label() }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="sis-fld"><label>Kop <span class="req">*</span></label><input name="titel" type="text" required maxlength="120" value="{{ $m->titel }}"></div>
                <div class="sis-fld"><label>Bericht <span class="req">*</span></label><textarea name="tekst" required maxlength="1000">{{ $m->tekst }}</textarea></div>
                <div class="sis-fld"><label>Vanaf</label><input name="van" type="datetime-local" value="{{ $m->van->format('Y-m-d\TH:i') }}"></div>
                <div class="sis-fld"><label>Tot <span class="req">*</span></label><input name="tot" type="datetime-local" required value="{{ $m->tot->format('Y-m-d\TH:i') }}"></div>
                <div class="sis-fld">
                  <label>Voor wie? <span class="sis-muted">(niets = iedereen)</span></label>
                  <div style="display:flex;flex-wrap:wrap;gap:5px 12px;">
                    @foreach (Rol::cases() as $r)
                      <label class="sis-check-inline" style="font-size:11.5px;">
                        <input type="checkbox" name="rollen[]" value="{{ $r->value }}" @checked(in_array($r->value, $m->rollen ?? [], true))> {{ $r->label() }}
                      </label>
                    @endforeach
                  </div>
                </div>
                <label class="sis-check-inline" style="font-size:12px;display:block;margin-bottom:10px;">
                  <input type="hidden" name="afsluitbaar" value="0">
                  <input type="checkbox" name="afsluitbaar" value="1" @checked($m->afsluitbaar)> Mag weggeklikt worden
                </label>
                <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--primary iuasr-dash-btn--sm">Opslaan</button>
              </form>
            </details>
            <form method="POST" action="{{ route('meldingen.destroy', $m) }}" style="display:inline;"
                  onsubmit="return confirm('Deze melding definitief verwijderen uit de historie?');">
              @csrf @method('DELETE')
              <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--danger iuasr-dash-btn--sm">Verwijderen</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="sis-muted" style="text-align:center;padding:22px;">Nog geen meldingen geplaatst.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<p class="sis-tblnote">
  Verlopen meldingen blijven hier staan als historie — zo is terug te zien wat er is omgeroepen en door wie.
  Ze worden na {{ config('sis.melding.bewaartermijn_dagen') }} dagen automatisch opgeruimd.
</p>

{{ $meldingen->links() }}
@endsection
