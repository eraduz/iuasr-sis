@php
    $gebruiker = auth()->user();
    $stand = $scriptie->stand($stap);
    $magBewerken = $scriptie->magStapBewerken($gebruiker, $stap);
    $magAfvinken = $stap->magAfvinkenDoor($gebruiker) && ($scriptie->isLopend() || $gebruiker->heeftRol(App\Enums\Rol::Beheerder));
@endphp

<div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin-bottom:12px;">
    <span><b>Stap {{ $stap->volgorde() }} — {{ $stap->label() }}.</b> {{ $stap->omschrijving() }} Verantwoordelijk: <b>{{ $stap->verantwoordelijke()->label() }}</b>.</span>
</div>

<div class="sis-card" style="margin-bottom:12px;">
    <div class="sis-card__hd">
        <h3>Status van de stap</h3>
        <span class="iuasr-dash-status {{ $stand?->badge() ?? 's-draft' }}">{{ $stand?->statusLabel() ?? '—' }}</span>
    </div>
    <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;">
        @if ($magBewerken)
            <form method="POST" action="{{ route('scriptie.stap.status', ['scriptie' => $scriptie, 'stap' => $stap->value]) }}" class="sis-form" style="flex:1;min-width:280px;">
                @csrf @method('PUT')
                <div class="sis-fld">
                    <label>Status</label>
                    <select name="status">
                        @foreach ($stap->statussen() as $sleutel => $label)
                            <option value="{{ $sleutel }}" @selected(($stand?->status) === $sleutel)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sis-fld">
                    <label>Opmerking</label>
                    <textarea name="opmerking" rows="2">{{ $stand?->opmerking }}</textarea>
                </div>
                <div class="sis-form__actions" style="margin-top:8px;"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Status opslaan</button></div></div>
            </form>
        @else
            <div style="flex:1;min-width:220px;">
                <div class="sis-dl"><dt>Status</dt><dd>{{ $stand?->statusLabel() ?? '—' }}</dd></div>
                @if ($stand?->opmerking)
                    <div class="sis-dl"><dt>Opmerking</dt><dd>{{ $stand->opmerking }}</dd></div>
                @endif
            </div>
        @endif

        <div style="min-width:220px;">
            @if ($stand?->gereed)
                <p class="sis-muted" style="margin:0 0 8px;">✓ Afgevinkt op {{ $stand->gereed_op?->format('d-m-Y') }}@if ($stand->gereedDoor) door {{ $stand->gereedDoor->naam }}@endif</p>
            @endif
            @if ($magAfvinken)
                <form method="POST" action="{{ route('scriptie.stap.afvinken', ['scriptie' => $scriptie, 'stap' => $stap->value]) }}">
                    @csrf
                    <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--sm {{ $stand?->gereed ? '' : 'iuasr-dash-btn--primary' }}">
                        {{ $stand?->gereed ? 'Stap heropenen' : 'Stap afvinken' }}
                    </button>
                </form>
                <p class="sis-muted" style="font-size:11px;margin:6px 0 0;">De stappen worden op volgorde afgevinkt.</p>
            @endif
        </div>
    </div>
</div>
