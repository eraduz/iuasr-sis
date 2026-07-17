@php
    use App\Models\Melding;

    // Systeemmeldingen van de Beheerder: staan op ELKE pagina van elke module.
    // Zichtbaarheid volgt uit het venster van/tot — geen status-kolom, dus een
    // melding verdwijnt vanzelf op de seconde, zonder achtergrondtaak.
    $meldingen = auth()->check()
        ? Melding::query()
            ->lopend()
            ->voorRollen(auth()->user()->rolSleutels())
            ->orderByDesc('niveau')
            ->orderByDesc('van')
            ->get()
        : collect();
@endphp

@if ($meldingen->isNotEmpty())
  <div class="sis-meldingen">
    @foreach ($meldingen as $m)
      <div class="iuasr-dash-alert {{ $m->niveau->alertKlasse() }} sis-melding"
           data-sleutel="{{ $m->sluitSleutel() }}"
           role="{{ $m->niveau === App\Enums\Meldingniveau::Urgent ? 'alert' : 'status' }}">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        <span class="sis-melding__tekst">
          <b>{{ $m->titel }}</b>
          {{ $m->tekst }}
        </span>
        @if ($m->afsluitbaar)
          <button type="button" class="sis-melding__sluit" aria-label="Melding sluiten" title="Sluiten">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        @endif
      </div>
    @endforeach
  </div>

  <script>
  (function () {
    // Wegklikken wordt in de BROWSER onthouden, niet in de database: het is een
    // voorkeur van het moment, geen gegeven dat bewaard hoeft te blijven, en zo
    // kost het geen schrijfactie op elke paginalading. De sleutel bevat het
    // wijzigingsmoment, dus een gecorrigeerde melding komt weer terug.
    var blokken = document.querySelectorAll('.sis-melding[data-sleutel]');
    Array.prototype.forEach.call(blokken, function (el) {
      var sleutel = el.dataset.sleutel;
      try {
        if (localStorage.getItem(sleutel) === '1') { el.remove(); return; }
      } catch (e) { /* geen localStorage: melding blijft gewoon staan */ }

      var knop = el.querySelector('.sis-melding__sluit');
      if (!knop) return;
      knop.addEventListener('click', function () {
        try { localStorage.setItem(sleutel, '1'); } catch (e) {}
        el.remove();
      });
    });
  })();
  </script>
@endif
