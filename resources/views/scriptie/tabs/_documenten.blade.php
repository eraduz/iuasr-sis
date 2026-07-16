@php
    $gebruiker = auth()->user();
    $magDoc = ($scriptie->isLopend()
            && ($gebruiker->magScriptieBeheren() || $gebruiker->isScriptieBegeleider() || $gebruiker->heeftRol(App\Enums\Rol::Examencommissie)))
        || $gebruiker->heeftRol(App\Enums\Rol::Beheerder);
    $lijst = $scriptie->documentenVoor($categorie);
    $catLabel = App\Models\ScriptieDocument::CATEGORIEEN[$categorie] ?? $categorie;
@endphp
<div class="sis-card" style="margin-bottom:12px;">
    <div class="sis-card__hd"><h3>{{ $titel ?? $catLabel }}</h3><span class="hint">Private opslag · gelogd</span></div>
    @if ($lijst->isEmpty())
        <p class="sis-muted">Nog geen bestand geüpload.</p>
    @else
        <div class="iuasr-dash-tbl-card" style="box-shadow:none;border:0;">
            <table class="iuasr-dash-tbl">
                <thead><tr><th>Bestand</th><th style="width:64px;">Versie</th><th>Geüpload</th><th class="row-act"></th></tr></thead>
                <tbody>
                    @foreach ($lijst as $doc)
                        <tr>
                            <td class="nm">{{ $doc->bestandsnaam }} @unless ($doc->isHuidigeVersie())<span class="sis-muted" style="font-size:11px;">(oude versie)</span>@endunless</td>
                            <td class="tnum">v{{ $doc->versie }}</td>
                            <td>{{ $doc->created_at?->format('d-m-Y') }}@if ($doc->geuploadDoor) · {{ $doc->geuploadDoor->naam }}@endif</td>
                            <td class="row-act">
                                <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('scriptie.document.download', ['scriptie' => $scriptie, 'document' => $doc]) }}">Download</a>
                                @if ($magDoc && $doc->isHuidigeVersie())
                                    <form method="POST" action="{{ route('scriptie.document.destroy', ['scriptie' => $scriptie, 'document' => $doc]) }}" style="display:inline;" onsubmit="return confirm('Document verwijderen?');">@csrf @method('DELETE')<button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">Verwijderen</button></form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    @if ($magDoc)
        <form method="POST" action="{{ route('scriptie.document.store', $scriptie) }}" enctype="multipart/form-data" class="sis-form" style="margin-top:10px;">
            @csrf
            <input type="hidden" name="categorie" value="{{ $categorie }}">
            <div class="sis-fld-row sis-fld-row--2">
                <div class="sis-fld"><label>Titel (optioneel)</label><input type="text" name="titel"></div>
                <div class="sis-fld"><label>Bestand <span class="req">*</span></label><input type="file" name="bestand" required></div>
            </div>
            <div class="sis-form__actions"><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Uploaden</button></div></div>
        </form>
    @endif
</div>
