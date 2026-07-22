@extends('layouts.app')

@section('titel', 'Cijfers mailen')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Cijfers mailen</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Cijfers mailen</h1>
    <div class="summary">Einde blok · <b>{{ $periode->naam }}</b> · verstuur de vastgestelde cijferlijsten naar de studenten</div>
  </div>
  <div class="iuasr-dash-vhead__actions">
    @if (auth()->user()->rolIs('examencommissie'))
      <a class="iuasr-dash-btn" href="{{ route('cijferlijst-sjabloon') }}">E-mailsjabloon aanpassen</a>
    @endif
  </div>
</div>

@forelse ($rijen as $r)
  @php $o = $r['opleiding']; @endphp
  <div class="sis-card" style="margin-bottom:16px;">
    <div class="sis-card__hd">
      <h3>{{ $o->naam }}</h3>
      <span class="hint">{{ $o->code }} · <b>{{ $r['vastgesteld'] }}/{{ $r['vakken']->count() }}</b> vakken vastgesteld</span>
    </div>

    @if ($r['vakken']->isNotEmpty())
      <div class="iuasr-dash-tbl-card" style="border:0;">
        <table class="iuasr-dash-tbl">
          <thead><tr><th>Vak</th><th>Code</th><th style="text-align:center;">Leerjaar</th><th style="text-align:center;">Cijferlijst</th></tr></thead>
          <tbody>
            @foreach ($r['vakken'] as $v)
              <tr>
                <td class="nm">{{ $v['vak']->naam }}</td>
                <td class="tnum">{{ $v['vak']->code }}</td>
                <td class="tnum" style="text-align:center;">{{ $v['vak']->leerjaar }}</td>
                <td style="text-align:center;"><span class="iuasr-dash-status {{ $v['status']->badge() }}">{{ $v['status']->label() }}</span></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div style="padding:10px 16px;"><p class="sis-muted" style="margin:0;">Geen actieve vakken voor deze opleiding in dit studiejaar.</p></div>
    @endif

    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:12px 16px;border-top:1px solid var(--borderColor);padding-top:12px;">
      <span class="sis-pill-soft"><b>{{ $r['teVersturen'] }}</b> te versturen</span>
      <span class="sis-pill-soft"><b>{{ $r['alVerzonden'] }}</b> al verzonden</span>
      <span class="sis-pill-soft"><b>{{ $r['overgeslagen'] }}</b> overgeslagen</span>
      <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('resultaten-mailen', ['opleiding_id' => $o->id]) }}">Ontvangers bekijken</a>
      <span style="flex:1;"></span>
      @if ($r['alVerzonden'] > 0)
        <form method="POST" action="{{ route('resultaten-mailen.versturen') }}" style="display:inline;" onsubmit="return confirm('Ook de AL verzonden cijferlijsten opnieuw versturen voor {{ $o->code }}?');">
          @csrf
          <input type="hidden" name="opleiding_id" value="{{ $o->id }}">
          <input type="hidden" name="opnieuw" value="1">
          <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Opnieuw versturen</button>
        </form>
      @endif
      <form method="POST" action="{{ route('resultaten-mailen.versturen') }}" style="display:inline;" onsubmit="return confirm('Cijferlijsten versturen naar {{ $r['teVersturen'] }} student(en) van {{ $o->code }}?');">
        @csrf
        <input type="hidden" name="opleiding_id" value="{{ $o->id }}">
        <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit" {{ $r['teVersturen'] === 0 ? 'disabled' : '' }}>Versturen ({{ $r['teVersturen'] }})</button>
      </form>
    </div>
  </div>
@empty
  <div class="iuasr-dash-empty" style="margin-top:16px;"><h3>Niets te mailen</h3><p class="sis-muted">Er zijn geen opleidingen met vakken of studenten in dit studiejaar.</p></div>
@endforelse

<p class="sis-tblnote">Elke student ontvangt <b>individueel</b> de eigen, door de examencommissie vastgestelde cijferlijst als ondertekende PDF. Het versturen gaat via de <b>wachtrij</b> (de status ziet u onder <b>Ontvangers bekijken</b>). Al gemailde studenten worden deze periode <b>overgeslagen</b>, tenzij u <b>Opnieuw versturen</b> kiest.</p>
@endsection
