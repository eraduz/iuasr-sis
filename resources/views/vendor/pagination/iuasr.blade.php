{{-- Paginatie in de stijl van het IUASR design system (iuasr-dash-pagination).
     Vervangt de standaard Tailwind-paginatie, waarvan de SVG-pijlen zonder
     Tailwind-CSS veel te groot werden. Compacte ‹ › knoppen + bereikteller. --}}
@if ($paginator->hasPages())
  <div class="iuasr-dash-pagination">
    @if ($paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
      <div class="iuasr-dash-pagination__range">Toont <b>{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</b> van <b>{{ $paginator->total() }}</b></div>
    @else
      <div class="iuasr-dash-pagination__range">Pagina <b>{{ $paginator->currentPage() }}</b></div>
    @endif
    <div class="iuasr-dash-pagination__nav">
      <a href="{{ $paginator->previousPageUrl() ?: '#' }}" rel="prev" aria-label="Vorige"><button type="button" {{ $paginator->onFirstPage() ? 'disabled' : '' }}>‹</button></a>
      <button type="button" class="is-current">{{ $paginator->currentPage() }}</button>
      @if ($paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
        <span style="color:var(--blackAltText);font-size:12px;">van {{ $paginator->lastPage() }}</span>
      @endif
      <a href="{{ $paginator->nextPageUrl() ?: '#' }}" rel="next" aria-label="Volgende"><button type="button" {{ $paginator->hasMorePages() ? '' : 'disabled' }}>›</button></a>
    </div>
  </div>
@endif
