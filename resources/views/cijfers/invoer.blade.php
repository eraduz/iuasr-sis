@extends('layouts.app')

@section('titel', 'Cijferinvoer · '.$vak->code)

@php $terug = auth()->user()->rolIs('docent') ? route('mijn-vakken') : route('cijferoverzicht'); @endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ $terug }}">{{ auth()->user()->rolIs('docent') ? 'Mijn vakken' : 'Cijferoverzicht' }}</a><span class="sep">›</span><b>{{ $vak->code }}</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>{{ $vak->naam }}</h1>
    <div class="summary"><b>{{ $vak->code }}</b> · {{ $vak->opleiding?->naam }} · {{ $vak->ec }} EC · {{ $rijen->count() }} studenten</div>
  </div>
  <div class="iuasr-dash-vhead__actions">
    @unless ($magInvoeren)<span class="sis-role-note"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg> Inzage — alleen-lezen</span>@endunless
  </div>
</div>

<div class="sis-card" style="margin-bottom:16px;">
  <div class="sis-card__hd"><h3>Toetsopbouw</h3><span class="hint">Weging telt op tot 100%</span></div>
  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    @foreach ($vak->toetsonderdelen as $od)
      <span class="sis-pill-soft" style="font-size:12px;padding:5px 12px;">{{ $od->naam }} · <b style="color:var(--priColor100);">{{ rtrim(rtrim(number_format($od->weging*100,0),'0'),'.') }}%</b></span>
    @endforeach
    <span class="sis-pill-soft" style="font-size:12px;padding:5px 12px;">Cesuur · <b style="color:var(--priColor100);">{{ number_format($grens,1,',','') }}</b></span>
  </div>
</div>

<form method="POST" action="{{ route('vakken.cijfers.opslaan', $vak) }}">
  @csrf
  <div class="iuasr-dash-tbl-card">
    <table class="iuasr-dash-tbl" id="grade-tbl">
      <thead>
        <tr>
          <th style="width:190px;">Student</th>
          @foreach ($vak->toetsonderdelen as $od)
            <th style="text-align:center;">{{ $od->naam }}<br><span class="sis-weegcell">{{ rtrim(rtrim(number_format($od->weging*100,0),'0'),'.') }}%</span></th>
          @endforeach
          <th style="text-align:center;">Poging</th>
          <th style="text-align:center;">Vrijstelling</th>
          <th style="text-align:right;">Eindcijfer</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($rijen as $rij)
          @php $insch = $rij['inschrijving']; $student = $rij['student']; $eind = $rij['eind']; @endphp
          <tr data-insch="{{ $insch->id }}">
            <td class="nm">{{ $student->volledigeNaam() }}<small>{{ $student->studentnummer }}</small></td>
            @foreach ($vak->toetsonderdelen as $od)
              @php $res = $rij['resultaten'][$od->id] ?? null; $val = $res && $res->cijfer !== null ? number_format($res->cijfer,1,',','') : ''; @endphp
              <td style="text-align:center;">
                <input class="sis-grade-input" inputmode="decimal" name="cijfer[{{ $insch->id }}][{{ $od->id }}]" value="{{ $val }}" {{ $magInvoeren ? '' : 'disabled' }}>
              </td>
            @endforeach
            <td style="text-align:center;">
              <input type="hidden" name="poging[{{ $insch->id }}]" value="{{ $rij['poging'] }}">
              <span class="sis-attempt">
                <button type="button" data-p="tentamen" class="{{ $rij['poging']==='tentamen' ? 'is-on' : '' }}" {{ $magInvoeren ? '' : 'disabled' }}>Tent.</button>
                <button type="button" data-p="herkansing" class="{{ $rij['poging']==='herkansing' ? 'is-on' : '' }}" {{ $magInvoeren ? '' : 'disabled' }}>Herk.</button>
              </span>
            </td>
            <td style="text-align:center;">
              <label class="sis-check-inline" style="justify-content:center;"><input type="checkbox" class="vr-check" name="vrijstelling[{{ $insch->id }}]" value="1" @checked($rij['vrijstelling']) {{ $magInvoeren ? '' : 'disabled' }}></label>
            </td>
            <td style="text-align:right;" class="final">
              @if ($eind['status']==='vr')<span class="sis-pill-soft">VR</span>
              @elseif ($eind['status']==='cijfer')<span class="sis-grade-final {{ $eind['cijfer']<$grens ? 'is-fail' : '' }}">{{ number_format($eind['cijfer'],1,',','') }}</span>
              @elseif ($eind['status']==='onvolledig')<span class="sis-muted" style="font-size:12px;">onvolledig</span>
              @else<span class="sis-muted">—</span>@endif
            </td>
          </tr>
        @empty
          <tr><td colspan="{{ $vak->toetsonderdelen->count() + 4 }}"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen deelnemers</h3><p>Er zijn geen actieve studenten voor dit vak in de huidige periode.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if ($magInvoeren && $rijen->isNotEmpty())
    <div class="sis-savebar">
      <span class="status"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Eindcijfer wordt live berekend; wijzigingen worden gelogd.</span>
      <span class="grow"></span>
      <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Cijfers opslaan</button>
    </div>
  @endif
