{{--
  Donut-/cirkeldiagram (SVG). Verwacht:
  $segments    : array van ['label' => string, 'value' => number, 'kleur' => css]
  $midden      : (optioneel) grote tekst in het midden (default: totaal)
  $middenLabel : (optioneel) klein label onder het middengetal
  $leeg        : (optioneel) tekst als alles 0 is
--}}
@php
  $totaal = collect($segments)->sum('value');
  $straal = 42;
  $omtrek = 2 * M_PI * $straal;
  $offset = 0;
@endphp
@if ($totaal <= 0)
  <p class="sis-bars--empty">{{ $leeg ?? 'Geen gegevens.' }}</p>
@else
  <div class="sis-donut-wrap">
    <svg class="sis-donut" viewBox="0 0 100 100" role="img" aria-label="Cirkeldiagram">
      <circle cx="50" cy="50" r="{{ $straal }}" fill="none" stroke="var(--priColor102)" stroke-width="12"/>
      @foreach ($segments as $s)
        @php
          $len = ($s['value'] / $totaal) * $omtrek;
          $dash = round($len, 2).' '.round($omtrek - $len, 2);
        @endphp
        @if ($s['value'] > 0)
          <circle cx="50" cy="50" r="{{ $straal }}" fill="none" stroke="{{ $s['kleur'] }}" stroke-width="12"
            stroke-dasharray="{{ $dash }}" stroke-dashoffset="{{ round(-$offset, 2) }}"
            transform="rotate(-90 50 50)"/>
          @php $offset += $len; @endphp
        @endif
      @endforeach
      <text x="50" y="50" text-anchor="middle" dominant-baseline="middle" class="sis-donut-num">{{ $midden ?? $totaal }}</text>
      @isset($middenLabel)<text x="50" y="63" text-anchor="middle" class="sis-donut-lbl">{{ $middenLabel }}</text>@endisset
    </svg>
    <ul class="sis-donut-legend">
      @foreach ($segments as $s)
        <li><span class="dot" style="background:{{ $s['kleur'] }};"></span>{{ $s['label'] }} <b>{{ $s['value'] }}</b></li>
      @endforeach
    </ul>
  </div>
@endif
