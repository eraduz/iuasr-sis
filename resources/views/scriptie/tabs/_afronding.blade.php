@php $magBewerken = $scriptie->magStapBewerken(auth()->user(), $stap); @endphp
@include('scriptie.tabs._kop')
@include('scriptie.tabs._checklist', ['checklistTitel' => 'Afrondingschecklist'])
<form method="POST" action="{{ route('scriptie.stap.update', ['scriptie' => $scriptie, 'stap' => $stap->value]) }}" class="sis-card sis-form">
    @csrf @method('PUT')
    <div class="sis-card__hd"><h3>Afronding van het scriptietraject</h3></div>
    <dl class="sis-dl">
        <dt>Eindcijfer</dt><dd>{{ $scriptie->definitief_cijfer !== null ? number_format((float) $scriptie->definitief_cijfer, 1, ',', '') : '—' }}</dd>
        <dt>Traject</dt><dd><span class="iuasr-dash-status {{ $scriptie->isAfgerond() ? 's-approved' : ($scriptie->isAfgebroken() ? 's-rejected' : 's-submitted') }}">{{ $scriptie->statusLabel() }}</span></dd>
    </dl>
    <div class="sis-fld"><label>Datum van archivering</label><input type="date" name="gearchiveerd_op" value="{{ old('gearchiveerd_op', $scriptie->gearchiveerd_op?->format('Y-m-d')) }}" @disabled(! $magBewerken)></div>
    @if ($magBewerken)
        <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div></div>
    @endif
    <p class="sis-muted" style="margin-top:8px;">Vink de laatste stap ‘Afronding’ af (bovenaan) om het scriptietraject definitief af te ronden. De eindstatus legt u vast via de statuskeuze.</p>
</form>
