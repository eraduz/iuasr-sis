@extends('layouts.app')

@section('titel', 'Dubbele tijdschriften samenvoegen')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('bibliotheek.dashboard') }}">Bibliotheek</a><span class="sep">›</span><b>Dubbele tijdschriften</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Dubbele tijdschriften samenvoegen</h1>
    <div class="summary">Voorstellen op naamgelijkenis. Er wordt niets samengevoegd tot u het aanvinkt.</div>
  </div>
</div>

<div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-bottom:16px;">
  <span>
    Dezelfde tijdschriftnaam komt uit <b>twee bronnen</b>. Uit de <b>boekenlijst</b> komen {{ $aantalZonderUitgaven }} plankregels:
    die hebben een exemplaar en een rekcode, maar geen uitgaven. Uit de <b>tijdschriftinhoud</b> komen {{ $aantalMetUitgaven }} echte
    tijdschriften mét uitgaven en artikelen, maar zonder exemplaar. Samenvoegen brengt ze bij elkaar: de exemplaren en de rekcode
    verhuizen naar het tijdschrift met de uitgaven, en <b>er gaat niets verloren</b>.
  </span>
</div>

<form method="GET" action="{{ route('bibliotheek.samenvoegen') }}" class="sis-toolbar" style="margin-bottom:12px;" data-autofilter>
  <label class="sis-check-inline" style="margin-right:8px;">Gelijkenis vanaf</label>
  <select name="drempel">
    @foreach (['0.95' => '95% — vrijwel identiek', '0.90' => '90%', '0.85' => '85%', '0.80' => '80% (aanbevolen)', '0.70' => '70% — ruimer, meer ruis'] as $waarde => $label)
      <option value="{{ $waarde }}" @selected(abs($drempel - (float) $waarde) < 0.001)>{{ $label }}</option>
    @endforeach
  </select>
  <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Tonen</button>
</form>

@if (count($voorstellen) === 0)
  <div class="iuasr-dash-empty"><h3>Geen voorstellen</h3><p class="sis-muted">Er zijn geen plankregels gevonden die genoeg lijken op een tijdschrift met uitgaven. Verlaag eventueel de drempel.</p></div>
@else
  <form method="POST" action="{{ route('bibliotheek.samenvoegen.uitvoeren') }}">
    @csrf

    <div class="iuasr-dash-tbl-card">
      <table class="iuasr-dash-tbl">
        <thead>
          <tr>
            <th style="width:40px;"><input type="checkbox" id="alles"></th>
            <th>Plankregel (uit de boekenlijst)</th>
            <th style="text-align:center;">Exemplaren</th>
            <th>Wordt opgenomen in (met uitgaven)</th>
            <th style="text-align:center;">Uitgaven</th>
            <th style="text-align:right;">Gelijkenis</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($voorstellen as $v)
            <tr>
              <td><input type="checkbox" name="paren[]" value="{{ $v['bron']->id }}:{{ $v['doel']->id }}" class="keuze" @checked($v['score'] >= 0.95)></td>
              <td class="nm" dir="auto">
                <a href="{{ route('bibliotheek.publicaties.show', $v['bron']) }}">{{ $v['bron']->titel }}</a>
                @if ($v['bron']->rekplaats())<br><small class="sis-muted">rek {{ $v['bron']->rekplaats() }}</small>@endif
              </td>
              <td class="tnum" style="text-align:center;">{{ $v['exemplaren'] }}</td>
              <td class="nm" dir="auto"><a href="{{ route('bibliotheek.publicaties.show', $v['doel']) }}">{{ $v['doel']->titel }}</a></td>
              <td class="tnum" style="text-align:center;">{{ $v['uitgaven'] }}</td>
              <td class="tnum" style="text-align:right;">
                <span class="iuasr-dash-status {{ $v['score'] >= 0.95 ? 's-approved' : ($v['score'] >= 0.85 ? 's-requested' : 's-incomplete') }}">
                  {{ number_format($v['score'] * 100, 0) }}%
                </span>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <p class="sis-tblnote">
      Voorstellen van <b>95% of hoger</b> staan alvast aangevinkt; die zijn vrijwel zeker hetzelfde blad.
      Loop de rest zelf na — een verkeerde samenvoeging plakt uitgaven onder het verkeerde tijdschrift.
    </p>

    <div style="margin-top:12px;">
      <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Aangevinkte voorstellen samenvoegen</button>
      <span class="sis-muted" style="margin-left:10px;">{{ count($voorstellen) }} {{ count($voorstellen) === 1 ? 'voorstel' : 'voorstellen' }}</span>
    </div>
  </form>
@endif
@endsection

@push('scripts')
<script>
(function () {
  var alles = document.getElementById('alles');
  if (!alles) return;
  alles.addEventListener('change', function () {
    document.querySelectorAll('.keuze').forEach(function (v) { v.checked = alles.checked; });
  });
})();
</script>
@endpush

@include('partials.autofilter')
