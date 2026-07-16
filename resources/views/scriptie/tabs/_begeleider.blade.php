@php $magBewerken = $scriptie->magStapBewerken(auth()->user(), $stap); @endphp
@include('scriptie.tabs._kop')
<form method="POST" action="{{ route('scriptie.stap.update', ['scriptie' => $scriptie, 'stap' => $stap->value]) }}" class="sis-card sis-form">
    @csrf @method('PUT')
    <div class="sis-card__hd"><h3>Toewijzing van de scriptiebegeleider</h3><span class="hint">de gekoppelde begeleider staat bij Kerngegevens</span></div>
    <div class="sis-fld-row sis-fld-row--2">
        <div class="sis-fld"><label>Naam van de begeleider</label><input type="text" name="begeleider_naam" value="{{ old('begeleider_naam', $scriptie->begeleider_naam ?: $scriptie->begeleider?->volledigeNaam()) }}" @disabled(! $magBewerken)></div>
        <div class="sis-fld"><label>E-mailadres van de begeleider</label><input type="email" name="begeleider_email" value="{{ old('begeleider_email', $scriptie->begeleider_email ?: $scriptie->begeleider?->email) }}" @disabled(! $magBewerken)></div>
    </div>
    <div class="sis-fld-row sis-fld-row--2">
        <div class="sis-fld"><label>Expertisegebied</label><input type="text" name="begeleider_expertise" value="{{ old('begeleider_expertise', $scriptie->begeleider_expertise) }}" @disabled(! $magBewerken)></div>
        <div class="sis-fld"><label>Datum van toewijzing</label><input type="date" name="begeleider_toegewezen_op" value="{{ old('begeleider_toegewezen_op', $scriptie->begeleider_toegewezen_op?->format('Y-m-d')) }}" @disabled(! $magBewerken)></div>
    </div>
    <div class="sis-fld-row sis-fld-row--2">
        <div class="sis-fld"><label>Verwacht aantal begeleidingsmomenten</label><input type="number" name="begeleiding_aantal_momenten" min="0" max="100" value="{{ old('begeleiding_aantal_momenten', $scriptie->begeleiding_aantal_momenten) }}" @disabled(! $magBewerken)></div>
        <div class="sis-fld"><label>Voorkeurswijze van contact</label><input type="text" name="begeleiding_contactwijze" value="{{ old('begeleiding_contactwijze', $scriptie->begeleiding_contactwijze) }}" placeholder="e-mail, Teams, fysiek, …" @disabled(! $magBewerken)></div>
    </div>
    <div class="sis-fld-row sis-fld-row--2">
        <div class="sis-fld"><label>Beschikbare spreekuren</label><input type="text" name="begeleiding_spreekuren" value="{{ old('begeleiding_spreekuren', $scriptie->begeleiding_spreekuren) }}" @disabled(! $magBewerken)></div>
        <div class="sis-fld"><label>Datum van het eerste gesprek</label><input type="date" name="begeleiding_eerste_gesprek" value="{{ old('begeleiding_eerste_gesprek', $scriptie->begeleiding_eerste_gesprek?->format('Y-m-d')) }}" @disabled(! $magBewerken)></div>
    </div>
    @if ($magBewerken)
        <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Begeleiding opslaan</button></div></div>
    @endif
</form>
