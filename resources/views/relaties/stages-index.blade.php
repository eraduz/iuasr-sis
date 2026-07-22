@extends('layouts.app')

@section('titel', 'Stages')

@section('inhoud')
@php $magBeheer = auth()->user()->magStagebeheer(); @endphp

<div class="sis-crumb"><b>Relatiebeheer</b><span class="sep">›</span><b>Stages</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Stages</h1>
    <div class="summary">{{ $stages->total() }} {{ $stages->total() === 1 ? 'stage' : 'stages' }}</div>
  </div>
  @if ($magBeheer)
    <div class="iuasr-dash-vhead__actions" style="display:flex; gap:8px; align-items:center;">
      @if ($organisatiesVoorPlaatsing->isNotEmpty())
        <label for="plaats-org" class="sis-muted" style="white-space:nowrap;">Student plaatsen bij:</label>
        <select id="plaats-org" aria-label="Kies een organisatie om een student te plaatsen">
          <option value="">— kies een organisatie —</option>
          @foreach ($organisatiesVoorPlaatsing as $org)
            <option value="{{ route('stages.create', $org) }}">{{ $org->naam }}</option>
          @endforeach
        </select>
        <button type="button" class="iuasr-dash-btn iuasr-dash-btn--primary"
          onclick="var s=document.getElementById('plaats-org'); if(s.value){location.href=s.value;}else{s.focus();}">Student plaatsen</button>
      @else
        <a class="iuasr-dash-btn" href="{{ route('relaties') }}">Naar organisaties</a>
      @endif
    </div>
  @endif
</div>
@if ($magBeheer && $organisatiesVoorPlaatsing->isNotEmpty())
  <p class="sis-muted" style="margin:-4px 0 12px;">Kies hierboven een organisatie om een student te plaatsen. U kunt dit ook doen vanaf de relatiekaart van een organisatie (paneel <b>Stages</b>).</p>
@endif

<form method="GET" action="{{ route('stages') }}" class="sis-toolbar" style="margin-bottom:12px; gap:8px; flex-wrap:wrap;">
  <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op student of stagenummer">
  <select name="status">
    <option value="">Alle statussen</option>
    @foreach ($statussen as $s)
      <option value="{{ $s->value }}" @selected($statusFilter === $s->value)>{{ $s->label() }}</option>
    @endforeach
  </select>
  <select name="opleiding">
    <option value="">Alle opleidingen</option>
    @foreach ($opleidingen as $o)
      <option value="{{ $o->id }}" @selected($opleidingFilter === $o->id)>{{ $o->code }}</option>
    @endforeach
  </select>
  <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Filteren</button>
</form>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead><tr><th>Stagenr.</th><th>Student</th><th>Opleiding</th><th>Stage</th><th>Organisatie</th><th>Begeleiders</th><th>Periode</th><th style="text-align:center;">Status</th><th>Beoordeling</th>@if($magBeheer)<th class="row-act"></th>@endif</tr></thead>
    <tbody>
      @forelse ($stages as $stage)
        <tr>
          <td class="tnum">{{ $stage->stagenummer }}</td>
          <td class="nm"><a href="{{ route('relaties.show', $stage->organisatie) }}#stages">{{ $stage->student?->volledigeNaam() ?? '—' }}</a><br><small class="sis-muted">{{ $stage->student?->studentnummer }}</small></td>
          <td>{{ $stage->opleiding?->code ?? '—' }}</td>
          <td>@if($stage->stageperiode){{ $stage->stageperiode->naam }}<br><small class="sis-muted">{{ $stage->uren ?? $stage->stageperiode->verplichte_uren }} / {{ $stage->stageperiode->verplichte_uren }} u</small>@else<span class="sis-muted">—</span>@endif</td>
          <td>{{ $stage->organisatie?->naam ?? '—' }}</td>
          <td>
            <small>{{ $stage->stagebegeleider?->naam ?? '—' }}<br>{{ $stage->werkplekbegeleider?->volledigeNaam() ?? '—' }}</small>
          </td>
          <td class="dt"><small>{{ $stage->startdatum?->format('d-m-Y') ?? '—' }}@if($stage->einddatum)<br>t/m {{ $stage->einddatum->format('d-m-Y') }}@endif</small></td>
          <td style="text-align:center;"><span class="iuasr-dash-status {{ $stage->status?->badge() }}">{{ $stage->status?->label() }}</span></td>
          <td>@if($stage->beoordeling)<span class="iuasr-dash-status {{ $stage->beoordeling === 'voldoende' ? 's-approved' : 's-rejected' }}">{{ ucfirst($stage->beoordeling) }}</span>@else <span class="sis-muted">—</span> @endif</td>
          @if($magBeheer)<td class="row-act"><a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('stages.edit', $stage) }}">Bewerken</a></td>@endif
        </tr>
      @empty
        <tr><td colspan="{{ $magBeheer ? 10 : 9 }}"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen stages</h3><p class="sis-muted">@if($magBeheer)Gebruik de knop <b>Student plaatsen</b> bovenaan om een student op een organisatie te plaatsen.@else Er zijn nog geen stages binnen uw bereik.@endif</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div style="margin-top:12px;">{{ $stages->links() }}</div>
<p class="sis-tblnote">Een student plaatst u met de knop <b>Student plaatsen</b> bovenaan (kies eerst de organisatie), of vanaf de relatiekaart van een organisatie (paneel <b>Stages</b>). De beoordeling legt u vast bij het bewerken van een stage.</p>
@endsection
