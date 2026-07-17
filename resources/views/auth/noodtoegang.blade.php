<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Noodtoegang — IUASR Management Systeem</title>
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Fira+Sans:wght@200;300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/sis.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/iuasr-plugin-dash.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/sis-theme.css') }}">
{{-- Donker is de standaard (energiezuinig); een bewuste 'licht'-keuze blijft behouden. --}}
<script>try{var t=localStorage.getItem('sis-theme');document.documentElement.setAttribute('data-theme',t==='light'?'light':'dark');}catch(e){document.documentElement.setAttribute('data-theme','dark');}</script>
</head>
<body>

<div class="sis-login">
  <div class="sis-login__card">
    <img class="sis-login__logo" src="{{ asset('assets/img/logo-dark.png') }}" alt="IUASR">
    <h1>Noodtoegang</h1>
    <p class="sub">Uitsluitend voor beheerders, voor als Microsoft Entra ID onbereikbaar is.</p>

    <form method="POST" action="{{ route('noodlogin.store') }}" style="text-align:left;">
      @csrf

      <div class="sis-fld">
        <label for="gebruikersnaam">Gebruikersnaam (e-mailadres)</label>
        <input id="gebruikersnaam" name="gebruikersnaam" type="email" required autofocus
               autocomplete="username" value="{{ old('gebruikersnaam') }}">
      </div>

      <div class="sis-fld">
        <label for="wachtwoord">Wachtwoord</label>
        <input id="wachtwoord" name="wachtwoord" type="password" required autocomplete="current-password">
      </div>

      {{-- Altijd dezelfde melding: zo is niet te achterhalen welk adres een
           noodaccount is. De echte reden staat alleen in het audit-logboek. --}}
      @error('gebruikersnaam')
        <p style="color:var(--secColor100);font-size:13px;margin:0 0 12px;">{{ $message }}</p>
      @enderror
      @error('wachtwoord')
        <p style="color:var(--secColor100);font-size:13px;margin:0 0 12px;">{{ $message }}</p>
      @enderror

      <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--primary" style="width:100%;">Inloggen</button>
    </form>

    <div class="sis-login__note" style="margin-top:18px;">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      <span>Elke inlogpoging wordt vastgelegd in het audit-logboek — geslaagd en mislukt. Gebruik deze weg alleen als Entra ID niet werkt.</span>
    </div>

    <div style="margin-top:14px;text-align:center;">
      <a href="{{ route('login') }}" style="font-size:13px;">Terug naar het gewone inloggen</a>
    </div>

    <div class="sis-login__foot">Islamic University of Applied Sciences Rotterdam · versie {{ config('sis.versie') }}</div>
  </div>
</div>

</body>
</html>
