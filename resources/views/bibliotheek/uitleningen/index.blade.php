@extends('layouts.app')

@section('titel', 'Uitleningen')

@section('inhoud')
@php $magBeheer = auth()->user()->magBibliotheekBeheren(); @endphp

<div class="sis-crumb"><a href="{{ route('bibliotheek.dashboard') }}">Bibliotheek</a><span class="sep">›</span><b>Uitleningen</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Uitleningen</h1>
    <div class="summary">{{ $uitleningen->total() }} {{ $uitleningen->total() === 1 ? 'uitlening' : 'uitleningen' }}</div>
  </div>
  @if ($magBeheer)
    <div class="iuasr-dash-vhead__actions">
      <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('bibliotheek.uitlenen') }}">Uitlenen</a>
    </div>
  @endif
</div>

<form method="GET" action="{{ route('bibliotheek.uitleningen') }}" class="sis-toolbar" style="margin-bottom:12px; gap:8px;">
  <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op titel, serienummer, lener of studentnummer" dir="auto">
  <select name="status">
    <option value="lopend" @selected($statusFilter === 'lopend')>Lopend</option>
    <option value="telaat" @selected($statusFilter === 'telaat')>Te laat</option>
    <option value="retour" @selected($statusFilter === 'retour')>Geretourneerd</option>
    <option value="alle" @selected($statusFilter === 'alle')>Alle</option>
  </select>
  <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Filteren</button>
</form>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Lener</th><th>Publicatie</th><th>Uitgeleend</th><th>Retour verwacht</th><th>Status</th><th style="text-align:center;">Mails</th><th class="row-act"></th></tr></thead>
    <tbody>
      @forelse ($uitleningen as $u)
        <tr>
          <td class="nm">
            <a href="{{ route('bibliotheek.lener', ['type' => $u->isStudentlening() ? 'student' : 'medewerker', 'id' => $u->student_id ?? $u->medewerker_id]) }}">{{ $u->lenerNaam() }}</a>
            <br><small class="sis-muted">{{ $u->isStudentlening() ? ($u->student->studentnummer ?? 'Student') : 'Medewerker' }}</small>
          </td>
          <td dir="auto">{{ $u->exemplaar->publicatie->volledigeTitel() }}<br><small class="sis-muted">{{ $u->exemplaar->serienummer }}</small></td>
          <td class="tnum">{{ $u->uitgeleend_op->format('d-m-Y') }}</td>
          <td class="tnum">{{ $u->verwachte_retour_op->format('d-m-Y') }}</td>
          <td>
            @if ($u->isRetour())
              <span class="iuasr-dash-status {{ $u->isOpTijdIngeleverd() ? 's-approved' : 's-incomplete' }}">
                Retour {{ $u->retour_op->format('d-m-Y') }}{{ $u->isOpTijdIngeleverd() ? '' : ' (te laat)' }}
              </span>
            @elseif ($u->isTeLaat())
              <span class="iuasr-dash-status s-rejected">{{ $u->dagenTeLaat() }} dagen te laat</span>
            @else
              <span class="iuasr-dash-status s-submitted">Uitgeleend</span>
            @endif
          </td>
          <td style="text-align:center;">{{ $u->emaillogs->count() }}</td>
          <td class="row-act">
            @if ($magBeheer && ! $u->isRetour())
              <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('bibliotheek.innemen', $u) }}">Innemen</a>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="7"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen uitleningen</h3><p class="sis-muted">Er zijn geen uitleningen die aan deze filters voldoen.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div style="margin-top:12px;">{{ $uitleningen->links() }}</div>
@endsection
