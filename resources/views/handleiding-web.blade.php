<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Handleiding — IUASR Management Systeem</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Fira+Sans:wght@200;300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/sis.css') }}?v={{ filemtime(public_path('assets/css/sis.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/iuasr-plugin-dash.css') }}?v={{ filemtime(public_path('assets/css/iuasr-plugin-dash.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/sis-theme.css') }}?v={{ filemtime(public_path('assets/css/sis-theme.css')) }}">
{{-- Donker is de standaard (energiezuinig); een bewuste 'licht'-keuze blijft behouden. --}}
<script>try{var t=localStorage.getItem('sis-theme');document.documentElement.setAttribute('data-theme',t==='light'?'light':'dark');}catch(e){document.documentElement.setAttribute('data-theme','dark');}</script>
<style>
  body { margin:0; font-family:'Fira Sans',sans-serif; background:#f6f5f9; color:var(--priColor100); }
  .hl-top { position:sticky; top:0; z-index:20; display:flex; align-items:center; justify-content:space-between; gap:12px;
    padding:12px 20px; background:#fff; border-bottom:1px solid var(--borderColor); }
  .hl-top__brand { display:flex; align-items:center; gap:10px; font-family:var(--serif-font); }
  .hl-top__brand img { height:28px; }
  .hl-layout { display:flex; gap:26px; max-width:1180px; margin:0 auto; padding:22px 20px 64px; align-items:flex-start; }
  .hl-toc { position:sticky; top:66px; flex:0 0 250px; max-height:calc(100vh - 88px); overflow:auto;
    border:1px solid var(--borderColor); border-radius:12px; padding:10px; background:#fff; }
  .hl-toc__hd { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:var(--blackAltText); margin:4px 8px 8px; }
  .hl-toc a { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:7px 10px; margin-bottom:1px;
    border-radius:8px; color:var(--priColor100); text-decoration:none; font-size:13px; line-height:1.3; }
  .hl-toc a:hover { background:var(--priColor102); }
  .hl-toc a .badge { flex:none; font-size:9px; text-transform:uppercase; letter-spacing:.04em; font-weight:700;
    color:#fff; background:var(--secColor100); padding:2px 6px; border-radius:999px; }
  .hl-content { flex:1; min-width:0; max-width:810px; }
  .hl-content h2 { font-family:var(--serif-font); font-size:23px; color:var(--secColor100); margin:28px 0 8px;
    padding-bottom:5px; border-bottom:1px solid var(--borderSubtleColor); scroll-margin-top:70px; }
  .hl-content h2:first-child { margin-top:2px; }
  .hl-content h3 { font-size:16px; margin:18px 0 6px; }
  .hl-content p { margin:0 0 10px; line-height:1.62; }
  .hl-content ul, .hl-content ol { margin:0 0 12px; padding-left:20px; }
  .hl-content li { margin:0 0 5px; line-height:1.55; }
  .hl-content b { font-weight:700; }
  .hl-content .tip { background:#f5f3fa; border-left:3px solid #1E1446; padding:10px 14px; margin:12px 0; border-radius:0 8px 8px 0; }
  .hl-content .let { background:#fbefef; border-left:3px solid #C8102E; padding:10px 14px; margin:12px 0; border-radius:0 8px 8px 0; }
  .hl-content table.rol { width:100%; border-collapse:collapse; font-size:13px; margin:10px 0 14px; }
  .hl-content table.rol th { background:#f2f0f8; text-align:left; padding:7px 9px; }
  .hl-content table.rol td { border-bottom:1px solid var(--borderSubtleColor); padding:7px 9px; vertical-align:top; }
  .hl-content code { background:rgba(30,20,70,.06); padding:1px 5px; border-radius:5px; font-size:.92em; }

  /* Donkere modus. */
  html[data-theme="dark"] body { background:#14121e; }
  html[data-theme="dark"] .hl-top { background:#1a1728; }
  html[data-theme="dark"] .hl-toc { background:#1f1b30; }
  html[data-theme="dark"] .hl-top__brand img { filter:brightness(0) invert(1); opacity:.92; }
  html[data-theme="dark"] .hl-content .tip { background:rgba(255,255,255,.04); border-left-color:var(--accent); }
  html[data-theme="dark"] .hl-content .let { background:rgba(255,93,116,.10); }
  html[data-theme="dark"] .hl-content table.rol th { background:rgba(255,255,255,.05); }
  html[data-theme="dark"] .hl-content code { background:rgba(255,255,255,.08); }

  @media (max-width:820px) {
    .hl-layout { flex-direction:column; }
    .hl-toc { position:static; flex:none; width:100%; max-height:none; }
  }
</style>
</head>
<body>
  <div class="hl-top">
    <div class="hl-top__brand"><img src="{{ asset('assets/img/logo-dark.png') }}" alt="IUASR"><span>Handleiding</span></div>
    <div style="display:flex; gap:8px; align-items:center;">
      <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('handleiding.medewerkers') }}" target="_blank" rel="noopener">PDF downloaden</a>
      <a class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" href="{{ route('dashboard') }}">Naar het systeem</a>
    </div>
  </div>

  <div class="hl-layout">
    <nav class="hl-toc" aria-label="Hoofdstukken">
      <div class="hl-toc__hd">Hoofdstukken</div>
      @foreach ($hoofdstukken as $h)
        @php $voorU = ! empty(array_intersect($h['rollen'], $mijnRollen)); @endphp
        <a href="#h{{ $h['nr'] }}">
          <span>{{ $h['nr'] }}. {{ $h['titel'] }}</span>
          @if ($voorU)<span class="badge">voor u</span>@endif
        </a>
      @endforeach
    </nav>

    <article class="hl-content">
      @include('partials.handleiding-inhoud')
    </article>
  </div>
</body>
</html>
