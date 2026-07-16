@php $magBewerken = $scriptie->magStapBewerken(auth()->user(), $stap); @endphp
@include('scriptie.tabs._kop')
<form method="POST" action="{{ route('scriptie.stap.update', ['scriptie' => $scriptie, 'stap' => $stap->value]) }}" class="sis-card sis-form">
    @csrf @method('PUT')
    <div class="sis-card__hd"><h3>Gegevens van de verdediging</h3></div>
    <div class="sis-fld-row sis-fld-row--3">
        <div class="sis-fld"><label>Datum</label><input type="date" name="verdediging_datum" value="{{ old('verdediging_datum', $scriptie->verdediging_datum?->format('Y-m-d')) }}" @disabled(! $magBewerken)></div>
        <div class="sis-fld"><label>Tijd</label><input type="time" name="verdediging_tijd" value="{{ old('verdediging_tijd', $scriptie->verdediging_tijd ? substr($scriptie->verdediging_tijd, 0, 5) : '') }}" @disabled(! $magBewerken)></div>
        <div class="sis-fld"><label>Locatie</label><input type="text" name="verdediging_locatie" value="{{ old('verdediging_locatie', $scriptie->verdediging_locatie) }}" @disabled(! $magBewerken)></div>
    </div>
    <div class="sis-fld"><label>Online vergaderlink</label><input type="text" name="verdediging_online_link" value="{{ old('verdediging_online_link', $scriptie->verdediging_online_link) }}" @disabled(! $magBewerken)></div>
    <div class="sis-fld"><label>Leden van de scriptiecommissie</label><textarea name="verdediging_commissieleden" rows="2" @disabled(! $magBewerken)>{{ old('verdediging_commissieleden', $scriptie->verdediging_commissieleden) }}</textarea></div>
    <div class="sis-fld-row sis-fld-row--2">
        <div class="sis-fld"><label>Duur van de presentatie (minuten)</label><input type="number" min="0" max="600" name="verdediging_duur_presentatie" value="{{ old('verdediging_duur_presentatie', $scriptie->verdediging_duur_presentatie) }}" @disabled(! $magBewerken)></div>
        <div class="sis-fld"><label>Duur van de vragenronde (minuten)</label><input type="number" min="0" max="600" name="verdediging_duur_vragen" value="{{ old('verdediging_duur_vragen', $scriptie->verdediging_duur_vragen) }}" @disabled(! $magBewerken)></div>
    </div>
    <div class="sis-fld"><label>Feedback</label><textarea name="verdediging_feedback" rows="3" @disabled(! $magBewerken)>{{ old('verdediging_feedback', $scriptie->verdediging_feedback) }}</textarea></div>
    <div class="sis-fld"><label>Eindbesluit</label><input type="text" name="verdediging_eindbesluit" value="{{ old('verdediging_eindbesluit', $scriptie->verdediging_eindbesluit) }}" @disabled(! $magBewerken)></div>
    @if ($magBewerken)
        <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Verdediging opslaan</button></div></div>
    @endif
</form>

@include('scriptie.tabs._documenten', ['categorie' => 'presentatie', 'titel' => 'Presentatiebestand'])
