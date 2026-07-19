@php
    use App\Support\Hidjrikalender;
    use App\Support\Quoteroulatie;

    $quote = Quoteroulatie::huidige();
    $hidjri = config('sis.hidjri.tonen') ? Hidjrikalender::vandaag() : null;
@endphp

@if ($quote)
  {{-- Bemoediging bovenaan het menu: een Schone Naam of een eigen spreuk, die
       om de paar minuten wisselt. Welke er staat volgt uit de klok, dus alle
       collega's zien op hetzelfde moment dezelfde tekst. --}}
  <div class="sis-quote" id="sis-quote"
       data-url="{{ route('quotes.huidig') }}"
       data-over="{{ Quoteroulatie::secondenTotVolgende() }}"
       aria-live="polite">
    <div class="sis-quote__inner">
      @if ($quote->heeftAfbeelding())
        <img class="sis-quote__img" src="{{ route('quotes.afbeelding', $quote) }}"
             alt="{{ $quote->kop() ?? $quote->betekenis }}" width="152" height="152">
      @elseif ($quote->arabisch)
        <div class="sis-quote__ar" dir="rtl" lang="ar">{{ $quote->arabisch }}</div>
      @endif

      @if ($quote->kop())
        <div class="sis-quote__kop">{{ $quote->kop() }}</div>
      @endif
      <div class="sis-quote__nl">{{ $quote->betekenis }}</div>
      @if ($quote->bron)
        <div class="sis-quote__bron">{{ $quote->bron }}</div>
      @endif
    </div>

    {{-- De islamitische datum staat BINNEN de quotetegel maar BUITEN de
         __inner-laag: die wordt bij elke wisseling herschreven, en de datum
         hoort niet elke vijf minuten weg te knipperen. --}}
    @includeWhen($hidjri, 'partials.hidjri', ['hidjri' => $hidjri])
  </div>

  <script>
  (function () {
    var el = document.getElementById('sis-quote');
    if (!el || !window.fetch) return; // Zonder JS blijft de quote van de paginalading staan.

    var inner = el.querySelector('.sis-quote__inner');

    function tekst(k, v, extra) {
      if (!v) return '';
      var d = document.createElement('div');
      d.className = k;
      d.textContent = v;
      if (extra) { d.setAttribute('dir', 'rtl'); d.setAttribute('lang', 'ar'); }
      return d.outerHTML;
    }

    function toon(q) {
      var beeld = '';
      if (q.afbeelding) {
        var img = document.createElement('img');
        img.className = 'sis-quote__img';
        img.src = q.afbeelding;
        img.alt = q.kop || q.betekenis;
        img.width = 152; img.height = 152;
        beeld = img.outerHTML;
      } else if (q.arabisch) {
        beeld = tekst('sis-quote__ar', q.arabisch, true);
      }
      inner.innerHTML = beeld
        + tekst('sis-quote__kop', q.kop)
        + tekst('sis-quote__nl', q.betekenis)
        + tekst('sis-quote__bron', q.bron);
      inner.classList.remove('is-in');
      void inner.offsetWidth; // forceer herstart van de fade
      inner.classList.add('is-in');
    }

    // Precies op de wisselgrens ophalen in plaats van blind pollen: dat is één
    // verzoek per tijdvak. De seconde extra voorkomt dat we er net vóór zitten
    // en het oude tijdvak terugkrijgen.
    function plan(over) {
      setTimeout(function () {
        fetch(el.dataset.url, { headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.ok ? r.json() : null; })
          .then(function (d) {
            if (d && d.quote) toon(d.quote);
            plan(d && d.volgende_over ? d.volgende_over : 300);
          })
          .catch(function () { plan(300); }); // Netwerk weg? Stil opnieuw proberen.
      }, (Math.max(1, over) + 1) * 1000);
    }

    plan(parseInt(el.dataset.over, 10) || 300);
  })();
  </script>
@elseif ($hidjri)
  {{-- Geen quote ingesteld? Dan staat de datum er alleen, met dezelfde rand. --}}
  <div class="sis-quote">
    @include('partials.hidjri', ['hidjri' => $hidjri])
  </div>
@endif
