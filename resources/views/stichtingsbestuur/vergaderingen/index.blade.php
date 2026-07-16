@extends('layouts.app')

@section('titel', 'Vergaderingen')

@section('inhoud')
@php $magBeheer = auth()->user()->magStichtingsbestuurBeheren(); @endphp
<div class="sis-crumb"><a href="{{ route('stichtingsbestuur.dashboard') }}">Stichtingsbestuur</a><span class="sep">›</span><b>Vergaderingen</b></div>

<div class="iuasr-dash-vhead">
    <div>
        <h1>Vergaderingen</h1>
        <div class="summary">{{ $vergaderingen->total() }} vergaderingen</div>
    </div>
    <div class="iuasr-dash-vhead__actions">
        @if ($magBeheer)
            <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('stichtingsbestuur.vergaderingen.create') }}">+ Vergadering</a>
        @endif
    </div>
</div>

<form method="GET" class="sis-toolbar">
    <select name="orgaan" data-autofilter>
        <option value="">Alle soorten</option>
        @foreach (\App\Enums\Bestuursorgaan::opties() as $waarde => $label)
            <option value="{{ $waarde }}" @selected($orgaanFilter === $waarde)>{{ $label }}</option>
        @endforeach
    </select>
    <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Filteren</button>
</form>

<div class="iuasr-dash-tbl-card">
    <table class="iuasr-dash-tbl">
        <thead><tr><th>Datum</th><th>Soort</th><th>Onderwerpen</th><th>Aanwezig</th><th>Notulist</th><th class="row-act"></th></tr></thead>
        <tbody>
            @forelse ($vergaderingen as $v)
                <tr>
                    <td class="tnum">{{ $v->datum?->format('d-m-Y') }}</td>
                    <td>{{ $v->orgaan->label() }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($v->onderwerpen, 70) ?: '—' }}</td>
                    <td class="tnum">{{ $v->aantalAanwezig() }}</td>
                    <td>{{ $v->genotuleerdDoor?->naam ?? '—' }}</td>
                    <td class="row-act"><a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('stichtingsbestuur.vergaderingen.show', $v) }}">Openen</a></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen vergaderingen</h3><p class="sis-muted">Leg de eerste vergadering vast.</p></div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $vergaderingen->links() }}
@endsection
