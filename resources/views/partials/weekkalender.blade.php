@php
    use Carbon\Carbon;

    // Read-only overzichtskalender met ISO-weeknummers: de huidige week plus vijf
    // weken vooruit, zodat medewerkers de data en weeknummers van de komende weken
    // in één oogopslag zien. Server-side gerenderd (accuraat op de laadmoment).
    $vandaag = Carbon::today();
    $weekStart = $vandaag->copy()->startOfWeek(Carbon::MONDAY);
    $maanden = ['', 'jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];

    $rijen = [];
    for ($w = 0; $w < 6; $w++) {
        $rowStart = $weekStart->copy()->addWeeks($w);
        $dagen = [];
        for ($d = 0; $d < 7; $d++) {
            $dagen[] = $rowStart->copy()->addDays($d);
        }
        $rijen[] = ['wk' => $rowStart->isoWeek, 'dagen' => $dagen];
    }
    $laatste = $weekStart->copy()->addWeeks(5)->addDays(6);
    $bereik = $maanden[$weekStart->month].' '.$weekStart->year
        .' – '.$maanden[$laatste->month].' '.$laatste->year;
@endphp

<div class="sis-weekcal" role="group" aria-label="Weekkalender">
  <div class="sis-weekcal__hd">
    <span class="ttl">Weekkalender</span>
    <span class="rng">{{ $bereik }}</span>
  </div>
  <table class="sis-weekcal__tbl">
    <thead>
      <tr>
        <th class="wk" title="Weeknummer">Wk</th>
        @foreach (['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'] as $dag)
          <th>{{ $dag }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach ($rijen as $rij)
        <tr @class(['is-nu' => $rij['dagen'][0]->isoWeek === $vandaag->isoWeek && $rij['dagen'][0]->year === $vandaag->year])>
          <td class="wk">{{ $rij['wk'] }}</td>
          @foreach ($rij['dagen'] as $dag)
            <td @class([
                  'weekend' => $dag->isWeekend(),
                  'vandaag' => $dag->isSameDay($vandaag),
                  'eerste' => $dag->day === 1,
                ])
                title="{{ $dag->day }} {{ $maanden[$dag->month] }} {{ $dag->year }}">
              {{ $dag->day === 1 ? $maanden[$dag->month] : $dag->day }}
            </td>
          @endforeach
        </tr>
      @endforeach
    </tbody>
  </table>
  <div class="sis-weekcal__ft">Alleen ter informatie · vandaag is week {{ $vandaag->isoWeek }}</div>
</div>
