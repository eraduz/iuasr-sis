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
  <div class="iuasr-dash-vhead__actions"><a class="iuasr-dash-btn" href="{{ route('audit-log') }}">Audit-log</a></div>
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
        <div style="min-width:180px;flex:0 0 auto;">
          <b style="font-size:13px;">{{ $d->naam }}</b>
          <div class="sis-muted" style="font-size:11.5px;">{{ $d->email }}</div>
        </div>
        <div style="flex:1 1 320px;">
          <div style="display:flex;flex-wrap:wrap;gap:6px 14px;">
            @foreach ($opleidingen as $o)
              <label class="sis-check-inline" style="font-size:12px;">
                <input type="checkbox" name="opleidingen[]" value="{{ $o->id }}" @checked($toegewezen->contains($o->id))> {{ $o->naam }}
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
            <td><span class="sis-rolebadge r-{{ $g->rol->value }}">{{ $g->rol->label() }}</span></td>
            <td class="dt">{{ $g->laatst_ingelogd_op?->diffForHumans() ?? 'nooit' }}</td>
            <td>@if($g->actief)<span class="iuasr-dash-status s-approved">Actief</span>@else<span class="iuasr-dash-status s-draft">Inactief</span>@endif</td>
            <td class="row-act">
              <form method="POST" action="{{ route('gebruikers.rol', $g) }}" style="display:flex;gap:6px;align-items:center;justify-content:flex-end;">
                @csrf @method('PUT')
                <select name="rol" style="height:30px;font-size:12.5px;">
                  @foreach ($rollen as $r)<option value="{{ $r->value }}" @selected($g->rol === $r)>{{ $r->label() }}</option>@endforeach
                </select>
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
