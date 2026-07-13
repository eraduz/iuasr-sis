@extends('layouts.app')

@section('titel', 'Verrijking')

@section('inhoud')
@php
  $statussen = [
    \App\Models\Bibliotheek\Verrijking::ONZEKER => 'Onzeker — overgeslagen',
    \App\Models\Bibliotheek\Verrijking::TOEGEPAST => 'Toegepast',
    \App\Models\Bibliotheek\Verrijking::GEEN_TREFFER => 'Geen treffer',
    \App\Models\Bibliotheek\Verrijking::FOUT => 'Fout bij het ophalen',
    'alle' => 'Alle',
  ];
@endphp

<div class="sis-crumb"><a href="{{ route('bibliotheek.dashboard') }}">Bibliotheek</a><span class="sep">›</span><b>Verrijking</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Verrijking met ISBN en uitgavejaar</h1>
    <div class="summary">Alleen Nederlandse, Engelse en Turkse titels. Er wordt uitsluitend iets gewijzigd bij een zekere match; twijfelgevallen staan hieronder en beslist u zelf.</div>
  </div>
</div>

<div class="iuasr-dash-stats">
  <div class="iuasr-dash-stat"><span class="lbl">Toegepast</span><span class="val">{{ $telling[\App\Models\Bibliotheek\Verrijking::TOEGEPAST] ?? 0 }}</span><span class="delta">zekere match</span></div>
  <div class="iuasr-dash-stat iuasr-dash-stat--alert"><span class="lbl">Onzeker</span><span class="val">{{ $telling[\App\Models\Bibliotheek\Verrijking::ONZEKER] ?? 0 }}</span><span class="delta">overgeslagen</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Geen treffer</span><span class="val">{{ $telling[\App\Models\Bibliotheek\Verrijking::GEEN_TREFFER] ?? 0 }}</span><span class="delta">niet gevonden</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Titels met ISBN</span><span class="val">{{ number_format($metIsbn, 0, ',', '.') }}</span><span class="delta">in de catalogus</span></div>
  <div class="iuasr-dash-stat"><span class="lbl">Nog te bevragen</span><span class="val">{{ number_format($nogTeGaan, 0, ',', '.') }}</span><span class="delta">titels</span></div>
</div>

@if ($nogTeGaan > 0)
  <div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin:16px 0;">
    <span>Er zijn nog {{ number_format($nogTeGaan, 0, ',', '.') }} titels niet bevraagd. De beheerder draait op de server: <code>php artisan bibliotheek:verrijken --limiet=500</code> (herhaalbaar; al bevraagde titels worden overgeslagen).</span>
  </div>
@endif

<form method="GET" action="{{ route('bibliotheek.verrijking') }}" class="sis-toolbar" style="margin-bottom:12px;" data-autofilter>
  <select name="status">
    @foreach ($statussen as $waarde => $label)
      <option value="{{ $waarde }}" @selected($status === $waarde)>{{ $label }}</option>
    @endforeach
  </select>
  <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Tonen</button>
</form>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Onze titel</th><th>Gevonden titel</th><th>Auteur (gevonden)</th><th>ISBN</th><th>Jaar</th><th style="text-align:right;">Zekerheid</th><th class="row-act"></th></tr></thead>
    <tbody>
      @forelse ($verrijkingen as $v)
        <tr>
          <td class="nm" dir="auto">
            <a href="{{ route('bibliotheek.publicaties.show', $v->publicatie_id) }}">{{ \Illuminate\Support\Str::limit($v->oude_titel ?? $v->publicatie?->titel, 45) }}</a>
            <br><small class="sis-muted">{{ $v->publicatie?->auteursTekst() }}</small>
          </td>
          <td dir="auto">{{ \Illuminate\Support\Str::limit($v->gevonden_titel ?? '—', 45) }}</td>
          <td dir="auto">{{ \Illuminate\Support\Str::limit($v->gevonden_auteur ?? '—', 30) }}</td>
          <td class="tnum">{{ $v->isbn ?? '—' }}</td>
          <td class="tnum">{{ $v->jaar ?? '—' }}</td>
          <td class="tnum" style="text-align:right;">
            @if ($v->score !== null)
              <span class="iuasr-dash-status {{ $v->score >= 0.92 ? 's-approved' : ($v->score >= 0.75 ? 's-requested' : 's-rejected') }}">{{ number_format($v->score * 100, 0) }}%</span>
            @else
              —
            @endif
          </td>
          <td class="row-act" style="white-space:nowrap;">
            @if ($v->status === \App\Models\Bibliotheek\Verrijking::ONZEKER)
              <form method="POST" action="{{ route('bibliotheek.verrijking.overnemen', $v) }}" style="display:inline;">
                @csrf @method('PUT')
                <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Overnemen</button>
              </form>
              <form method="POST" action="{{ route('bibliotheek.verrijking.afwijzen', $v) }}" style="display:inline;">
                @csrf @method('PUT')
                <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Afwijzen</button>
              </form>
            @else
              <span class="sis-muted">{{ $v->statusLabel() }}</span>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="7"><div class="iuasr-dash-empty" style="border:0;"><h3>Niets te tonen</h3><p class="sis-muted">Er zijn geen titels met deze status.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div style="margin-top:12px;">{{ $verrijkingen->links() }}</div>
<p class="sis-tblnote">De zekerheid is de gelijkenis tussen onze titel en de gevonden titel. Vanaf 92% én een overeenkomende auteur past het systeem de correctie zelf toe; daaronder beslist u. Elke wijziging wordt gelogd en de oude titel blijft bewaard.</p>
@endsection

@include('partials.autofilter')
