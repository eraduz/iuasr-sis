{{--
  Zelf-filterende filterbalk. Zet `data-autofilter` op een <form method="GET">:
  een keuzelijst, een datum of een vinkje verzenden het formulier meteen bij het
  wijzigen. Vrije tekstvelden doen dat NIET — daar typt de gebruiker in, en die
  verzendt met Enter (of met de knop Filteren).

  De knop Filteren blijft bestaan: zonder JavaScript werkt de balk gewoon door.
--}}
@once
  @push('scripts')
    <script>
    (function () {
      document.querySelectorAll('form[data-autofilter]').forEach(function (formulier) {
        formulier.querySelectorAll('select, input[type="date"], input[type="checkbox"], input[type="radio"]').forEach(function (veld) {
          veld.addEventListener('change', function () {
            formulier.submit();
          });
        });
      });
    })();
    </script>
  @endpush
@endonce
