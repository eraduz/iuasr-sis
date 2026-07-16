@extends('layouts.app')

@section('titel', 'Scriptie '.$scriptie->scriptienummer)

@section('inhoud')
@php
    $gebruiker = auth()->user();
    $magBeheer = $gebruiker->magScriptieBeheren();
    $geldigeTabs = collect($stappen)->map(fn ($s) => $s->value)->all();
    $actief = in_array(request('tab'), $geldigeTabs, true) ? request('tab') : $stappen[0]->value;
@endphp

<div class="sis-crumb">
    <a href="{{ route('scriptie.dashboard') }}">Scriptie Coördinatie</a><span class="sep">›</span>
    <a href="{{ route('scriptie.trajecten') }}">Trajecten</a><span class="sep">›</span>
    <b>{{ $scriptie->scriptienummer }}</b>
</div>

<div class="iuasr-dash-vhead">
    <div>
        <h1>{{ $scriptie->titelWeergave() }}</h1>
        <div class="summary">
            {{ $scriptie->student?->volledigeNaam() }} · {{ $scriptie->student?->studentnummer }} ·
            {{ $scriptie->opleiding?->naam }} ·
            <span class="iuasr-dash-status {{ $scriptie->isAfgerond() ? 's-approved' : ($scriptie->isAfgebroken() ? 's-rejected' : 's-submitted') }}">{{ $scriptie->statusLabel() }}</span>
        </div>
    </div>
    <div class="iuasr-dash-vhead__actions">
        <div class="summary">Voortgang <b>{{ $scriptie->aantalGereed() }}/{{ count($stappen) }}</b> stappen</div>
    </div>
</div>

{{-- Kerngegevens --}}
<div class="sis-card">
    <div class="sis-card__hd"><h3>Kerngegevens</h3><span class="hint">{{ $scriptie->scriptienummer }}</span></div>
    <form method="POST" action="{{ route('scriptie.kern.update', $scriptie) }}" class="sis-form">
        @csrf @method('PUT')
        <div class="sis-fld-row sis-fld-row--2">
            <div class="sis-fld">
                <label>Voorlopige titel</label>
                <input type="text" name="titel_voorlopig" value="{{ old('titel_voorlopig', $scriptie->titel_voorlopig) }}" @disabled(! $magBeheer)>
            </div>
            <div class="sis-fld">
                <label>Definitieve titel</label>
                <input type="text" name="titel_definitief" value="{{ old('titel_definitief', $scriptie->titel_definitief) }}" @disabled(! $magBeheer)>
            </div>
        </div>
        <div class="sis-fld-row sis-fld-row--2">
            <div class="sis-fld">
                <label>Taal van de scriptie</label>
                <input type="text" name="taal" value="{{ old('taal', $scriptie->taal) }}" placeholder="Nederlands, Engels, Arabisch, …" @disabled(! $magBeheer)>
            </div>
            <div class="sis-fld">
                <label>Scriptiebegeleider</label>
                <select name="begeleider_id" @disabled(! $magBeheer)>
                    <option value="">— nog niet toegewezen —</option>
                    @foreach ($docenten as $docent)
                        <option value="{{ $docent->id }}" @selected(old('begeleider_id', $scriptie->begeleider_id) == $docent->id)>{{ $docent->volledigeNaam() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @if ($magBeheer)
            <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Kern opslaan</button></div></div>
        @endif
    </form>
</div>

{{-- Tabbladen: één per stap --}}
<div class="sis-tabs" role="tablist" style="margin-top:16px;">
    @foreach ($stappen as $stap)
        @php $stand = $scriptie->stand($stap); @endphp
        <button class="sis-tab {{ $actief === $stap->value ? 'is-active' : '' }}" data-tab="{{ $stap->value }}" role="tab">
            {{ $stap->volgorde() }}. {{ $stap->label() }}
            @if ($stand?->gereed)
                <span aria-hidden="true" title="Afgevinkt" style="color:var(--heritage-groen,#285C4D);font-weight:700;">✓</span>
            @endif
        </button>
    @endforeach
</div>

@foreach ($stappen as $stap)
    <section class="sis-tabpanel {{ $actief === $stap->value ? 'is-active' : '' }}" data-panel="{{ $stap->value }}">
        @include('scriptie.tabs._'.$stap->value, ['scriptie' => $scriptie, 'stap' => $stap])
    </section>
@endforeach

@if ($magBeheer && $scriptie->isLopend())
    <div class="sis-card" style="margin-top:16px;border-color:var(--secColor100);">
        <div class="sis-card__hd"><h3>Traject afbreken</h3></div>
        <form method="POST" action="{{ route('scriptie.afbreken', $scriptie) }}" class="sis-form"
              onsubmit="return confirm('Weet u zeker dat u dit scriptietraject wilt afbreken?');">
            @csrf
            <div class="sis-fld">
                <label>Reden (optioneel)</label>
                <input type="text" name="reden" maxlength="255">
            </div>
            <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--danger" type="submit">Traject afbreken</button></div></div>
        </form>
    </div>
@endif
@endsection

@push('scripts')
<script>
    document.querySelectorAll('.sis-tab').forEach(function (t) {
        t.addEventListener('click', function () {
            if (t.classList.contains('is-locked')) return;
            var key = t.getAttribute('data-tab');
            document.querySelectorAll('.sis-tab').forEach(function (x) { x.classList.remove('is-active'); });
            t.classList.add('is-active');
            document.querySelectorAll('.sis-tabpanel').forEach(function (p) {
                p.classList.toggle('is-active', p.getAttribute('data-panel') === key);
            });
            if (history.replaceState) {
                var url = new URL(window.location);
                url.searchParams.set('tab', key);
                history.replaceState(null, '', url);
            }
        });
    });
</script>
@endpush
