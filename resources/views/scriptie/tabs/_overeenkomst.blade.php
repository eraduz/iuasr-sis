@php
    $magBewerken = $scriptie->magStapBewerken(auth()->user(), $stap);
@endphp
@include('scriptie.tabs._kop')

<form method="POST" action="{{ route('scriptie.stap.update', ['scriptie' => $scriptie, 'stap' => $stap->value]) }}" class="sis-card sis-form">
    @csrf @method('PUT')
    <div class="sis-card__hd"><h3>Inhoud van de overeenkomst</h3></div>
    <dl class="sis-dl">
        <dt>Naam van de student</dt><dd>{{ $scriptie->student?->volledigeNaam() }}</dd>
        <dt>Studentnummer</dt><dd>{{ $scriptie->student?->studentnummer }}</dd>
        <dt>Scriptiebegeleider</dt><dd>{{ $scriptie->begeleider?->volledigeNaam() ?: ($scriptie->begeleider_naam ?: '—') }}</dd>
    </dl>
    <div class="sis-fld-row sis-fld-row--2">
        <div class="sis-fld"><label>Definitieve of voorlopige titel</label><input type="text" name="titel_definitief" value="{{ old('titel_definitief', $scriptie->titel_definitief ?: $scriptie->titel_voorlopig) }}" @disabled(! $magBewerken)></div>
        <div class="sis-fld"><label>Taal van de scriptie</label><input type="text" name="taal" value="{{ old('taal', $scriptie->taal) }}" @disabled(! $magBewerken)></div>
    </div>
    <div class="sis-fld"><label>Onderzoeksvraag</label><textarea name="overeenkomst_onderzoeksvraag" rows="2" @disabled(! $magBewerken)>{{ old('overeenkomst_onderzoeksvraag', $scriptie->overeenkomst_onderzoeksvraag) }}</textarea></div>
    <div class="sis-fld"><label>Namen van de leden van de scriptiecommissie</label><textarea name="overeenkomst_commissieleden" rows="2" @disabled(! $magBewerken)>{{ old('overeenkomst_commissieleden', $scriptie->overeenkomst_commissieleden) }}</textarea></div>
    <div class="sis-fld-row sis-fld-row--2">
        <div class="sis-fld"><label>Studielast</label><input type="text" name="overeenkomst_studielast" value="{{ old('overeenkomst_studielast', $scriptie->overeenkomst_studielast) }}" placeholder="bijv. 15 EC" @disabled(! $magBewerken)></div>
        <div class="sis-fld"><label>Deadline van het Plan van Aanpak</label><input type="date" name="overeenkomst_deadline_pva" value="{{ old('overeenkomst_deadline_pva', $scriptie->overeenkomst_deadline_pva?->format('Y-m-d')) }}" @disabled(! $magBewerken)></div>
    </div>
    <div class="sis-fld-row sis-fld-row--2">
        <div class="sis-fld"><label>Startdatum</label><input type="date" name="overeenkomst_startdatum" value="{{ old('overeenkomst_startdatum', $scriptie->overeenkomst_startdatum?->format('Y-m-d')) }}" @disabled(! $magBewerken)></div>
        <div class="sis-fld"><label>Verwachte einddatum</label><input type="date" name="overeenkomst_einddatum" value="{{ old('overeenkomst_einddatum', $scriptie->overeenkomst_einddatum?->format('Y-m-d')) }}" @disabled(! $magBewerken)></div>
    </div>
    @if ($magBewerken)
        <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Overeenkomst opslaan</button></div></div>
    @endif
</form>

{{-- Digitale goedkeuring (ja/nee per rol) --}}
<form method="POST" action="{{ route('scriptie.goedkeuring', $scriptie) }}" class="sis-card sis-form">
    @csrf @method('PUT')
    <div class="sis-card__hd"><h3>Digitale goedkeuring</h3></div>
    @php
        $rijen = [
            ['student', 'Student', $scriptie->goedkeuring_student, $scriptie->goedkeuring_student_op],
            ['begeleider', 'Scriptiebegeleider', $scriptie->goedkeuring_begeleider, $scriptie->goedkeuring_begeleider_op],
            ['coordinator', 'Scriptiecoördinator', $scriptie->goedkeuring_coordinator, $scriptie->goedkeuring_coordinator_op],
            ['directeur', 'Opleidingsdirecteur', $scriptie->goedkeuring_directeur, $scriptie->goedkeuring_directeur_op],
        ];
    @endphp
    @foreach ($rijen as [$sleutel, $label, $akkoord, $op])
        <div class="sis-fld">
            <label class="sis-check-inline">
                <input type="checkbox" name="goedkeuring_{{ $sleutel }}" value="1" @checked($akkoord) @disabled(! $magBewerken)>
                {{ $label }} @if ($akkoord && $op)<span class="sis-muted" style="font-size:12px;">— akkoord op {{ $op->format('d-m-Y') }}</span>@endif
            </label>
        </div>
    @endforeach
    @if ($magBewerken)
        <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Goedkeuringen opslaan</button></div></div>
    @endif
</form>

{{-- Ondertekende overeenkomst (PDF) --}}
<div class="sis-card">
    <div class="sis-card__hd"><h3>Ondertekende scriptieovereenkomst</h3></div>
    @if ($scriptie->overeenkomstDocument)
        <p class="sis-muted">Gegenereerd · verificatiecode <b>{{ $scriptie->overeenkomstDocument->code }}</b>.</p>
        <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('scriptie.overeenkomst.download', $scriptie) }}">Download PDF</a>
    @else
        <p class="sis-muted">Er is nog geen ondertekende overeenkomst gegenereerd.</p>
    @endif
    @if ($magBewerken)
        <form method="POST" action="{{ route('scriptie.overeenkomst', $scriptie) }}" style="display:inline;margin-left:8px;">
            @csrf
            <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary">{{ $scriptie->overeenkomstDocument ? 'Opnieuw genereren' : 'Overeenkomst genereren' }}</button>
        </form>
        <p class="sis-muted" style="font-size:11px;margin:8px 0 0;">De PDF wordt op het IUASR-briefpapier gegenereerd met een SHA-256-echtheidskenmerk en verificatiecode.</p>
    @endif
</div>
