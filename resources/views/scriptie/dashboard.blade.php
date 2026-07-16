@extends('layouts.app')

@section('titel', 'Scriptie Coördinatie')

@section('inhoud')
<div class="sis-crumb"><b>Scriptie Coördinatie</b></div>

<div class="iuasr-dash-vhead">
    <div>
        <h1>Scriptie Coördinatie</h1>
        <div class="summary">Het scriptietraject in elf stappen · van toelating tot afronding</div>
    </div>
    <div class="iuasr-dash-vhead__actions">
        <a class="iuasr-dash-btn" href="{{ route('scriptie.trajecten') }}">Alle trajecten</a>
        @if (auth()->user()->magScriptieBeheren())
            <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('scriptie.kandidaten') }}">Scriptie Kandidaten</a>
        @endif
    </div>
</div>

<div class="iuasr-dash-stats">
    <div class="iuasr-dash-stat"><div class="lbl">Lopende trajecten</div><div class="val">{{ $kpi['lopend'] }}</div></div>
    <div class="iuasr-dash-stat"><div class="lbl">Afgerond</div><div class="val">{{ $kpi['afgerond'] }}</div></div>
    <div class="iuasr-dash-stat"><div class="lbl">Afgebroken</div><div class="val">{{ $kpi['afgebroken'] }}</div></div>
    <div class="iuasr-dash-stat"><div class="lbl">Totaal</div><div class="val">{{ $kpi['totaal'] }}</div></div>
</div>

<div class="sis-grid-2" style="margin-top:16px;">
    <div class="sis-card">
        <div class="sis-card__hd"><h3>Wacht op u</h3><span class="hint">{{ $wachtOpMij->count() }}</span></div>
        @forelse ($wachtOpMij as $s)
            <div style="display:flex;justify-content:space-between;gap:10px;padding:6px 0;border-bottom:1px solid var(--borderSubtleColor);">
                <div>
                    <a href="{{ route('scriptie.show', $s) }}"><b>{{ $s->scriptienummer }}</b></a> · {{ $s->student?->volledigeNaam() }}
                    <div class="sis-muted" style="font-size:12px;">{{ $s->opleiding?->code }} · huidige stap: {{ $s->huidigeStap()?->label() ?? '—' }}</div>
                </div>
                <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('scriptie.show', $s) }}">Openen</a>
            </div>
        @empty
            <p class="sis-muted">Er wacht op dit moment geen traject op uw rol.</p>
        @endforelse
    </div>

    <div class="sis-card">
        <div class="sis-card__hd"><h3>Lopende trajecten per stap</h3></div>
        @forelse ($perStap as $rij)
            <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--borderSubtleColor);">
                <span>{{ $rij['label'] }}</span><b>{{ $rij['aantal'] }}</b>
            </div>
        @empty
            <p class="sis-muted">Geen lopende trajecten.</p>
        @endforelse
    </div>
</div>

<div class="iuasr-dash-tbl-card" style="margin-top:16px;">
    <div class="sis-card__hd" style="padding:12px 14px 0;"><h3>Recent gestart</h3></div>
    <table class="iuasr-dash-tbl">
        <thead><tr><th>Scriptienr.</th><th>Student</th><th>Opleiding</th><th>Begeleider</th><th>Voortgang</th><th class="row-act"></th></tr></thead>
        <tbody>
            @forelse ($recent as $s)
                <tr>
                    <td class="tnum">{{ $s->scriptienummer }}</td>
                    <td class="nm">{{ $s->student?->volledigeNaam() }}</td>
                    <td>{{ $s->opleiding?->code }}</td>
                    <td>{{ $s->begeleider?->volledigeNaam() ?? '—' }}</td>
                    <td class="tnum">{{ $s->aantalGereed() }}/11</td>
                    <td class="row-act"><a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('scriptie.show', $s) }}">Openen</a></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="iuasr-dash-empty" style="border:0;"><h3>Nog geen trajecten</h3><p class="sis-muted">Start een traject via Scriptie Kandidaten.</p></div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
