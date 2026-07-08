@extends('layouts.app')

@section('titel', 'Back-up & herstel')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Back-up &amp; herstel</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Back-up &amp; herstel</h1>
    <div class="summary">Volledige recovery-back-up als één met wachtwoord versleutelde ZIP</div>
  </div>
</div>

@if (session('status'))
  <div class="iuasr-dash-alert iuasr-dash-alert--ok" style="margin-bottom:16px;"><span>{{ session('status') }}</span></div>
@endif

<div class="sis-grid-2" style="align-items:start;">
  <div class="sis-card sis-form">
    <div class="sis-card__hd"><h3>Back-up genereren</h3></div>

    <form method="POST" action="{{ route('backup.download') }}" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Bezig met genereren…';">
      @csrf
      <p class="sis-muted" style="font-size:13px;margin:0 0 14px;">
        Kies een sterk wachtwoord. Dit wordt gebruikt om het ZIP-archief te
        versleutelen (AES-256) en wordt <b>nergens opgeslagen</b> — bewaar het
        veilig, zonder wachtwoord is de back-up onbruikbaar.
      </p>

      <div class="sis-fld">
        <label>Wachtwoord voor het archief</label>
        <input type="password" name="wachtwoord" required minlength="8" autocomplete="new-password" placeholder="minimaal 8 tekens">
        @error('wachtwoord')<small style="color:var(--secColor100);">{{ $message }}</small>@enderror
      </div>
      <div class="sis-fld">
        <label>Wachtwoord bevestigen</label>
        <input type="password" name="wachtwoord_confirmation" required minlength="8" autocomplete="new-password">
      </div>

      <div style="display:flex;align-items:center;gap:12px;margin-top:6px;">
        <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Back-up genereren &amp; downloaden</button>
        <span class="sis-muted" style="font-size:12px;">Het genereren kan even duren.</span>
      </div>
    </form>
  </div>

  <div class="sis-card">
    <div class="sis-card__hd"><h3>Wat zit er in de back-up?</h3></div>
    <ul class="iuasr-dash-log" style="margin:0 0 14px;">
      <li><b>Database</b><time>Volledige dump (structuur + alle gegevens)</time></li>
      <li><b>Applicatie &amp; webpagina's</b><time>Broncode, schermen en configuratie</time></li>
      <li><b>Configuratie (.env incl. sleutel)</b><time>Nodig om BSN/rekeningnummer te herstellen</time></li>
      <li><b>Geüploade bestanden</b><time>Documenten en ondertekende PDF's</time></li>
    </ul>
    <p class="sis-tblnote" style="margin:0;">
      Niet inbegrepen (te herleiden): <b>vendor/</b> (herstel via <code>composer install</code>),
      <b>.git/</b> en de referentiemap <b>IUASR/</b>.
    </p>
  </div>
</div>

<div class="iuasr-dash-alert iuasr-dash-alert--warn" style="margin-top:16px;">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
  <span>Deze back-up bevat <b>alle persoonsgegevens én de encryptiesleutel</b>. Bewaar het archief uitsluitend versleuteld op een beveiligde, interne locatie (AVG). Het downloaden wordt gelogd.</span>
</div>
@endsection
