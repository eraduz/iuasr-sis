@php $magBewerken = $scriptie->magStapBewerken(auth()->user(), $stap); @endphp
@include('scriptie.tabs._kop')
<form method="POST" action="{{ route('scriptie.stap.update', ['scriptie' => $scriptie, 'stap' => $stap->value]) }}" class="sis-card sis-form">
    @csrf @method('PUT')
    <div class="sis-card__hd"><h3>Gegevens van het scriptievoorstel</h3></div>
    <div class="sis-fld-row sis-fld-row--2">
        <div class="sis-fld"><label>Voorlopige scriptietitel</label><input type="text" name="titel_voorlopig" value="{{ old('titel_voorlopig', $scriptie->titel_voorlopig) }}" @disabled(! $magBewerken)></div>
        <div class="sis-fld"><label>Taal van de scriptie</label><input type="text" name="taal" value="{{ old('taal', $scriptie->taal) }}" @disabled(! $magBewerken)></div>
    </div>
    <div class="sis-fld"><label>Onderwerp uit de onderwerpenlijst</label><input type="text" name="voorstel_onderwerp_keuze" value="{{ old('voorstel_onderwerp_keuze', $scriptie->voorstel_onderwerp_keuze) }}" @disabled(! $magBewerken)></div>
    <div class="sis-fld"><label>Eigen voorstel voor een onderwerp</label><textarea name="voorstel_onderwerp_eigen" rows="2" @disabled(! $magBewerken)>{{ old('voorstel_onderwerp_eigen', $scriptie->voorstel_onderwerp_eigen) }}</textarea></div>
    <div class="sis-fld"><label>Korte omschrijving van het onderwerp</label><textarea name="voorstel_omschrijving" rows="3" @disabled(! $magBewerken)>{{ old('voorstel_omschrijving', $scriptie->voorstel_omschrijving) }}</textarea></div>
    <div class="sis-fld"><label>Aanleiding voor het onderzoek</label><textarea name="voorstel_aanleiding" rows="3" @disabled(! $magBewerken)>{{ old('voorstel_aanleiding', $scriptie->voorstel_aanleiding) }}</textarea></div>
    <div class="sis-fld"><label>Voorlopige probleemstelling</label><textarea name="voorstel_probleemstelling" rows="3" @disabled(! $magBewerken)>{{ old('voorstel_probleemstelling', $scriptie->voorstel_probleemstelling) }}</textarea></div>
    <div class="sis-fld"><label>Voorlopige hoofdvraag</label><textarea name="voorstel_hoofdvraag" rows="2" @disabled(! $magBewerken)>{{ old('voorstel_hoofdvraag', $scriptie->voorstel_hoofdvraag) }}</textarea></div>
    <div class="sis-fld-row sis-fld-row--2">
        <div class="sis-fld"><label>Beoogde doelgroep</label><input type="text" name="voorstel_doelgroep" value="{{ old('voorstel_doelgroep', $scriptie->voorstel_doelgroep) }}" @disabled(! $magBewerken)></div>
        <div class="sis-fld"><label>Voorkeur voor een scriptiebegeleider</label><input type="text" name="voorstel_voorkeur_begeleider" value="{{ old('voorstel_voorkeur_begeleider', $scriptie->voorstel_voorkeur_begeleider) }}" @disabled(! $magBewerken)></div>
    </div>
    <div class="sis-fld"><label class="sis-check-inline"><input type="checkbox" name="voorstel_contact_begeleider" value="1" @checked(old('voorstel_contact_begeleider', $scriptie->voorstel_contact_begeleider)) @disabled(! $magBewerken)> Er is al contact geweest met de begeleider</label></div>
    @if ($magBewerken)
        <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Voorstel opslaan</button></div></div>
    @endif
</form>
