@php $magBewerken = $scriptie->magStapBewerken(auth()->user(), $stap); @endphp
@include('scriptie.tabs._kop')
@include('scriptie.tabs._checklist', ['checklistTitel' => 'Inleverchecklist'])
<form method="POST" action="{{ route('scriptie.stap.update', ['scriptie' => $scriptie, 'stap' => $stap->value]) }}" class="sis-card sis-form">
    @csrf @method('PUT')
    <div class="sis-card__hd"><h3>Inlevering</h3></div>
    <div class="sis-fld"><label>Datum definitieve inlevering</label><input type="date" name="definitief_ingeleverd_op" value="{{ old('definitief_ingeleverd_op', $scriptie->definitief_ingeleverd_op?->format('Y-m-d')) }}" @disabled(! $magBewerken)></div>
    @if ($magBewerken)
        <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div></div>
    @endif
</form>

@include('scriptie.tabs._documenten', ['categorie' => 'eindversie', 'titel' => 'Ingeleverde scriptie (eindversie)'])
