@extends('layouts.app')

@section('titel', 'Organisaties')

@section('inhoud')
@php $magBeheer = auth()->user()->magRelatiebeheer(); @endphp

<div class="sis-crumb"><b>Relatiebeheer</b><span class="sep">›</span><b>Organisaties</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Organisaties</h1>
    <div class="summary">{{ $organisaties->total() }} {{ $organisaties->total() === 1 ? 'organisatie' : 'organisaties' }}</div>
  </div>
  @if ($magBeheer)
    <div class="iuasr-dash-vhead__actions">
      <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('relaties.create') }}">Nieuwe organisatie</a>
    </div>
  @endif
</div>

<form method="GET" action="{{ route('relaties') }}" class="sis-toolbar" style="margin-bottom:12px; gap:8px; flex-wrap:wrap;">
  <input type="hidden" name="per" value="{{ $perPagina }}">
  <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op naam, plaats of relatienummer">
  <select name="type">
    <option value="">Alle types</option>
    @foreach ($types as $t)
      <option value="{{ $t->id }}" @selected($typeFilter === $t->id)>{{ $t->naam }}</option>
    @endforeach
  </select>
  <select name="opleiding">
    <option value="">Alle opleidingen</option>
    @foreach ($opleidingen as $o)
      <option value="{{ $o->id }}" @selected($opleidingFilter === $o->id)>{{ $o->code }}</option>
    @endforeach
  </select>
  <select name="status">
    <option value="actief" @selected($status === 'actief')>Actief</option>
    <option value="inactief" @selected($status === 'inactief')>Inactief</option>
    <option value="alle" @selected($status === 'alle')>Alle</option>
  </select>
  <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Filteren</button>
</form>

@include('partials.az-index', ['route' => 'relaties', 'letterFilter' => $letterFilter, 'perPagina' => $perPagina])

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Relatienr.</th><th>Naam</th><th>Type</th><th>Plaats</th><th>Opleiding(en)</th><th style="text-align:center;">Status</th><th class="row-act"></th></tr></thead>
    <tbody>
      @forelse ($organisaties as $org)
        <tr>
          <td class="tnum">{{ $org->relatienummer }}</td>
          <td class="nm"><a href="{{ route('relaties.show', $org) }}">{{ $org->naam }}</a></td>
          <td>{{ $org->type?->naam ?? '—' }}</td>
          <td>{{ $org->plaats ?? '—' }}</td>
          <td>{{ $org->opleidingen->pluck('code')->implode(', ') ?: '—' }}</td>
          <td style="text-align:center;"><span class="iuasr-dash-status {{ $org->actief ? 's-approved' : 's-draft' }}">{{ $org->actief ? 'Actief' : 'Inactief' }}</span></td>
          <td class="row-act" style="white-space:nowrap;">
            <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('relaties.show', $org) }}">Bekijken</a>
            @if ($magBeheer)
              <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('relaties.edit', $org) }}">Bewerken</a>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="7"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen organisaties</h3><p class="sis-muted">Er zijn geen organisaties die aan deze filters voldoen.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div style="margin-top:12px;">{{ $organisaties->links() }}</div>
<p class="sis-tblnote">Organisaties worden nooit verwijderd (historie); zet een organisatie desgewenst op inactief. De zichtbaarheid is opleidinggebonden.</p>
@endsection
