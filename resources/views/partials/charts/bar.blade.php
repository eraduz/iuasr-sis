{{--
  Horizontale staafgrafiek. Verwacht:
  $data   : array van ['label' => string, 'value' => number, 'kleur' => ?css]
  $kleur   : (optioneel) standaardkleur voor de balken
  $eenheid : (optioneel) achtervoegsel achter de waarde (bv. '%')
  $leeg    : (optioneel) tekst als er geen data is
--}}
@php
  $kleur = $kleur ?? 'var(--priColor200)';
  $max = collect($data)->max('value');
  $max = $max > 0 ? $max : 1;
@endphp
@if (empty($data))
  <p class="sis-bars--empty">{{ $leeg ?? 'Geen gegevens.' }}</p>
@else
  <div class="sis-bars">
    @foreach ($data as $d)
      <div class="sis-bar-row">
        <span class="sis-bar-lbl" title="{{ $d['label'] }}">{{ $d['label'] }}</span>
        <span class="sis-bar-track"><span class="sis-bar-fill" style="width:{{ round(($d['value'] / $max) * 100) }}%;background:{{ $d['kleur'] ?? $kleur }};"></span></span>
        <span class="sis-bar-val">{{ $d['value'] }}{{ $eenheid ?? '' }}</span>
      </div>
    @endforeach
  </div>
@endif
