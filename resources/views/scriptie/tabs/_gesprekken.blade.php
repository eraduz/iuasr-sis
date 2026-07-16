@php
    $gebruiker = auth()->user();
    $magGesprek = $scriptie->magStapBewerken($gebruiker, App\Enums\Scriptiestap::PlanVanAanpak);
    $gesprekken = $scriptie->gesprekken;
@endphp
<div class="sis-card" style="margin-bottom:12px;">
    <div class="sis-card__hd"><h3>Begeleidingsgesprekken</h3><span class="hint">{{ $gesprekken->count() }} geregistreerd</span></div>

    @forelse ($gesprekken as $g)
        <div class="sis-card" style="margin:0 0 10px;">
            <div class="sis-card__hd">
                <h3 style="font-size:15px;">{{ $g->datum?->format('d-m-Y') }}@if ($g->begintijd) · {{ substr($g->begintijd, 0, 5) }}@if ($g->eindtijd)–{{ substr($g->eindtijd, 0, 5) }}@endif @endif</h3>
                <span class="iuasr-dash-status {{ $g->badge() }}">{{ $g->statusLabel() }}</span>
            </div>
            <dl class="sis-dl">
                @if ($g->onderwerp)<dt>Onderwerp</dt><dd>{{ $g->onderwerp }}</dd>@endif
                <dt>Vorm</dt><dd>{{ $g->online ? 'Online' : 'Fysiek' }}@if ($g->locatie) · {{ $g->locatie }}@endif</dd>
                @if ($g->besproken)<dt>Besproken</dt><dd>{{ $g->besproken }}</dd>@endif
                @if ($g->feedback)<dt>Feedback</dt><dd>{{ $g->feedback }}</dd>@endif
                @if ($g->afspraken)<dt>Afspraken</dt><dd>{{ $g->afspraken }}</dd>@endif
                @if ($g->actiepunten_student)<dt>Actie student</dt><dd>{{ $g->actiepunten_student }}</dd>@endif
                @if ($g->actiepunten_begeleider)<dt>Actie begeleider</dt><dd>{{ $g->actiepunten_begeleider }}</dd>@endif
                @if ($g->actiepunten_deadline)<dt>Deadline actie</dt><dd>{{ $g->actiepunten_deadline?->format('d-m-Y') }}</dd>@endif
                <dt>Bevestigd</dt><dd>Student: {{ $g->bevestigd_student ? 'ja' : 'nee' }} · Begeleider: {{ $g->bevestigd_begeleider ? 'ja' : 'nee' }}</dd>
            </dl>
            @if ($magGesprek)
                <form method="POST" action="{{ route('scriptie.gesprek.destroy', ['scriptie' => $scriptie, 'gesprek' => $g]) }}" onsubmit="return confirm('Gesprek verwijderen?');">@csrf @method('DELETE')<button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">Verwijderen</button></form>
            @endif
        </div>
    @empty
        <p class="sis-muted">Nog geen begeleidingsgesprekken geregistreerd.</p>
    @endforelse

    @if ($magGesprek)
        <details style="margin-top:10px;">
            <summary class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" style="cursor:pointer;list-style:none;">+ Gesprek registreren</summary>
            <form method="POST" action="{{ route('scriptie.gesprek.store', $scriptie) }}" class="sis-form" style="margin-top:10px;">
                @csrf
                <div class="sis-fld-row sis-fld-row--3">
                    <div class="sis-fld"><label>Datum <span class="req">*</span></label><input type="date" name="datum" value="{{ now()->toDateString() }}" required></div>
                    <div class="sis-fld"><label>Begintijd</label><input type="time" name="begintijd"></div>
                    <div class="sis-fld"><label>Eindtijd</label><input type="time" name="eindtijd"></div>
                </div>
                <div class="sis-fld-row sis-fld-row--2">
                    <div class="sis-fld"><label>Locatie</label><input type="text" name="locatie"></div>
                    <div class="sis-fld"><label>Status</label><select name="status">@foreach (App\Models\ScriptieGesprek::STATUSSEN as $s => $l)<option value="{{ $s }}" @selected($s === 'gepland')>{{ $l }}</option>@endforeach</select></div>
                </div>
                <div class="sis-fld"><label class="sis-check-inline"><input type="checkbox" name="online" value="1"> Online gesprek</label></div>
                <div class="sis-fld"><label>Onderwerp</label><input type="text" name="onderwerp"></div>
                <div class="sis-fld"><label>Besproken punten</label><textarea name="besproken" rows="2"></textarea></div>
                <div class="sis-fld"><label>Feedback van de begeleider</label><textarea name="feedback" rows="2"></textarea></div>
                <div class="sis-fld"><label>Afspraken</label><textarea name="afspraken" rows="2"></textarea></div>
                <div class="sis-fld-row sis-fld-row--2">
                    <div class="sis-fld"><label>Actiepunten student</label><textarea name="actiepunten_student" rows="2"></textarea></div>
                    <div class="sis-fld"><label>Actiepunten begeleider</label><textarea name="actiepunten_begeleider" rows="2"></textarea></div>
                </div>
                <div class="sis-fld-row sis-fld-row--2">
                    <div class="sis-fld"><label>Deadline actiepunten</label><input type="date" name="actiepunten_deadline"></div>
                    <div class="sis-fld">
                        <label class="sis-check-inline"><input type="checkbox" name="bevestigd_student" value="1"> Bevestigd door student</label>
                        <label class="sis-check-inline"><input type="checkbox" name="bevestigd_begeleider" value="1"> Bevestigd door begeleider</label>
                    </div>
                </div>
                <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Gesprek opslaan</button></div></div>
            </form>
        </details>
    @endif
</div>
