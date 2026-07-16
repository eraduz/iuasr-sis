@php $magBewerken = $scriptie->magStapBewerken(auth()->user(), $stap); @endphp
@include('scriptie.tabs._kop')
<form method="POST" action="{{ route('scriptie.stap.update', ['scriptie' => $scriptie, 'stap' => $stap->value]) }}" class="sis-card sis-form">
    @csrf @method('PUT')
    <div class="sis-card__hd"><h3>Onderdelen van het Plan van Aanpak</h3></div>
    <div class="sis-fld"><label>Aanleiding van het onderzoek</label><textarea name="pva_aanleiding" rows="2" @disabled(! $magBewerken)>{{ old('pva_aanleiding', $scriptie->pva_aanleiding) }}</textarea></div>
    <div class="sis-fld"><label>Probleembeschrijving</label><textarea name="pva_probleembeschrijving" rows="2" @disabled(! $magBewerken)>{{ old('pva_probleembeschrijving', $scriptie->pva_probleembeschrijving) }}</textarea></div>
    <div class="sis-fld"><label>Toegevoegde waarde</label><textarea name="pva_toegevoegde_waarde" rows="2" @disabled(! $magBewerken)>{{ old('pva_toegevoegde_waarde', $scriptie->pva_toegevoegde_waarde) }}</textarea></div>
    <div class="sis-fld"><label>Maatschappelijke relevantie</label><textarea name="pva_maatschappelijke_relevantie" rows="2" @disabled(! $magBewerken)>{{ old('pva_maatschappelijke_relevantie', $scriptie->pva_maatschappelijke_relevantie) }}</textarea></div>
    <div class="sis-fld"><label>Wetenschappelijke of vakinhoudelijke relevantie</label><textarea name="pva_wetenschappelijke_relevantie" rows="2" @disabled(! $magBewerken)>{{ old('pva_wetenschappelijke_relevantie', $scriptie->pva_wetenschappelijke_relevantie) }}</textarea></div>
    <div class="sis-fld"><label>Historische context</label><textarea name="pva_historische_context" rows="2" @disabled(! $magBewerken)>{{ old('pva_historische_context', $scriptie->pva_historische_context) }}</textarea></div>
    <div class="sis-fld"><label>Voorlopig literatuuronderzoek</label><textarea name="pva_literatuuronderzoek" rows="2" @disabled(! $magBewerken)>{{ old('pva_literatuuronderzoek', $scriptie->pva_literatuuronderzoek) }}</textarea></div>
    <div class="sis-fld-row sis-fld-row--2">
        <div class="sis-fld"><label>Doelgroep</label><textarea name="pva_doelgroep" rows="2" @disabled(! $magBewerken)>{{ old('pva_doelgroep', $scriptie->pva_doelgroep) }}</textarea></div>
        <div class="sis-fld"><label>Hoofdvraag</label><textarea name="pva_hoofdvraag" rows="2" @disabled(! $magBewerken)>{{ old('pva_hoofdvraag', $scriptie->pva_hoofdvraag) }}</textarea></div>
    </div>
    <div class="sis-fld"><label>Deelvragen</label><textarea name="pva_deelvragen" rows="2" @disabled(! $magBewerken)>{{ old('pva_deelvragen', $scriptie->pva_deelvragen) }}</textarea></div>
    <div class="sis-fld-row sis-fld-row--2">
        <div class="sis-fld"><label>Methode van gegevensverzameling</label><textarea name="pva_methode_verzameling" rows="2" @disabled(! $magBewerken)>{{ old('pva_methode_verzameling', $scriptie->pva_methode_verzameling) }}</textarea></div>
        <div class="sis-fld"><label>Methode van data-analyse</label><textarea name="pva_methode_analyse" rows="2" @disabled(! $magBewerken)>{{ old('pva_methode_analyse', $scriptie->pva_methode_analyse) }}</textarea></div>
    </div>
    <div class="sis-fld"><label>Planning</label><textarea name="pva_planning" rows="2" @disabled(! $magBewerken)>{{ old('pva_planning', $scriptie->pva_planning) }}</textarea></div>
    <div class="sis-fld"><label>Risico's en haalbaarheid</label><textarea name="pva_risicos" rows="2" @disabled(! $magBewerken)>{{ old('pva_risicos', $scriptie->pva_risicos) }}</textarea></div>
    <div class="sis-fld"><label>Voorlopige literatuurlijst</label><textarea name="pva_literatuurlijst" rows="3" @disabled(! $magBewerken)>{{ old('pva_literatuurlijst', $scriptie->pva_literatuurlijst) }}</textarea></div>
    @if ($magBewerken)
        <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Plan van Aanpak opslaan</button></div></div>
    @endif
</form>

@include('scriptie.tabs._documenten', ['categorie' => 'plan_van_aanpak', 'titel' => 'Document Plan van Aanpak'])

@include('scriptie.tabs._gesprekken')
