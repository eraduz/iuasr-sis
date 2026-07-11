@extends('layouts.app')

@section('titel', 'Organisatiestructuur')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('hr.dashboard') }}">HR</a><span class="sep">›</span><b>Organisatie</b></div>

<div class="iuasr-dash-vhead"><div><h1>Organisatiestructuur</h1><div class="summary">Afdelingen, teams en leidinggevenden</div></div></div>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Afdeling / team</th><th>Manager</th><th style="text-align:right;">Medewerkers</th></tr></thead>
    <tbody>
      @forelse ($wortels as $wortel)
        @include('hr.partials.afdelingrij', ['afdeling' => $wortel, 'diepte' => 0, 'perOuder' => $perOuder])
      @empty
        <tr><td colspan="3"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen afdelingen</h3><p class="sis-muted">Beheer afdelingen via Opzoektabellen.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<p class="sis-tblnote">Afdelingen, teams (een afdeling met een bovenliggende afdeling) en de afdelingsmanager beheert u via <b>Opzoektabellen → Afdelingen</b>.</p>
@endsection
