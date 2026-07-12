<!DOCTYPE html>
<html lang="nl">
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, Helvetica, sans-serif; color:#1E1446; font-size:14px; line-height:1.6;">
  <p>Beste Personeelszaken,</p>

  <p>Er is via de self-service een nieuwe <strong>verlofaanvraag</strong> binnengekomen:</p>

  <table style="border-collapse:collapse; font-size:14px;">
    <tr><td style="padding:2px 12px 2px 0; color:#666;">Medewerker</td><td><strong>{{ $medewerkerNaam }}</strong></td></tr>
    <tr><td style="padding:2px 12px 2px 0; color:#666;">Soort verlof</td><td>{{ $verloftypeLabel }}</td></tr>
    <tr><td style="padding:2px 12px 2px 0; color:#666;">Periode</td><td>{{ $van }} t/m {{ $tot }}</td></tr>
    <tr><td style="padding:2px 12px 2px 0; color:#666;">Aantal uren</td><td>{{ rtrim(rtrim(number_format($uren, 1, ',', '.'), '0'), ',') }}</td></tr>
    @if ($reden)<tr><td style="padding:2px 12px 2px 0; color:#666;">Toelichting</td><td>{{ $reden }}</td></tr>@endif
  </table>

  <p>Log in op het SIS om de aanvraag te beoordelen (Personeelszaken &rarr; Verlof).</p>

  <p>Met vriendelijke groet,<br>
    IUASR — HR / Personeelszaken (automatisch bericht)</p>

  <hr style="border:none;border-top:1px solid #ddd;margin:18px 0;">
  <p style="font-size:11px;color:#666;">Deze e-mail is automatisch verzonden door het studentbeheersysteem.</p>
</body>
</html>
