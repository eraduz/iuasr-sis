<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Inloggen — IUASR SIS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Fira+Sans:wght@200;300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/sis.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/iuasr-plugin-dash.css') }}">
</head>
<body>

<div class="sis-login">
  <div class="sis-login__card">
    <img class="sis-login__logo" src="{{ asset('assets/img/logo-dark.png') }}" alt="IUASR">
    <h1>Studentbeheersysteem</h1>
    <p class="sub">Intern systeem van Studentenzaken. Log in met uw IUASR-account.</p>

    {{-- Authenticatie verloopt via Microsoft Entra ID (SSO/OIDC). Er wordt
         NOOIT een eigen login gebouwd. Deze knop start straks de OIDC-flow. --}}
    <a class="sis-sso-btn" href="#" aria-disabled="true">
      <span class="sis-sso-btn__ic">
        <svg width="15" height="15" viewBox="0 0 23 23" aria-hidden="true"><rect x="1" y="1" width="10" height="10" fill="#F25022"/><rect x="12" y="1" width="10" height="10" fill="#7FBA00"/><rect x="1" y="12" width="10" height="10" fill="#00A4EF"/><rect x="12" y="12" width="10" height="10" fill="#FFB900"/></svg>
      </span>
      Inloggen met Microsoft Entra ID
    </a>

    <div class="sis-login__note">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      <span>Dit systeem draait intern en is IP-beperkt. Uw rol volgt uit uw Entra-groep en bepaalt wat u mag zien en doen.</span>
    </div>

    <div class="sis-login__foot">Islamic University of Applied Sciences Rotterdam</div>
  </div>
</div>

</body>
</html>
