@php
    $punten = $scriptie->checklistVoor($stap);
    $magBewerken = $scriptie->magStapBewerken(auth()->user(), $stap);
@endphp
@if ($punten->isNotEmpty())
<div class="sis-card" style="margin-bottom:12px;">
    <div class="sis-card__hd"><h3>{{ $checklistTitel ?? 'Checklist' }}</h3></div>
    <form method="POST" action="{{ route('scriptie.stap.checklist', ['scriptie' => $scriptie, 'stap' => $stap->value]) }}" class="sis-form">
        @csrf @method('PUT')
        <div class="iuasr-dash-tbl-card" style="box-shadow:none;border:0;">
            <table class="iuasr-dash-tbl">
                <thead><tr>
                    <th>Punt</th>
                    <th style="width:56px;text-align:center;">Ja</th>
                    <th style="width:56px;text-align:center;">Nee</th>
                    <th style="width:64px;text-align:center;">Leeg</th>
                    <th style="width:38%;">Toelichting</th>
                </tr></thead>
                <tbody>
                    @foreach ($punten as $punt)
                        <tr>
                            <td>{{ $punt->label }}</td>
                            <td style="text-align:center;"><input type="radio" name="waarde[{{ $punt->id }}]" value="ja" @checked($punt->waarde === true) @disabled(! $magBewerken)></td>
                            <td style="text-align:center;"><input type="radio" name="waarde[{{ $punt->id }}]" value="nee" @checked($punt->waarde === false) @disabled(! $magBewerken)></td>
                            <td style="text-align:center;"><input type="radio" name="waarde[{{ $punt->id }}]" value="" @checked($punt->waarde === null) @disabled(! $magBewerken)></td>
                            <td><input type="text" name="toelichting[{{ $punt->id }}]" value="{{ $punt->toelichting }}" @disabled(! $magBewerken)></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($magBewerken)
            <div class="sis-form__actions" style="margin-top:8px;"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Checklist opslaan</button></div></div>
        @endif
    </form>
</div>
@endif
