@php $magBewerken = $scriptie->magStapBewerken(auth()->user(), $stap); @endphp
@include('scriptie.tabs._kop')
@include('scriptie.tabs._checklist', ['checklistTitel' => 'Beoordelingsonderdelen'])
<form method="POST" action="{{ route('scriptie.stap.update', ['scriptie' => $scriptie, 'stap' => $stap->value]) }}" class="sis-card sis-form">
    @csrf @method('PUT')
    <div class="sis-card__hd"><h3>Beoordeling</h3></div>
    <div class="sis-fld-row sis-fld-row--3">
        <div class="sis-fld"><label>Naam eerste beoordelaar</label><input type="text" name="beoordelaar_1" value="{{ old('beoordelaar_1', $scriptie->beoordelaar_1) }}" @disabled(! $magBewerken)></div>
        <div class="sis-fld"><label>Naam tweede beoordelaar</label><input type="text" name="beoordelaar_2" value="{{ old('beoordelaar_2', $scriptie->beoordelaar_2) }}" @disabled(! $magBewerken)></div>
        <div class="sis-fld"><label>Eventuele derde beoordelaar</label><input type="text" name="beoordelaar_3" value="{{ old('beoordelaar_3', $scriptie->beoordelaar_3) }}" @disabled(! $magBewerken)></div>
    </div>
    <div class="sis-fld-row sis-fld-row--3">
        <div class="sis-fld"><label>Datum van beoordeling</label><input type="date" name="beoordeling_datum" value="{{ old('beoordeling_datum', $scriptie->beoordeling_datum?->format('Y-m-d')) }}" @disabled(! $magBewerken)></div>
        <div class="sis-fld"><label>Voorlopig cijfer</label><input type="number" step="0.1" min="1" max="10" name="voorlopig_cijfer" value="{{ old('voorlopig_cijfer', $scriptie->voorlopig_cijfer) }}" @disabled(! $magBewerken)></div>
        <div class="sis-fld"><label>Definitief cijfer</label><input type="number" step="0.1" min="1" max="10" name="definitief_cijfer" value="{{ old('definitief_cijfer', $scriptie->definitief_cijfer) }}" @disabled(! $magBewerken)></div>
    </div>
    <div class="sis-fld"><label>Motivering</label><textarea name="beoordeling_motivering" rows="3" @disabled(! $magBewerken)>{{ old('beoordeling_motivering', $scriptie->beoordeling_motivering) }}</textarea></div>
    <div class="sis-fld-row sis-fld-row--2">
        <div class="sis-fld"><label class="sis-check-inline"><input type="checkbox" name="kalibratie_afgerond" value="1" @checked(old('kalibratie_afgerond', $scriptie->kalibratie_afgerond)) @disabled(! $magBewerken)> Kalibratie afgerond</label></div>
        <div class="sis-fld"><label>Eindbesluit</label><input type="text" name="beoordeling_eindbesluit" value="{{ old('beoordeling_eindbesluit', $scriptie->beoordeling_eindbesluit) }}" @disabled(! $magBewerken)></div>
    </div>
    @if ($magBewerken)
        <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Beoordeling opslaan</button></div></div>
    @endif
</form>

@include('scriptie.tabs._documenten', ['categorie' => 'beoordelingsformulier', 'titel' => 'Beoordelingsformulier'])
