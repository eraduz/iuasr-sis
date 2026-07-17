@extends('layouts.app')

@section('titel', 'Gebruikers & rollen')

@php
  $J = '<span class="sis-access sis-access--yes"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>';
  $N = '<span class="sis-access sis-access--no"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span>';
  $tag = fn($t) => '<span class="sis-pill-soft" style="font-size:10.5px;">'.$t.'</span>';
  // Celvolgorde = rolvolgorde: Studentenzaken, Financiën, Docent, Examencie, Directie, Beheerder.
  $matrix = [
    ['Studenten inzien',        [$J, $N, $N, $J, $tag('beperkt'), $N]],
    ['Persoonsgegevens / BSN',  [$J, $N, $N, $N, $N, $J]],
    ['In-/uitschrijven',        [$J, $N, $N, $N, $N, $N]],
    ['Cijfers invoeren',        [$N, $N, $tag('eigen vak'), $N, $N, $N]],
    ['Cijfers inzien',          [$N, $N, $tag('eigen vak'), $J, $J, $N]],
    ['Verklaringen uitgeven',   [$J, $N, $N, $N, $N, $N]],
    ['Collegegeld instellen',   [$J, $N, $N, $N, $N, $J]],
    ['Betalingen registreren',  [$N, $J, $N, $N, $N, $J]],
    ['Betaalachterstand inzien',[$J, $J, $N, $N, $J, $J]],
    ['Opzoektabellen beheren',  [$N, $N, $N, $N, $N, $J]],
  ];
@endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Gebruikers &amp; rollen</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Gebruikers &amp; rollen</h1>
    <div class="summary"><b>{{ $gebruikers->count() }}</b> gebruikers · <b>{{ count($rollen) }}</b> rollen · toegang via IUASR SSO</div>
  </div>
  <div class="iuasr-dash-vhead__actions">
    <a class="iuasr-dash-btn" href="{{ route('noodaccounts') }}">Noodaccounts</a>
    <a class="iuasr-dash-btn" href="{{ route('audit-log') }}">Audit-log</a>
  </div>
</div>

<div class="sis-card" style="margin-bottom:18px;">
  <div class="sis-card__hd"><h3>Toegangsmatrix</h3><span class="hint">Wat elke rol mag — dit stuurt de zichtbaarheid van cijfers en persoonsgegevens</span></div>
  <div class="iuasr-dash-tbl-card" style="border:0;overflow-x:auto;">
    <table class="iuasr-dash-tbl" style="min-width:720px;">
      <thead><tr><th>Recht</th>@foreach ($rollen as $r)<th style="text-align:center;">{{ $r->label() }}</th>@endforeach</tr></thead>
      <tbody>
        @foreach ($matrix as [$recht, $cellen])
          <tr><td class="nm">{{ $recht }}</td>@foreach ($cellen as $c)<td style="text-align:center;">{!! $c !!}</td>@endforeach</tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <p class="sis-tblnote">Cijfers zijn bewust <b>niet</b> zichtbaar voor Studentenzaken. Docenten zien alleen hun eigen vak. Directie en examencommissie hebben inzage; alleen de examencommissie stelt vast.</p>
</div>

