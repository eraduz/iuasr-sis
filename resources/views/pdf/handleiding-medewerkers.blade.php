<!DOCTYPE html>
<html lang="nl">
@php
  $logoPad = public_path('assets/img/iuasr-logo.png');
  $logo = is_file($logoPad) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPad)) : null;
@endphp
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 42px 46px 64px 46px; }
    body { font-family: "DejaVu Sans", sans-serif; color: #1E1446; font-size: 10.5pt; line-height: 1.5; }
    #footer { position: fixed; bottom: -44px; left: 0; right: 0; height: 30px; border-top: 1px solid #ddd; padding-top: 6px; font-size: 8pt; color: #666; }
    #footer .r { text-align: right; }
    #footer .num:after { content: counter(page); }
    .cover { border-bottom: 3px solid #1E1446; padding-bottom: 14px; margin-bottom: 20px; }
    .cover img { height: 96px; }
    .cover h1 { font-size: 24pt; font-weight: bold; margin: 14px 0 2px; }
    .cover .sub { font-size: 11pt; color: #666; margin: 0; }
    h2 { font-size: 14pt; color: #C8102E; margin: 20px 0 6px; border-bottom: 1px solid #eee; padding-bottom: 3px; page-break-after: avoid; }
    h3 { font-size: 11.5pt; margin: 14px 0 4px; page-break-after: avoid; }
    p { margin: 0 0 9px; }
    ul, ol { margin: 0 0 10px; padding-left: 20px; }
    li { margin: 0 0 4px; }
    .tip { background: #F5F3FA; border-left: 3px solid #1E1446; padding: 8px 12px; margin: 10px 0; font-size: 10pt; }
    .let { background: #FBEFEF; border-left: 3px solid #C8102E; padding: 8px 12px; margin: 10px 0; font-size: 10pt; }
    table.rol { width: 100%; border-collapse: collapse; font-size: 9.5pt; margin: 8px 0 12px; }
    table.rol th { background: #1E1446; color: #fff; text-align: left; padding: 6px 8px; font-size: 8.5pt; }
    table.rol td { border-bottom: 1px solid #eee; padding: 6px 8px; vertical-align: top; }
    b { color: #1E1446; }
  </style>
</head>
<body>
  <div id="footer">
    <table style="width:100%;"><tr>
      <td>IUASR Management Systeem — Handleiding voor medewerkers · {{ now()->format('d-m-Y') }}</td>
      <td class="r">Pagina <span class="num"></span></td>
    </tr></table>
  </div>

  <div class="cover">
    @if ($logo)<img src="{{ $logo }}" alt="IUASR">@endif
    <h1>Handleiding voor medewerkers</h1>
    <p class="sub">Intern managementsysteem · Islamic University of Applied Sciences Rotterdam</p>
  </div>

  @include('partials.handleiding-inhoud')

</body>
</html>
