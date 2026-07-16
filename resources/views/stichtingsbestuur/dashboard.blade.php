@extends('layouts.app')

@section('titel', 'Stichtingsbestuur')

@section('inhoud')
@php $magBeheer = auth()->user()->magStichtingsbestuurBeheren(); @endphp
<div class="sis-crumb"><b>Stichtingsbestuur</b></div>

<div class="iuasr-dash-vhead">
    <div>
        <h1>Stichtingsbestuur</h1>
        <div class="summary">Bestuursleden, Raad van Toezicht en vergaderingen van de stichting</div>
    </div>
    <div class="iuasr-dash-vhead__actions">
        <a class="iuasr-dash-btn" href="{{ route('stichtingsbestuur.leden') }}">Alle leden</a>
        <a class="iuasr-dash-btn" href="{{ route('stichtingsbestuur.vergaderingen') }}">Vergaderingen</a>
        @if ($magBeheer)
            <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('stichtingsbestuur.vergaderingen.create') }}">+ Vergadering</a>
        @endif
    </div>
</div>

<div class="iuasr-dash-stats">
    <div class="iuasr-dash-stat"><div class="lbl">Bestuursleden</div><div class="val">{{ $kpi['bestuur'] }}</div></div>
    <div class="iuasr-dash-stat"><div class="lbl">Raad van Toezicht</div><div class="val">{{ $kpi['rvt'] }}</div></div>
    <div class="iuasr-dash-stat"><div class="lbl">Vergaderingen</div><div class="val">{{ $kpi['vergaderingen'] }}</div></div>
</div>

<div class="sis-grid-2" style="margin-top:16px;">
    <div class="sis-card">
        <div class="sis-card__hd"><h3>Stichtingsbestuur</h3><span class="hint">{{ $bestuur->count() }} leden</span></div>
        @forelse ($bestuur as $lid)
            <div style="display:flex;justify-content:space-between;gap:10px;padding:6px 0;border-bottom:1px solid var(--borderSubtleColor);">
                <div>
                    <b>{{ $lid->volledigeNaam() }}</b>
                    <span class="sis-pill-soft">{{ $lid->titel->label() }}</span>
                    <div class="sis-muted" style="font-size:12px;">{{ $lid->email ?: '—' }}@if ($lid->telefoon) · {{ $lid->telefoon }}@endif</div>
                </div>
                @if ($magBeheer)<a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('stichtingsbestuur.leden.edit', $lid) }}">Bewerken</a>@endif
            </div>
        @empty
            <p class="sis-muted">Nog geen bestuursleden vastgelegd.</p>
        @endforelse
    </div>

    <div class="sis-card">
        <div class="sis-card__hd"><h3>Raad van Toezicht</h3><span class="hint">{{ $rvt->count() }} commissarissen</span></div>
        @forelse ($rvt as $lid)
            <div style="display:flex;justify-content:space-between;gap:10px;padding:6px 0;border-bottom:1px solid var(--borderSubtleColor);">
                <div>
                    <b>{{ $lid->volledigeNaam() }}</b>
                    <span class="sis-pill-soft">{{ $lid->titel->label() }}</span>
                    <div class="sis-muted" style="font-size:12px;">{{ $lid->email ?: '—' }}@if ($lid->telefoon) · {{ $lid->telefoon }}@endif</div>
                </div>
                @if ($magBeheer)<a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('stichtingsbestuur.leden.edit', $lid) }}">Bewerken</a>@endif
            </div>
        @empty
            <p class="sis-muted">Nog geen commissarissen vastgelegd.</p>
        @endforelse
    </div>
</div>

<div class="iuasr-dash-tbl-card" style="margin-top:16px;">
    <div class="sis-card__hd" style="padding:12px 14px 0;"><h3>Recente vergaderingen</h3></div>
    <table class="iuasr-dash-tbl">
        <thead><tr><th>Datum</th><th>Soort</th><th>Onderwerpen</th><th>Aanwezig</th><th class="row-act"></th></tr></thead>
        <tbody>
            @forelse ($vergaderingen as $v)
                <tr>
                    <td class="tnum">{{ $v->datum?->format('d-m-Y') }}</td>
                    <td>{{ $v->orgaan->label() }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($v->onderwerpen, 60) ?: '—' }}</td>
                    <td class="tnum">{{ $v->aantalAanwezig() }}</td>
                    <td class="row-act"><a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('stichtingsbestuur.vergaderingen.show', $v) }}">Openen</a></td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="iuasr-dash-empty" style="border:0;"><h3>Nog geen vergaderingen</h3><p class="sis-muted">Leg een vergadering vast via de knop hierboven.</p></div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
