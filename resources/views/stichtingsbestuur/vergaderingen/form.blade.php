@extends('layouts.app')

@section('titel', $vergadering->exists ? 'Vergadering bewerken' : 'Nieuwe vergadering')

@section('inhoud')
@php
    $nieuw = ! $vergadering->exists;
    $bestuur = $leden->where('orgaan', \App\Enums\Bestuursorgaan::Stichtingsbestuur)->values();
    $rvt = $leden->where('orgaan', \App\Enums\Bestuursorgaan::RaadVanToezicht)->values();
    $huidigVal = fn ($lid) => ($h = ($huidig[$lid->id] ?? null)) instanceof \App\Enums\Aanwezigheid ? $h->value : ($h ?? '');
@endphp
<div class="sis-crumb"><a href="{{ route('stichtingsbestuur.dashboard') }}">Stichtingsbestuur</a><span class="sep">›</span><a href="{{ route('stichtingsbestuur.vergaderingen') }}">Vergaderingen</a><span class="sep">›</span><b>{{ $nieuw ? 'Nieuw' : $vergadering->datum?->format('d-m-Y') }}</b></div>

<div class="iuasr-dash-vhead"><div><h1>{{ $nieuw ? 'Nieuwe vergadering' : 'Vergadering bewerken' }}</h1></div></div>

@if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger"><span>{{ $errors->first() }}</span></div>
@endif

<form method="POST" action="{{ $nieuw ? route('stichtingsbestuur.vergaderingen.store') : route('stichtingsbestuur.vergaderingen.update', $vergadering) }}" class="sis-card sis-form">
    @csrf
    @unless ($nieuw) @method('PUT') @endunless

    <fieldset class="sis-fieldset">
        <legend>Vergadering</legend>
        <div class="sis-fld-row sis-fld-row--3">
            <div class="sis-fld"><label>Datum <span class="req">*</span></label><input type="date" name="datum" value="{{ old('datum', $vergadering->datum?->format('Y-m-d')) }}" required></div>
            <div class="sis-fld">
                <label>Soort vergadering <span class="req">*</span></label>
                <select name="orgaan" required>
                    @foreach (\App\Enums\Bestuursorgaan::opties() as $waarde => $label)
                        <option value="{{ $waarde }}" @selected(old('orgaan', $vergadering->orgaan?->value) === $waarde)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sis-fld"><label>Locatie</label><input type="text" name="locatie" value="{{ old('locatie', $vergadering->locatie) }}"></div>
        </div>
        <div class="sis-fld"><label>Welke onderwerpen zijn besproken?</label><textarea name="onderwerpen" rows="4">{{ old('onderwerpen', $vergadering->onderwerpen) }}</textarea></div>
        <div class="sis-fld"><label>Wat zijn de besluiten?</label><textarea name="besluiten" rows="4">{{ old('besluiten', $vergadering->besluiten) }}</textarea></div>
        <div class="sis-fld"><label>Opmerking</label><textarea name="opmerking" rows="2">{{ old('opmerking', $vergadering->opmerking) }}</textarea></div>
    </fieldset>

    <fieldset class="sis-fieldset">
        <legend>Aanwezigheid</legend>
        <p class="sis-muted" style="margin:0 0 8px;">Zet per lid de aanwezigheid. Laat leeg wat niet van toepassing is.</p>

        @foreach (['Stichtingsbestuur' => $bestuur, 'Raad van Toezicht' => $rvt] as $groep => $groepLeden)
            @if ($groepLeden->isNotEmpty())
                <div class="sis-card" style="margin:0 0 10px;">
                    <div class="sis-card__hd"><h3 style="font-size:15px;">{{ $groep }}</h3></div>
                    @foreach ($groepLeden as $lid)
                        @php $val = $huidigVal($lid); @endphp
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:4px 0;border-bottom:1px solid var(--borderSubtleColor);">
                            <span>{{ $lid->volledigeNaam() }} <span class="sis-muted" style="font-size:12px;">· {{ $lid->titel->label() }}</span></span>
                            <select name="aanwezigheid[{{ $lid->id }}]" style="max-width:200px;">
                                <option value="">— niet geregistreerd —</option>
                                @foreach (\App\Enums\Aanwezigheid::opties() as $waarde => $label)
                                    <option value="{{ $waarde }}" @selected($val === $waarde)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
            @endif
        @endforeach
        @if ($leden->isEmpty())
            <p class="sis-muted">Er zijn nog geen actieve leden. Voeg eerst leden toe.</p>
        @endif
    </fieldset>

    <div class="sis-form__actions">
        <a class="iuasr-dash-btn" href="{{ route('stichtingsbestuur.vergaderingen') }}">Annuleren</a>
        <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div>
    </div>
</form>
@endsection