</form>

<p class="sis-tblnote">Eindcijfer = gewogen gemiddelde van de deelresultaten (1 decimaal). Bij <b>Vrijstelling</b> vervallen de deelvelden en geldt “VR”. EC worden toegekend als álle meetellende onderdelen voldoende zijn.</p>

@push('scripts')
<script>
  var WEGING = @json($vak->toetsonderdelen->pluck('weging')->map(fn($w)=>(float)$w)->values());
  var CESUUR = {{ $grens }};

  function calcRow(tr) {
    var vr = tr.querySelector('.vr-check').checked;
    var inputs = tr.querySelectorAll('.sis-grade-input');
    var finalCell = tr.querySelector('.final');
    inputs.forEach(function (i) { i.disabled = vr || i.dataset.locked === '1'; i.classList.remove('is-pass','is-fail'); });
    if (vr) { finalCell.innerHTML = '<span class="sis-pill-soft">VR</span>'; return; }
    var som = 0, gew = 0, compleet = true, any = false;
    inputs.forEach(function (i, idx) {
      var v = parseFloat((i.value || '').replace(',', '.'));
      if (!isNaN(v)) { som += v * WEGING[idx]; gew += WEGING[idx]; any = true; i.classList.add(v >= CESUUR ? 'is-pass' : 'is-fail'); }
      else { compleet = false; }
    });
    if (!any) { finalCell.innerHTML = '<span class="sis-muted">—</span>'; return; }
    if (!compleet) { finalCell.innerHTML = '<span class="sis-muted" style="font-size:12px;">onvolledig</span>'; return; }
    var f = Math.round((som / gew) * 10) / 10;
    finalCell.innerHTML = '<span class="sis-grade-final' + (f < CESUUR ? ' is-fail' : '') + '">' + f.toFixed(1).replace('.', ',') + '</span>';
  }

  document.querySelectorAll('#grade-tbl tbody tr[data-insch]').forEach(function (tr) {
    tr.querySelectorAll('.sis-grade-input').forEach(function (i) {
      i.dataset.locked = i.disabled ? '1' : '0';
      i.addEventListener('input', function () { calcRow(tr); });
    });
    var vr = tr.querySelector('.vr-check');
    if (vr) vr.addEventListener('change', function () { calcRow(tr); });
    tr.querySelectorAll('.sis-attempt button').forEach(function (b) {
      b.addEventListener('click', function () {
        if (b.disabled) return;
        tr.querySelectorAll('.sis-attempt button').forEach(function (x) { x.classList.remove('is-on'); });
        b.classList.add('is-on');
        tr.querySelector('input[name^="poging"]').value = b.dataset.p;
      });
    });
  });
</script>
@endpush
@endsection