<div class="sis-card" style="margin-bottom:18px;border-left:3px solid var(--heritage-groen,#285C4D);">
  <div class="sis-card__hd"><h3>Directie — opleidingtoewijzing</h3><span class="hint">Een directielid ziet uitsluitend studenten, cijfers en rapporten van de toegewezen opleiding(en)</span></div>
  @if ($directie->isEmpty())
    <p class="sis-muted" style="font-size:13px;margin:0;">Er zijn geen gebruikers met de rol Directie.</p>
  @else
    @foreach ($directie as $d)
      @php $toegewezen = $d->opleidingen->pluck('id'); @endphp
      <form method="POST" action="{{ route('gebruikers.opleidingen', $d) }}"
            style="display:flex;gap:16px;align-items:flex-start;padding:12px 0;border-top:1px solid var(--line,#e6e4ee);flex-wrap:wrap;">
        @csrf @method('PUT')
        <div style="min-width:200px;flex:0 0 auto;">
          <b style="font-size:13px;">{{ $d->naam }}</b>
          <div class="sis-muted" style="font-size:11.5px;">{{ $d->email }}</div>
          @if ($d->opleidingen->isNotEmpty())
            <div style="margin-top:5px;display:flex;flex-wrap:wrap;gap:4px;">
              @foreach ($d->opleidingen->sortBy('code') as $o)
                <span class="sis-pill-soft" style="font-size:10px;letter-spacing:.02em;" title="{{ $o->naam }}">{{ $o->code }}</span>
              @endforeach
            </div>
          @endif
        </div>
        <div style="flex:1 1 320px;">
          <div style="display:flex;flex-wrap:wrap;gap:6px 14px;">
            @foreach ($opleidingen as $o)
              <label class="sis-check-inline" style="font-size:12px;">
                <input type="checkbox" name="opleidingen[]" value="{{ $o->id }}" @checked($toegewezen->contains($o->id))> {{ $o->naam }} <span class="sis-muted">({{ $o->code }})</span>
              </label>
            @endforeach
          </div>
          @if ($toegewezen->isEmpty())<small style="color:var(--secColor100);display:block;margin-top:4px;">Nog geen toewijzing — dit directielid ziet momenteel geen studenten.</small>@endif
        </div>
        <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--sm" style="flex:0 0 auto;">Opslaan</button>
      </form>
    @endforeach
    <p class="sis-tblnote" style="margin-top:10px;">Een student met een dubbele inschrijving is zichtbaar voor de directie van elke opleiding waarin hij/zij actief is ingeschreven.</p>
  @endif
</div>

<div class="sis-card" style="margin-bottom:18px;border-left:3px solid var(--priColor100,#1E1446);">
  <div class="sis-card__hd"><h3>Gebruiker aanmaken</h3><span class="hint">Voegt een medewerkersaccount toe. Er wordt geen wachtwoord ingesteld — inloggen verloopt via Entra ID (SSO), of in ontwikkeling via de dev-login.</span></div>
  @if ($errors->any())
    <div style="background:#fdecec;border:1px solid var(--secColor100,#C8102E);color:var(--secColor100,#C8102E);border-radius:8px;padding:8px 12px;margin-bottom:12px;font-size:12.5px;">{{ $errors->first() }}</div>
  @endif
  <form method="POST" action="{{ route('gebruikers.store') }}" style="display:flex;flex-wrap:wrap;gap:12px 16px;align-items:flex-end;">
    @csrf
    <label style="display:flex;flex-direction:column;gap:3px;font-size:12px;">Naam
      <input type="text" name="naam" value="{{ old('naam') }}" required style="height:32px;font-size:13px;min-width:180px;">
    </label>
    <label style="display:flex;flex-direction:column;gap:3px;font-size:12px;">E-mail
      <input type="email" name="email" value="{{ old('email') }}" required style="height:32px;font-size:13px;min-width:220px;">
    </label>
    <label style="display:flex;flex-direction:column;gap:3px;font-size:12px;">Primaire rol
      <select name="rol" style="height:32px;font-size:13px;">
        @foreach ($rollen as $r)<option value="{{ $r->value }}" @selected(old('rol') === $r->value)>{{ $r->label() }}</option>@endforeach
      </select>
    </label>
    <details style="position:relative;">
      <summary class="iuasr-dash-btn iuasr-dash-btn--sm" style="cursor:pointer;list-style:none;">Extra rollen</summary>
      <div style="position:absolute;left:0;z-index:20;margin-top:4px;background:var(--surface,#fff);border:1px solid var(--line,#e6e4ee);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);padding:10px;min-width:230px;">
        @php $oudExtra = (array) old('rollen', []); @endphp
        @foreach ($rollen as $r)
          <label class="sis-check-inline" style="display:flex;gap:6px;font-size:11.5px;padding:2px 0;">
            <input type="checkbox" name="rollen[]" value="{{ $r->value }}" @checked(in_array($r->value, $oudExtra, true))> {{ $r->label() }}
          </label>
        @endforeach
        <small class="sis-muted" style="display:block;margin-top:6px;">Gelden náást de primaire rol; rechten worden opgeteld.</small>
      </div>
    </details>
    <label class="sis-check-inline" style="font-size:12px;"><input type="checkbox" name="actief" value="1" checked> actief</label>
    <button type="submit" class="iuasr-dash-btn">Aanmaken</button>
  </form>
  <p class="sis-tblnote" style="margin-top:10px;">De <b>primaire rol</b> bepaalt het startdashboard en de weergave. <b>Extra rollen</b> tellen erbovenop: de gebruiker mag alles wat één van zijn rollen toestaat. Bij een risicocombinatie (bijv. Studentenzaken + cijferinzage) volgt een waarschuwing en wordt de keuze gelogd.</p>
