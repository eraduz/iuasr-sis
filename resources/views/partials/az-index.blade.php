{{--
  A–Z-index en keuze voor het aantal per pagina. Voor lange, op titel gesorteerde
  lijsten (de catalogus). Elke letter is een link die op de beginletter filtert;
  '#' vangt titels die niet met een Latijnse letter beginnen (cijfers, Arabisch).

  Verwacht:
    $route        : de routenaam van de lijst (bv. 'catalogus')
    $letterFilter : de actieve letter (of '' voor alles)
    $perPagina    : het huidige aantal per pagina
  Behoudt de overige filters via de bestaande query-parameters.
--}}
@php
  $behoud = request()->except(['letter', 'page']);
  $letters = array_merge(range('A', 'Z'), ['#']);
@endphp

<div class="sis-az" style="display:flex; flex-wrap:wrap; gap:4px; align-items:center; margin-bottom:12px;">
  <a href="{{ route($route, array_merge($behoud, ['letter' => null])) }}"
     class="iuasr-dash-btn iuasr-dash-btn--sm {{ $letterFilter === '' ? 'iuasr-dash-btn--primary' : '' }}">Alle</a>

  @foreach ($letters as $letter)
    <a href="{{ route($route, array_merge($behoud, ['letter' => $letter])) }}"
       class="iuasr-dash-btn iuasr-dash-btn--sm {{ $letterFilter === $letter ? 'iuasr-dash-btn--primary' : '' }}"
       style="min-width:30px; text-align:center; padding-left:6px; padding-right:6px;">{{ $letter }}</a>
  @endforeach

  <span style="flex:1;"></span>

  <form method="GET" action="{{ route($route) }}" style="display:flex; align-items:center; gap:6px;">
    @foreach (request()->except(['per', 'page']) as $sleutel => $waarde)
      @if (is_array($waarde))
        @foreach ($waarde as $item)<input type="hidden" name="{{ $sleutel }}[]" value="{{ $item }}">@endforeach
      @else
        <input type="hidden" name="{{ $sleutel }}" value="{{ $waarde }}">
      @endif
    @endforeach
    <label class="sis-muted" style="font-size:12px;">Per pagina</label>
    <select name="per" onchange="this.form.submit()">
      @foreach (\App\Support\Paginakeuze::OPTIES as $optie)
        <option value="{{ $optie }}" @selected($perPagina === $optie)>{{ $optie }}</option>
      @endforeach
    </select>
  </form>
</div>
