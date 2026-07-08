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
        <span class="sis-spark-lbl">{{ $d['label'] }}</span>
      </div>
    @endforeach
  </div>
@endif
