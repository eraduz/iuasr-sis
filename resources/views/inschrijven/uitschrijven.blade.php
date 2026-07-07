@extends('layouts.app')

@section('titel', 'Uitschrijven')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ route('studenten.show', $student) }}">{{ $student->studentnummer }}</a><span class="sep">›</span><b>Uitschrijven</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Uitschrijven</h1>
    <div class="summary">Statuswijziging · berekende uitschrijfdatum (einde lopende maand)</div>
  </div>
</div>

<div class="sis-grid-2">
  <div>
    <div class="sis-card" style="margin-bottom:16px;">
      <div class="iuasr-dash-candidate__hd" style="margin:0;padding:0;border:0;">
        <span class="iuasr-dash-candidate__avatar" style="width:44px;height:44px;font-size:18px;" aria-hidden="true">{{ mb_substr($student->voornaam,0,1) }}</span>
        <div class="iuasr-dash-candidate__body">
          <div style="font-family:var(--serif-font);font-size:19px;">{{ $student->volledigeNaam() }}</div>
          <div class="iuasr-dash-candidate__meta"><span>{{ $student->studentnummer }}</span><span class="dot"></span><span>{{ $huidige->opleiding?->naam }}</span></div>
        </div>
        <span class="iuasr-dash-status {{ $huidige->status->badge() }}" style="align-self:flex-start;">{{ $huidige->status->label() }}</span>
      </div>
    </div>

    <form method="POST" action="{{ route('uitschrijven.store', $student) }}" class="sis-card sis-form">
      @csrf
      <fieldset class="sis-fieldset">
        <legend>Uitschrijving</legend>
        <div class="sis-fld">
          <label>Reden uitschrijving <span class="req">*</span></label>
          <select name="reden" required>
            <option>Op eigen verzoek</option>
            <option>Afgestudeerd</option>
            <option>Negatief bindend studieadvies (BSA)</option>
            <option>Overstap andere instelling</option>
            <option>Financieel / niet voldaan</option>
          </select>
        </div>
        <div class="sis-fld">
          <label>Peildatum verzoek <span class="req">*</span></label>
          <input type="date" name="peildatum" id="peildatum" value="{{ old('peildatum', now()->toDateString()) }}" required>
          <div class="help">De uitschrijfdatum wordt berekend als het einde van de lopende maand.</div>
        </div>
        <div class="sis-fld"><label>Toelichting (interne notitie)</label><textarea name="toelichting" placeholder="Optioneel — reden, afspraken, doorverwijzing…">{{ old('toelichting') }}</textarea></div>
      </fieldset>
      <div class="sis-form__actions">
        <a class="iuasr-dash-btn" href="{{ route('studenten.show', $student) }}">Annuleren</a>
        <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--danger" type="submit">Uitschrijven bevestigen</button></div>
      </div>
    </form>
  </div>

  <div>
    <div class="sis-card">
      <div class="sis-card__hd"><h3>Gevolgen</h3></div>
      <dl class="sis-dl">
        <dt>Nieuwe status</dt><dd><span class="iuasr-dash-status s-draft">Uitgeschreven</span></dd>
        <dt>Berekende datum</dt><dd><b id="calc-date">—</b></dd>
        <dt>Klasplaatsing</dt><dd>Vervalt per uitschrijfdatum</dd>
        <dt>Behaalde EC</dt><dd>Blijven geregistreerd</dd>
      </dl>
    </div>

    <div class="sis-card" style="margin-top:16px;">
      <div class="sis-card__hd"><h3>Verschuldigd collegegeld</h3><span class="hint">pro rata t/m uitschrijfmaand</span></div>
      @if ($fin['jaarbedrag'] === null)
        <p class="sis-muted" style="font-size:13px;margin:0;">Geen collegegeldtarief ingesteld voor dit studiejaar.</p>
      @else
        <dl class="sis-dl">
          <dt>Jaarcollegegeld</dt><dd>€ {{ number_format($fin['jaarbedrag'], 2, ',', '.') }}</dd>
          <dt>Maandbedrag</dt><dd>€ {{ number_format($fin['maandbedrag'], 2, ',', '.') }} <span class="sis-muted" style="font-size:11px;">· ÷ 12</span></dd>
          <dt>Ingeschreven</dt><dd><b id="calc-maanden">—</b> maanden</dd>
          <dt>Verschuldigd</dt><dd><b id="calc-verschuldigd">—</b></dd>
          <dt>Betaald</dt><dd>€ {{ number_format($fin['betaald'], 2, ',', '.') }}</dd>
          <dt>Saldo</dt><dd><b id="calc-saldo">—</b></dd>
        </dl>
      @endif
    </div>
    <div class="iuasr-dash-alert iuasr-dash-alert--warn" style="margin-top:16px;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <span>Een uitgeschreven student kan later opnieuw worden ingeschreven; het studentnummer blijft behouden.</span>
    </div>
  </div>
</div>

@push('scripts')
<script>
  var maanden = ['januari','februari','maart','april','mei','juni','juli','augustus','september','oktober','november','december'];
  var inp = document.getElementById('peildatum'), out = document.getElementById('calc-date');
  var MAANDBEDRAG = {{ $fin['maandbedrag'] ?? 'null' }}, STARTJAAR = {{ $fin['startjaar'] ?? 'null' }}, BETAALD = {{ $fin['betaald'] ?? 0 }};
  function euro(n){ return '€ ' + n.toLocaleString('nl-NL', {minimumFractionDigits:2, maximumFractionDigits:2}); }
  function recalc(){
    if(!inp.value) return;
    var d = new Date(inp.value);
    var e = new Date(d.getFullYear(), d.getMonth()+1, 0); // einde van de maand
    out.textContent = e.getDate()+' '+maanden[e.getMonth()]+' '+e.getFullYear();
    if (MAANDBEDRAG === null || STARTJAAR === null) return;
    var m = (e.getFullYear()-STARTJAAR)*12 + (e.getMonth()+1 - 9) + 1; // sept = maand 1
    m = Math.max(0, Math.min(12, m));
    var verschuldigd = Math.round(MAANDBEDRAG * m * 100) / 100;
    var saldo = Math.round((BETAALD - verschuldigd) * 100) / 100;
    document.getElementById('calc-maanden').textContent = m;
    document.getElementById('calc-verschuldigd').textContent = euro(verschuldigd);
    var s = document.getElementById('calc-saldo');
    if (saldo > 0) { s.textContent = 'Terug te betalen ' + euro(saldo); }
    else if (saldo < 0) { s.textContent = 'Nog te betalen ' + euro(-saldo); }
    else { s.textContent = 'Voldaan'; }
  }
  inp.addEventListener('input', recalc); recalc();
</script>
@endpush
@endsection
