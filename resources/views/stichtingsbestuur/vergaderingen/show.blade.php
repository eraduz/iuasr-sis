@extends('layouts.app')

@section('titel', 'Vergadering ' . $vergadering->datum?->format('d-m-Y'))

@section('inhoud')
@php
    $magBeheer = auth()->user()->magStichtingsbestuurBeheren();
    $aanwezig = $vergadering->aanwezigheden->sortBy(fn ($a) => $a->bestuurslid?->achternaam);
@endphp
<div class="sis-crumb"><a href="{{ route('stichtingsbestuur.dashboard') }}">Stichtingsbestuur</a><span class="sep">›</span><a href="{{ route('stichtingsbestuur.vergaderingen') }}">Vergaderingen</a><span class="sep">›</span><b>{{ $vergadering->datum?->format('d-m-Y') }}</b></div>

<div class="iuasr-dash-vhead">
    <div>
        <h1>Vergadering {{ $vergadering->orgaan->label() }}</h1>
        <div class="summary">{{ $vergadering->datum?->format('d-m-Y') }}@if ($vergadering->locatie) · {{ $vergadering->locatie }}@endif @if ($vergadering->genotuleerdDoor) · genotuleerd door {{ $vergadering->genotuleerdDoor->naam }}@endif</div>
    </div>
    <div class="iuasr-dash-vhead__actions">
        @if ($magBeheer)
            <a class="iuasr-dash-btn" href="{{ route('stichtingsbestuur.vergaderingen.edit', $vergadering) }}">Bewerken</a>
            <form method="POST" action="{{ route('stichtingsbestuur.vergaderingen.destroy', $vergadering) }}" style="display:inline;" onsubmit="return confirm('Deze vergadering verwijderen?');">@csrf @method('DELETE')<button class="iuasr-dash-btn iuasr-dash-btn--danger" type="submit">Verwijderen</button></form>
        @endif
    </div>
</div>

<div class="sis-grid-2">
    <div>
        <div class="sis-card">
            <div class="sis-card__hd"><h3>Besproken onderwerpen</h3></div>
            <p style="white-space:pre-wrap;">{{ $vergadering->onderwerpen ?: '—' }}</p>
        </div>
        <div class="sis-card">
            <div class="sis-card__hd"><h3>Besluiten</h3></div>
            <p style="white-space:pre-wrap;">{{ $vergadering->besluiten ?: '—' }}</p>
        </div>
        @if ($vergadering->opmerking)
            <div class="sis-card">
                <div class="sis-card__hd"><h3>Opmerking</h3></div>
                <p style="white-space:pre-wrap;">{{ $vergadering->opmerking }}</p>
            </div>
        @endif
    </div>

    <div class="sis-card">
        <div class="sis-card__hd"><h3>Aanwezigheid</h3><span class="hint">{{ $vergadering->aantalAanwezig() }} aanwezig</span></div>
        @forelse ($aanwezig as $a)
            <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;border-bottom:1px solid var(--borderSubtleColor);">
                <span>{{ $a->bestuurslid?->volledigeNaam() ?? 'onbekend lid' }}</span>
                <span class="iuasr-dash-status {{ $a->aanwezigheid->badge() }}">{{ $a->aanwezigheid->label() }}</span>
            </div>
        @empty
            <p class="sis-muted">Geen aanwezigheid geregistreerd.</p>
        @endforelse
    </div>
</div>
@endsection
