@php $magBewerken = $scriptie->magStapBewerken(auth()->user(), $stap); @endphp
@include('scriptie.tabs._kop')
@include('scriptie.tabs._checklist', ['checklistTitel' => 'Beoordelingspunten'])
<form method="POST" action="{{ route('scriptie.stap.update', ['scriptie' => $scriptie, 'stap' => $stap->value]) }}" class="sis-card sis-form">
    @csrf @method('PUT')
    <div class="sis-card__hd"><h3>Besluit</h3><span class="hint">gebruik de statuskeuze bovenaan voor het besluit</span></div>
    <div class="sis-fld-row sis-fld-row--2">
        <div class="sis-fld"><label>Datum van beoordeling</label><input type="date" name="onderwerp_beoordeeld_op" value="{{ old('onderwerp_beoordeeld_op', $scriptie->onderwerp_beoordeeld_op?->format('Y-m-d')) }}" @disabled(! $magBewerken)></div>
        <div class="sis-fld"><label>Naam van de beoordelaar</label><input type="text" name="onderwerp_beoordelaar" value="{{ old('onderwerp_beoordelaar', $scriptie->onderwerp_beoordelaar) }}" @disabled(! $magBewerken)></div>
    </div>
    <div class="sis-fld"><label>Toelichting</label><textarea name="onderwerp_toelichting" rows="3" @disabled(! $magBewerken)>{{ old('onderwerp_toelichting', $scriptie->onderwerp_toelichting) }}</textarea></div>
    <div class="sis-fld"><label>Vereiste aanpassingen</label><textarea name="onderwerp_vereiste_aanpassingen" rows="3" @disabled(! $magBewerken)>{{ old('onderwerp_vereiste_aanpassingen', $scriptie->onderwerp_vereiste_aanpassingen) }}</textarea></div>
    <div class="sis-fld"><label>Uiterste datum voor her-indiening</label><input type="date" name="onderwerp_herindiening_uiterlijk" value="{{ old('onderwerp_herindiening_uiterlijk', $scriptie->onderwerp_herindiening_uiterlijk?->format('Y-m-d')) }}" @disabled(! $magBewerken)></div>
    @if ($magBewerken)
        <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Besluit opslaan</button></div></div>
    @endif
</form>
