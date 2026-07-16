@extends('layouts.app')

@section('titel', 'Scriptie Kandidaten')

@section('inhoud')
@php $magBeheer = auth()->user()->magScriptieBeheren(); @endphp
<div class="sis-crumb"><a href="{{ route('scriptie.dashboard') }}">Scriptie Coördinatie</a><span class="sep">›</span><b>Kandidaten</b></div>

<div class="iuasr-dash-vhead">
    <div>
        <h1>Scriptie Kandidaten</h1>
        <div class="summary">Actieve studenten die aan de toelatingseisen voldoen (≥ {{ (int) config('sis.scriptie.toelating_ec', 180) }} EC · Methoden en Technieken I &amp; II) en nog geen traject hebben</div>
    </div>
</div>

<form method="GET" class="sis-toolbar">
    <select name="opleiding" data-autofilter>
        <option value="">Alle opleidingen</option>
        @foreach ($opleidingen as $opl)
            <option value="{{ $opl->id }}" @selected((string) $gekozenOpleiding === (string) $opl->id)>{{ $opl->naam }}</option>
        @endforeach
    </select>
    <label class="sis-check-inline"><input type="checkbox" name="alles" value="1" data-autofilter @checked($toonAlles)> Ook wie (nog) niet voldoet</label>
    <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Filteren</button>
</form>

<div class="iuasr-dash-tbl-card">
    <table class="iuasr-dash-tbl">
        <thead><tr>
            <th style="width:110px;">Studentnr.</th><th>Naam</th><th>Opleiding</th>
            <th>Behaalde EC</th><th>M&amp;T I</th><th>M&amp;T II</th><th>Toelating</th><th class="row-act"></th>
        </tr></thead>
        <tbody>
            @forelse ($kandidaten as $rij)
                @php $t = $rij['toelating']; $student = $rij['student']; $insch = $rij['inschrijving']; @endphp
                <tr>
                    <td class="tnum">{{ $student->studentnummer }}</td>
                    <td class="nm">{{ $student->volledigeNaam() }}</td>
                    <td>{{ $insch->opleiding?->code }}</td>
                    <td class="tnum">{{ $t['ec'] }} / {{ (int) $t['ec_norm'] }} @if (! $t['ec_voldaan'])<span class="iuasr-dash-status s-rejected">te weinig</span>@endif</td>
                    <td>{!! $t['mt1'] ? '<span class="iuasr-dash-status s-approved">ja</span>' : '<span class="iuasr-dash-status s-rejected">nee</span>' !!}</td>
                    <td>{!! $t['mt2'] ? '<span class="iuasr-dash-status s-approved">ja</span>' : '<span class="iuasr-dash-status s-rejected">nee</span>' !!}</td>
                    <td>{!! $t['voldoet'] ? '<span class="iuasr-dash-status s-approved">voldoet</span>' : '<span class="iuasr-dash-status s-docs">voldoet niet</span>' !!}</td>
                    <td class="row-act">
                        @if ($magBeheer)
                            <form method="POST" action="{{ route('scriptie.start', $insch) }}" style="display:inline;"
                                  @unless ($t['voldoet']) onsubmit="return confirm('Deze student voldoet (nog) niet aan alle toelatingseisen. Toch een traject starten?');" @endunless>
                                @csrf
                                <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary">Traject starten</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen kandidaten</h3><p class="sis-muted">Er zijn nu geen studenten die aan de toelatingseisen voldoen (of allen hebben al een traject). Vink ‘Ook wie nog niet voldoet’ aan om iedereen te tonen.</p></div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<p class="sis-tblnote">De toelatingscontrole gebruikt de behaalde EC en de resultaten van Methoden en Technieken I en II (op vakcode). Alleen opleidingen met een scriptie worden getoond.</p>
@endsection
