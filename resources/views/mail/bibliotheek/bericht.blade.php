<!DOCTYPE html>
<html lang="nl">
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, Helvetica, sans-serif; color:#1E1446; font-size:14px; line-height:1.6;">
  {{-- De tekst komt uit het e-mailsjabloon (beheerd door de Beheerder), met de
       variabelen al ingevuld. nl2br + e() zodat regelovergangen behouden blijven
       en de inhoud nooit als HTML wordt uitgevoerd. --}}
  {!! nl2br(e($tekst)) !!}
</body>
</html>