</div>

<div class="sis-card">
  <div class="sis-card__hd"><h3>Gebruikers</h3></div>
  <div class="iuasr-dash-tbl-card" style="border:0;">
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Naam</th><th>E-mail</th><th>Rol</th><th>Laatste login</th><th>Status</th><th class="row-act">Wijzigen</th></tr></thead>
      <tbody>
        @foreach ($gebruikers as $g)
          <tr>
            <td class="nm">{{ $g->naam }}</td>
            <td class="dt">{{ $g->email }}</td>
            <td>
              <span class="sis-rolebadge r-{{ $g->rol->value }}" title="Primaire rol">{{ $g->rol->label() }}</span>
              @foreach ($g->extraRollen() as $er)
                <span class="sis-rolebadge r-{{ $er->value }}" style="margin-left:3px;opacity:.9;" title="Extra rol">+ {{ $er->label() }}</span>
              @endforeach
              @if ($g->heeftRol(App\Enums\Rol::Directie) && $g->opleidingen->isNotEmpty())
                <span style="display:inline-flex;flex-wrap:wrap;gap:3px;margin-left:4px;vertical-align:middle;">
                  @foreach ($g->opleidingen->sortBy('code') as $o)<span class="sis-pill-soft" style="font-size:10px;letter-spacing:.02em;" title="{{ $o->naam }}">{{ $o->code }}</span>@endforeach
                </span>
              @endif
              @if ($g->heeftRol(App\Enums\Rol::Cursusadministratie) && $g->gedirigeerdeCursussen->isNotEmpty())
                <span style="display:inline-flex;flex-wrap:wrap;gap:3px;margin-left:4px;vertical-align:middle;">
                  @foreach ($g->gedirigeerdeCursussen->sortBy('code') as $c)<span class="sis-pill-soft" style="font-size:10px;letter-spacing:.02em;" title="{{ $c->naam }}">{{ $c->code }}</span>@endforeach
                </span>
              @endif
            </td>
            <td class="dt">{{ $g->laatst_ingelogd_op?->diffForHumans() ?? 'nooit' }}</td>
            <td>@if($g->actief)<span class="iuasr-dash-status s-approved">Actief</span>@else<span class="iuasr-dash-status s-draft">Inactief</span>@endif</td>
            <td class="row-act">
              @php $extraSet = $g->extraRollen()->map(fn ($r) => $r->value)->all(); @endphp
              <form method="POST" action="{{ route('gebruikers.rol', $g) }}" style="display:flex;gap:6px;align-items:center;justify-content:flex-end;flex-wrap:wrap;">
                @csrf @method('PUT')
                <select name="rol" style="height:30px;font-size:12.5px;" title="Primaire rol">
                  @foreach ($rollen as $r)<option value="{{ $r->value }}" @selected($g->rol === $r)>{{ $r->label() }}</option>@endforeach
                </select>
                <details style="position:relative;">
                  <summary class="iuasr-dash-btn iuasr-dash-btn--sm" style="cursor:pointer;list-style:none;">Extra rollen ({{ count($extraSet) }})</summary>
                  <div style="position:absolute;right:0;z-index:20;margin-top:4px;background:var(--surface,#fff);border:1px solid var(--line,#e6e4ee);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);padding:10px;min-width:230px;text-align:left;">
                    @foreach ($rollen as $r)
                      <label class="sis-check-inline" style="display:flex;gap:6px;font-size:11.5px;padding:2px 0;{{ $g->rol === $r ? 'opacity:.5;' : '' }}">
                        <input type="checkbox" name="rollen[]" value="{{ $r->value }}" @checked(in_array($r->value, $extraSet, true)) @disabled($g->rol === $r)>
                        {{ $r->label() }}@if ($g->rol === $r) <span class="sis-muted">(primair)</span>@endif
                      </label>
                    @endforeach
                    <small class="sis-muted" style="display:block;margin-top:6px;">Gelden náást de primaire rol; rechten worden opgeteld.</small>
                  </div>
                </details>
                <label class="sis-check-inline" style="font-size:11.5px;"><input type="checkbox" name="actief" value="1" @checked($g->actief)> actief</label>
                <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--sm">Opslaan</button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection
