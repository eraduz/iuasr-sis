@extends('layouts.app')

@section('titel', 'Afstuderen — kandidaten')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Afstuderen</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Afstuderen — kandidaten</h1>
    <div class="summary">Studenten in het laatste leerjaar · start en volg het afstudeerproces</div>
  </div>
</div>

@php $magStarten = auth()->user()->heeftRol(App\Enums\Rol::Examencommissie) || auth()->user()->heeftRol(App\Enums\Rol::Beheerder); @endphp

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr>
      <th style="width:110px;">Studentnr.</th><th>Naam</th><th>Opleiding</th><th>Leerjaar</th><th>Afstudeerproces</th><th class="row-act"></th>
    </tr></thead>
    <tbody>
      @forelse ($kandidaten as $i)
        @php $proces = $i->afstudeerproces; @endphp
        <tr>
          <td class="tnum">{{ $i->student->studentnummer }}</td>
          <td class="nm">{{ $i->student->volledigeNaam() }}</td>
          <td class="pg">{{ $i->opleiding?->naam }} @unless($i->isLaatsteLeerjaar())<span class="sis-pill-soft" title="Vervroegd vrijgegeven door de examencommissie">vervroegd</span>@endunless</td>
          <td class="tnum">{{ $i->leerjaar }} / {{ $i->opleiding?->nominale_jaren ?? '?' }}</td>
          <td>
            @if (! $proces)
              <span class="sis-muted">Niet gestart</span>
            @elseif ($proces->status === App\Models\Afstudeerproces::AFGEBROKEN)
              <span class="iuasr-dash-status s-rejected">Afgebroken</span>
            @else
              <span class="iuasr-dash-status {{ $proces->isAfgerond() ? 's-approved' : 's-incomplete' }}">{{ $proces->isAfgerond() ? 'Afgerond' : 'Lopend' }}</span>
              <span class="sis-muted" style="font-size:11px;"> {{ $proces->aantalGereed() }}/5</span>
            @endif
          </td>
          <td class="row-act">
            @if (! $proces && $magStarten)
              <form method="POST" action="{{ route('afstuderen.proces.start', $i) }}" style="display:inline;">
                @csrf
                <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary">Start proces</button>
              </form>
            @else
              <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('studenten.show', $i->student) }}#afstuderen">Openen</a>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="6"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen kandidaten</h3><p class="sis-muted">Er zijn nu geen studenten in het laatste leerjaar (of met een vervroegd-vrijgave van de examencommissie).</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<p class="sis-tblnote">De examencommissie start per student het afstudeerproces (5 stappen). Op het dossier volgt u de voortgang; elke stap wordt afgevinkt door de verantwoordelijke rol (examencommissie: verzoek, vakken, stage &amp; scriptie · Studentenzaken: diploma klaarmaken en uitreiken).</p>
@endsection
