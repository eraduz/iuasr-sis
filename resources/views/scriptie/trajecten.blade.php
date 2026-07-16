@extends('layouts.app')

@section('titel', 'Scriptietrajecten')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('scriptie.dashboard') }}">Scriptie Coördinatie</a><span class="sep">›</span><b>Trajecten</b></div>

<div class="iuasr-dash-vhead">
    <div>
        <h1>Scriptietrajecten</h1>
        <div class="summary">{{ $trajecten->total() }} trajecten</div>
    </div>
    <div class="iuasr-dash-vhead__actions">
        @if (auth()->user()->magScriptieBeheren())
            <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('scriptie.kandidaten') }}">Scriptie Kandidaten</a>
        @endif
    </div>
</div>

<form method="GET" class="sis-toolbar">
    <input type="text" name="q" value="{{ $q }}" placeholder="Zoek op nummer, titel of student…">
    <select name="status" data-autofilter>
        <option value="">Alle statussen</option>
        <option value="lopend" @selected($status === 'lopend')>Lopend</option>
        <option value="afgerond" @selected($status === 'afgerond')>Afgerond</option>
        <option value="afgebroken" @selected($status === 'afgebroken')>Afgebroken</option>
    </select>
    <select name="opleiding" data-autofilter>
        <option value="">Alle opleidingen</option>
        @foreach ($opleidingen as $opl)
            <option value="{{ $opl->id }}" @selected((string) $opleiding === (string) $opl->id)>{{ $opl->naam }}</option>
        @endforeach
    </select>
    <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Filteren</button>
    <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('scriptie.trajecten') }}">Wissen</a>
</form>

<div class="iuasr-dash-tbl-card">
    <table class="iuasr-dash-tbl">
        <thead><tr>
            <th style="width:100px;">Scriptienr.</th><th>Titel</th><th>Student</th><th>Opleiding</th>
            <th>Begeleider</th><th>Huidige stap</th><th>Voortgang</th><th>Status</th><th class="row-act"></th>
        </tr></thead>
        <tbody>
            @forelse ($trajecten as $s)
                <tr>
                    <td class="tnum">{{ $s->scriptienummer }}</td>
                    <td class="nm">{{ \Illuminate\Support\Str::limit($s->titelWeergave(), 50) }}</td>
                    <td>{{ $s->student?->volledigeNaam() }}</td>
                    <td>{{ $s->opleiding?->code }}</td>
                    <td>{{ $s->begeleider?->volledigeNaam() ?? '—' }}</td>
                    <td>{{ $s->huidigeStap()?->label() ?? '—' }}</td>
                    <td class="tnum">{{ $s->aantalGereed() }}/11</td>
                    <td><span class="iuasr-dash-status {{ $s->isAfgerond() ? 's-approved' : ($s->isAfgebroken() ? 's-rejected' : 's-submitted') }}">{{ $s->statusLabel() }}</span></td>
                    <td class="row-act"><a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('scriptie.show', $s) }}">Openen</a></td>
                </tr>
            @empty
                <tr><td colspan="9"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen trajecten</h3><p class="sis-muted">Er zijn nog geen scriptietrajecten die aan de filters voldoen.</p></div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $trajecten->links() }}
@endsection
