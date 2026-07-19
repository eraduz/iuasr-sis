{{--
  Verticale staafjes voor een trend (bv. instroom of cijferverdeling). Verwacht:
  $data    : array van ['label' => string, 'value' => number]
  $kleur    : (optioneel) balkkleur
  $leeg     : (optioneel) tekst als er geen data is
--}}
@php
  $kleur = $kleur ?? 'var(--priColor200)';
  $max = collect($data)->max('value');
  $max = $max > 0 ? $max : 1;
@endphp
@if (empty($data))
  <p class="sis-bars--empty">{{ $leeg ?? 'Geen gegevens.' }}</p>
@else
  <div class="sis-spark">
    @foreach ($data as $d)
      <div class="sis-spark-col">
        <span class="sis-spark-val">{{ $d['value'] }}</span>
        <div class="sis-spark-bar" style="height:{{ max(3, round(($d['value'] / $max) * 78)) }}px;background:{{ $d['kleur'] ?? $kleur }};"></div>
        {{-- Het woord 'Studiejaar' staat al in de kop van de kaart; achttien keer
             herhalen onder de staafjes maakt de grafiek alleen maar breder. Het
             jaartal komt op twee regels ("2009" / "2010"), zodat een kolom half
             zo breed hoeft te zijn. De gegevens zelf blijven ongewijzigd. --}}
        @php
          $label = preg_replace('/^\s*Studiejaar\s+/iu', '', (string) $d['label']);
          // De perioden zijn in de loop der jaren niet eenduidig benoemd:
          // "2009-2010" naast "2024 / 2025". Beide scheidingstekens afvangen,
          // anders staat een deel van de labels op één regel en de rest op twee.
          $delen = preg_split('/\s*[-–\/]\s*/u', $label, 2);
        @endphp
        <span class="sis-spark-lbl">
          @if (count($delen) === 2)
            {{ $delen[0] }}<span class="sis-spark-lbl__2e">{{ $delen[1] }}</span>
          @else
            {{ $label }}
          @endif
        </span>
      </div>
    @endforeach
  </div>
@endif
