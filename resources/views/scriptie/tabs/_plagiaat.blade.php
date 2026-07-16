@php $magBewerken = $scriptie->magStapBewerken(auth()->user(), $stap); @endphp
@include('scriptie.tabs._kop')
<form method="POST" action="{{ route('scriptie.stap.update', ['scriptie' => $scriptie, 'stap' => $stap->value]) }}" class="sis-card sis-form">
    @csrf @method('PUT')
    <div class="sis-card__hd"><h3>Plagiaatcontrole</h3></div>
    <div class="sis-fld-row sis-fld-row--3">
        <div class="sis-fld"><label>Datum van controle</label><input type="date" name="plagiaat_datum" value="{{ old('plagiaat_datum', $scriptie->plagiaat_datum?->format('Y-m-d')) }}" @disabled(! $magBewerken)></div>
        <div class="sis-fld"><label>Versienummer</label><input type="text" name="plagiaat_versienummer" value="{{ old('plagiaat_versienummer', $scriptie->plagiaat_versienummer) }}" @disabled(! $magBewerken)></div>
        <div class="sis-fld"><label>Similariteitspercentage</label><input type="number" step="0.01" min="0" max="100" name="plagiaat_similariteit" value="{{ old('plagiaat_similariteit', $scriptie->plagiaat_similariteit) }}" @disabled(! $magBewerken)></div>
    </div>
    <div class="sis-fld-row sis-fld-row--2">
        <div class="sis-fld"><label>Beoordeeld door</label><input type="text" name="plagiaat_beoordeeld_door" value="{{ old('plagiaat_beoordeeld_door', $scriptie->plagiaat_beoordeeld_door) }}" @disabled(! $magBewerken)></div>
        <div class="sis-fld"><label class="sis-check-inline"><input type="checkbox" name="plagiaat_rapport_beschikbaar" value="1" @checked(old('plagiaat_rapport_beschikbaar', $scriptie->plagiaat_rapport_beschikbaar)) @disabled(! $magBewerken)> Rapport beschikbaar</label></div>
    </div>
    <div class="sis-fld"><label>Toelichting</label><textarea name="plagiaat_toelichting" rows="2" @disabled(! $magBewerken)>{{ old('plagiaat_toelichting', $scriptie->plagiaat_toelichting) }}</textarea></div>
    <div class="sis-fld"><label>Eventuele vervolgstappen</label><textarea name="plagiaat_vervolgstappen" rows="2" @disabled(! $magBewerken)>{{ old('plagiaat_vervolgstappen', $scriptie->plagiaat_vervolgstappen) }}</textarea></div>
    @if ($magBewerken)
        <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Plagiaatcontrole opslaan</button></div></div>
    @endif
</form>

@include('scriptie.tabs._documenten', ['categorie' => 'plagiaatrapport', 'titel' => 'Plagiaatrapport'])
