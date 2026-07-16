@include('scriptie.tabs._kop')
@php
    $norm = (int) config('sis.scriptie.toelating_ec', 180);
    $ecVoldaan = $scriptie->toelating_ec !== null && (float) $scriptie->toelating_ec >= $norm;
@endphp
<div class="sis-card">
    <div class="sis-card__hd"><h3>Controle van de toelatingseisen</h3><span class="hint">momentopname bij de start</span></div>
    <dl class="sis-dl">
        <dt>Behaalde EC</dt>
        <dd>
            {{ \App\Support\Ec::toon($scriptie->toelating_ec) }} / {{ $norm }} EC
            <span class="iuasr-dash-status {{ $ecVoldaan ? 's-approved' : 's-rejected' }}">{{ $ecVoldaan ? 'voldaan' : 'niet voldaan' }}</span>
        </dd>
        <dt>Methoden en Technieken I</dt>
        <dd><span class="iuasr-dash-status {{ $scriptie->toelating_mt1_behaald ? 's-approved' : 's-rejected' }}">{{ $scriptie->toelating_mt1_behaald ? 'behaald' : 'niet behaald' }}</span></dd>
        <dt>Methoden en Technieken II</dt>
        <dd><span class="iuasr-dash-status {{ $scriptie->toelating_mt2_behaald ? 's-approved' : 's-rejected' }}">{{ $scriptie->toelating_mt2_behaald ? 'behaald' : 'niet behaald' }}</span></dd>
    </dl>
    <p class="sis-muted">De toelatingseisen zijn gecontroleerd bij de start van het traject (minimaal {{ $norm }} EC behaald én Methoden en Technieken I en II afgerond). Bij aanvullende documenten of een besluit kan de coördinator de status van deze stap hierboven bijstellen.</p>
</div>
