@extends('layouts.app')

@section('titel', 'Bestuursleden')

@section('inhoud')
@php $magBeheer = auth()->user()->magStichtingsbestuurBeheren(); @endphp
<div class="sis-crumb"><a href="{{ route('stichtingsbestuur.dashboard') }}">Stichtingsbestuur</a><span class="sep">›</span><b>Leden</b></div>

<div class="iuasr-dash-vhead">
    <div>
        <h1>Bestuursleden &amp; commissarissen</h1>
        <div class="summary">{{ $leden->count() }} weergegeven</div>
    </div>
    <div class="iuasr-dash-vhead__actions">
        @if ($magBeheer)
            <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('stichtingsbestuur.leden.create') }}">+ Lid toevoegen</a>
        @endif
    </div>
</div>

<form method="GET" class="sis-toolbar">
    <select name="orgaan" data-autofilter>
        <option value="">Alle organen</option>
        @foreach (\App\Enums\Bestuursorgaan::opties() as $waarde => $label)
            <option value="{{ $waarde }}" @selected($orgaanFilter === $waarde)>{{ $label }}</option>
        @endforeach
    </select>
    <label class="sis-check-inline"><input type="checkbox" name="actief" value="alle" data-autofilter @checked($toonInactief)> Ook afgetreden leden</label>
    <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Filteren</button>
</form>

<div class="iuasr-dash-tbl-card">
    <table class="iuasr-dash-tbl">
        <thead><tr>
            <th>Naam</th><th>Orgaan</th><th>Titel</th><th>Bevoegdheid</th><th>Contact</th><th>In functie</th><th>Status</th><th class="row-act"></th>
        </tr></thead>
        <tbody>
            @forelse ($leden as $lid)
                <tr>
                    <td class="nm">{{ $lid->volledigeNaam() }}</td>
                    <td>{{ $lid->orgaan->label() }}</td>
                    <td>{{ $lid->titel->label() }}</td>
                    <td>{{ $lid->bevoegdheid ?: '—' }}</td>
                    <td>{{ $lid->email ?: '—' }}@if ($lid->telefoon)<br><span class="sis-muted">{{ $lid->telefoon }}</span>@endif</td>
                    <td class="tnum">{{ $lid->datum_in_functie?->format('d-m-Y') ?? '—' }}</td>
                    <td><span class="iuasr-dash-status {{ $lid->actief ? 's-approved' : 's-draft' }}">{{ $lid->actief ? 'actief' : 'afgetreden' }}</span></td>
                    <td class="row-act">
                        @if ($magBeheer)
                            <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('stichtingsbestuur.leden.edit', $lid) }}">Bewerken</a>
                            <form method="POST" action="{{ route('stichtingsbestuur.leden.destroy', $lid) }}" style="display:inline;" onsubmit="return confirm('Dit lid definitief verwijderen? Gebruik anders ‘afgetreden’.');">@csrf @method('DELETE')<button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">Verwijderen</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen leden</h3><p class="sis-muted">Voeg een bestuurslid of commissaris toe.</p></div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
