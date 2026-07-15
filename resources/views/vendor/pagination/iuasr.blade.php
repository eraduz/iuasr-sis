{{-- Paginatie in de stijl van het IUASR design system (iuasr-dash-pagination).
     Toont paginanummers met een venster rond de huidige pagina, eerste/laatste,
     en bij veel pagina's een sprongveld — zodat een lijst van honderden pagina's
     toch te navigeren is. --}}
@if ($paginator->hasPages())
  @php
    $isVolledig = $paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $huidige = $paginator->currentPage();
    $laatste = $isVolledig ? $paginator->lastPage() : null;
    // Een venster van twee pagina's aan weerszijden van de huidige.
    $vanaf = max(1, $huidige - 2);
    $tot = $isVolledig ? min($laatste, $huidige + 2) : $huidige;
  @endphp

  <div class="iuasr-dash-pagination">
    @if ($isVolledig)
      <div class="iuasr-dash-pagination__range">Toont <b>{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</b> van <b>{{ number_format($paginator->total(), 0, ',', '.') }}</b></div>
    @else
      <div class="iuasr-dash-pagination__range">Pagina <b>{{ $huidige }}</b></div>
    @endif

    <div class="iuasr-dash-pagination__nav">
      {{-- Eerste + vorige --}}
      @if ($isVolledig)
        <a href="{{ $paginator->url(1) }}" aria-label="Eerste pagina"><button type="button" {{ $paginator->onFirstPage() ? 'disabled' : '' }}>«</button></a>
      @endif
      <a href="{{ $paginator->previousPageUrl() ?: '#' }}" rel="prev" aria-label="Vorige"><button type="button" {{ $paginator->onFirstPage() ? 'disabled' : '' }}>‹</button></a>

      @if ($isVolledig)
        {{-- Sprong naar het begin --}}
        @if ($vanaf > 1)
          <a href="{{ $paginator->url(1) }}"><button type="button">1</button></a>
          @if ($vanaf > 2)<span style="color:var(--blackAltText);">…</span>@endif
        @endif

        {{-- Het venster met paginanummers --}}
        @for ($p = $vanaf; $p <= $tot; $p++)
          @if ($p === $huidige)
            <button type="button" class="is-current">{{ $p }}</button>
          @else
            <a href="{{ $paginator->url($p) }}"><button type="button">{{ $p }}</button></a>
          @endif
        @endfor

        {{-- Sprong naar het einde --}}
        @if ($tot < $laatste)
          @if ($tot < $laatste - 1)<span style="color:var(--blackAltText);">…</span>@endif
          <a href="{{ $paginator->url($laatste) }}"><button type="button">{{ $laatste }}</button></a>
        @endif
      @else
        <button type="button" class="is-current">{{ $huidige }}</button>
      @endif

      {{-- Volgende + laatste --}}
      <a href="{{ $paginator->nextPageUrl() ?: '#' }}" rel="next" aria-label="Volgende"><button type="button" {{ $paginator->hasMorePages() ? '' : 'disabled' }}>›</button></a>
      @if ($isVolledig)
        <a href="{{ $paginator->url($laatste) }}" aria-label="Laatste pagina"><button type="button" {{ $huidige >= $laatste ? 'disabled' : '' }}>»</button></a>
      @endif
    </div>

    {{-- Sprongveld: alleen tonen als er echt veel pagina's zijn. --}}
    @if ($isVolledig && $laatste > 10)
      <form method="GET" action="" class="iuasr-dash-pagination__jump" style="display:flex; align-items:center; gap:6px;">
        @foreach (request()->except('page') as $sleutel => $waarde)
          @if (is_array($waarde))
            @foreach ($waarde as $item)<input type="hidden" name="{{ $sleutel }}[]" value="{{ $item }}">@endforeach
          @else
            <input type="hidden" name="{{ $sleutel }}" value="{{ $waarde }}">
          @endif
        @endforeach
        <label style="color:var(--blackAltText); font-size:12px;">Naar pagina</label>
        <input type="number" name="page" min="1" max="{{ $laatste }}" value="{{ $huidige }}" style="width:70px;" aria-label="Ga naar pagina">
        <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--sm">Ga</button>
      </form>
    @endif
  </div>
@endif
