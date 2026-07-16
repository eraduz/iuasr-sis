@extends('layouts.app')

@section('titel', $lid->exists ? 'Lid bewerken' : 'Lid toevoegen')

@section('inhoud')
@php $nieuw = ! $lid->exists; @endphp
<div class="sis-crumb"><a href="{{ route('stichtingsbestuur.dashboard') }}">Stichtingsbestuur</a><span class="sep">›</span><a href="{{ route('stichtingsbestuur.leden') }}">Leden</a><span class="sep">›</span><b>{{ $nieuw ? 'Nieuw' : $lid->volledigeNaam() }}</b></div>

<div class="iuasr-dash-vhead"><div><h1>{{ $nieuw ? 'Lid toevoegen' : 'Lid bewerken' }}</h1></div></div>

@if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger"><span>{{ $errors->first() }}</span></div>
@endif

<form method="POST" action="{{ $nieuw ? route('stichtingsbestuur.leden.store') : route('stichtingsbestuur.leden.update', $lid) }}" class="sis-card sis-form">
    @csrf
    @unless ($nieuw) @method('PUT') @endunless

    <fieldset class="sis-fieldset">
        <legend>Functie</legend>
        <div class="sis-fld-row sis-fld-row--2">
            <div class="sis-fld">
                <label>Orgaan <span class="req">*</span></label>
                <select name="orgaan" required>
                    @foreach (\App\Enums\Bestuursorgaan::opties() as $waarde => $label)
                        <option value="{{ $waarde }}" @selected(old('orgaan', $lid->orgaan?->value) === $waarde)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sis-fld">
                <label>Titel <span class="req">*</span></label>
                <select name="titel" required>
                    @foreach (\App\Enums\Bestuurstitel::opties() as $waarde => $label)
                        <option value="{{ $waarde }}" @selected(old('titel', $lid->titel?->value) === $waarde)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="sis-fld">
            <label>Bevoegdheid <span class="sis-muted" style="font-weight:400;">(alleen voor het stichtingsbestuur)</span></label>
            <input type="text" name="bevoegdheid" value="{{ old('bevoegdheid', $lid->bevoegdheid) }}">
        </div>
    </fieldset>

    <fieldset class="sis-fieldset">
        <legend>Persoonsgegevens</legend>
        <div class="sis-fld-row sis-fld-row--2">
            <div class="sis-fld"><label>Voornaam <span class="req">*</span></label><input type="text" name="voornaam" value="{{ old('voornaam', $lid->voornaam) }}" required></div>
            <div class="sis-fld"><label>Achternaam <span class="req">*</span></label><input type="text" name="achternaam" value="{{ old('achternaam', $lid->achternaam) }}" required></div>
        </div>
        <div class="sis-fld-row sis-fld-row--2">
            <div class="sis-fld"><label>Geboortedatum</label><input type="date" name="geboortedatum" value="{{ old('geboortedatum', $lid->geboortedatum?->format('Y-m-d')) }}"></div>
            <div class="sis-fld"><label>Adres</label><input type="text" name="adres" value="{{ old('adres', $lid->adres) }}"></div>
        </div>
        <div class="sis-fld-row sis-fld-row--2">
            <div class="sis-fld"><label>Telefoonnummer</label><input type="text" name="telefoon" value="{{ old('telefoon', $lid->telefoon) }}"></div>
            <div class="sis-fld"><label>E-mailadres</label><input type="email" name="email" value="{{ old('email', $lid->email) }}"></div>
        </div>
    </fieldset>

    <fieldset class="sis-fieldset">
        <legend>Periode</legend>
        <div class="sis-fld-row sis-fld-row--2">
            <div class="sis-fld"><label>Datum in functie</label><input type="date" name="datum_in_functie" value="{{ old('datum_in_functie', $lid->datum_in_functie?->format('Y-m-d')) }}"></div>
            <div class="sis-fld"><label>Datum uit functie</label><input type="date" name="datum_uit_functie" value="{{ old('datum_uit_functie', $lid->datum_uit_functie?->format('Y-m-d')) }}"></div>
        </div>
        <div class="sis-fld"><label class="sis-check-inline"><input type="checkbox" name="actief" value="1" @checked(old('actief', $lid->actief ?? true))> Actief lid</label></div>
    </fieldset>

    <div class="sis-form__actions">
        <a class="iuasr-dash-btn" href="{{ route('stichtingsbestuur.leden') }}">Annuleren</a>
        <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div>
    </div>
</form>
@endsection
