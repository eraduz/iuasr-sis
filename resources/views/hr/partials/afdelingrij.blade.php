{{-- Recursieve rij voor de afdelingenboom. Verwacht: $afdeling, $diepte, $perOuder. --}}
<tr>
  <td class="nm">
    @for ($i = 0; $i < $diepte; $i++)<span style="display:inline-block; width:18px;"></span>@endfor
    @if ($diepte > 0)<span class="sis-muted">↳ </span>@endif
    <b>{{ $afdeling->naam }}</b> <span class="sis-muted">({{ $afdeling->code }})</span>
  </td>
  <td>{{ $afdeling->manager?->volledigeNaam() ?? '—' }}</td>
  <td class="tnum" style="text-align:right;">{{ $afdeling->medewerkers_actief }}</td>
</tr>
@foreach (($perOuder->get($afdeling->id) ?? collect())->sortBy('naam') as $kind)
  @include('hr.partials.afdelingrij', ['afdeling' => $kind, 'diepte' => $diepte + 1, 'perOuder' => $perOuder])
@endforeach
