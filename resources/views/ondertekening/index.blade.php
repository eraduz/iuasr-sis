@extends('layouts.app')

@section('titel', 'Ondertekende documenten')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Ondertekende documenten</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Ondertekende documenten</h1>
    <div class="summary">Archief en logregistratie van digitaal ondertekende PDF's · echtheidskenmerk (SHA-256)</div>
  </div>
  <div class="iuasr-dash-vhead__actions">
    <a class="iuasr-dash-btn" href="{{ route('verificatie') }}" target="_blank">Publieke verificatiepagina</a>
  </div>
</div>

<form method="GET" action="{{ route('ondertekening') }}" class="iuasr-dash-filters">
  <div class="search" style="grid-column:1 / -1;">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op verificatiecode, titel of ontvanger…">
  </div>
</form>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Code</th><th>Document</th><th>Verstrekt aan</th><th>Ondertekend door</th><th>Datum</th><th class="row-act"></th></tr></thead>
    <tbody>
      @forelse ($documenten as $doc)
        <tr>
          <td class="tnum">{{ $doc->code }}</td>
          <td class="nm">{{ $doc->titel }}@if($doc->student)<small>{{ $doc->student->studentnummer }}</small>@endif</td>
          <td>{{ $doc->ontvanger ?? '—' }}</td>
          <td>{{ $doc->uitgegevenDoor?->naam ?? '—' }}</td>
          <td class="dt">{{ $doc->created_at->format('d-m-Y H:i') }}</td>
          <td class="row-act"><a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('ondertekening.download', $doc) }}">Downloaden</a></td>
        </tr>
      @empty
        <tr><td colspan="6"><div class="iuasr-dash-empty" style="border:0;"><h3>Nog geen ondertekende documenten</h3><p>Genereer bijvoorbeeld een verklaring; deze wordt automatisch ondertekend en hier gearchiveerd.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div style="margin-top:14px;">{{ $documenten->links() }}</div>
@endsection
