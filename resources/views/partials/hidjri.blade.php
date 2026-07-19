{{--
  De islamitische (hidjri) datum onder de zijbalk-quote, met de gewone datum
  eronder. Informatief: welke variant het systeem aanhoudt staat in
  `config/sis.php` ('hidjri.variant'), met een verschuiving in dagen voor het
  geval IUASR de plaatselijke maansobservatie volgt.
--}}
<div class="sis-hidjri" title="Islamitische datum ({{ $hidjri['tekst'] }})">
  <div class="sis-hidjri__ar" dir="rtl" lang="ar">{{ $hidjri['arabisch'] }}</div>
  <div class="sis-hidjri__nl">{{ $hidjri['tekst'] }}</div>
  <div class="sis-hidjri__greg">{{ \Carbon\CarbonImmutable::now(config('app.timezone'))->translatedFormat('l j F Y') }}</div>
</div>
