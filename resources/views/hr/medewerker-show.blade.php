@extends('layouts.app')

@section('titel', $medewerker->volledigeNaam())

@section('inhoud')
@php $magBeheer = $medewerker->beheerbaarVoor(auth()->user()); @endphp

<div class="sis-crumb"><a href="{{ route('medewerkers') }}">Medewerkers</a><span class="sep">›</span><b>{{ $medewerker->volledigeNaam() }}</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>{{ $medewerker->volledigeNaam() }}</h1>
    <div class="summary">Personeelsnummer <b>{{ $medewerker->personeelsnummer }}</b> · <span class="iuasr-dash-status {{ $medewerker->status?->badge() }}">{{ $medewerker->status?->label() }}</span>@if($medewerker->fte() !== null) · {{ number_format($medewerker->fte(), 2, ',', '.') }} FTE @endif</div>
  </div>
  @if ($magBeheer)
    <div class="iuasr-dash-vhead__actions"><a class="iuasr-dash-btn" href="{{ route('medewerkers.edit', $medewerker) }}">Bewerken</a></div>
  @endif
</div>

<div class="sis-card" style="margin-bottom:16px;">
  <div class="sis-card__hd"><b>Gegevens</b></div>
  <div style="padding:14px 16px; display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px 24px;">
    <div><small class="sis-muted">Functie</small><div>{{ $medewerker->functie?->naam ?? '—' }}</div></div>
    <div><small class="sis-muted">Afdeling</small><div>{{ $medewerker->afdeling?->naam ?? '—' }}</div></div>
    <div><small class="sis-muted">Leidinggevende</small><div>{{ $medewerker->manager?->volledigeNaam() ?? '—' }}</div></div>
    <div><small class="sis-muted">Geboortedatum</small><div>{{ $medewerker->geboortedatum?->format('d-m-Y') ?? '—' }}</div></div>
    <div><small class="sis-muted">Telefoon</small><div>{{ $medewerker->telefoon ?? '—' }}</div></div>
    <div><small class="sis-muted">E-mail</small><div>{{ $medewerker->email ?? '—' }}</div></div>
    <div><small class="sis-muted">Adres</small><div>{{ trim(($medewerker->adres ?? '').' '.($medewerker->postcode ?? '').' '.($medewerker->woonplaats ?? '')) ?: '—' }}</div></div>
    <div><small class="sis-muted">Self-service login</small><div>{{ $medewerker->user?->naam ?? '—' }}</div></div>
    @if ($bsnZichtbaar)<div><small class="sis-muted">BSN</small><div>{{ $medewerker->bsn ?? '—' }}</div></div>@endif
  </div>
  @if ($medewerker->opmerkingen)
    <div style="padding:0 16px 14px;"><small class="sis-muted">Opmerkingen</small><div>{{ $medewerker->opmerkingen }}</div></div>
  @endif
</div>

{{-- Dienstverbanden (Fase A). --}}
<div class="sis-card" id="dienstverband" style="margin-bottom:16px;">
  <div class="sis-card__hd" style="display:flex; align-items:center; justify-content:space-between;">
    <b>Dienstverbanden ({{ $medewerker->dienstverbanden->count() }})</b>
    @if ($magBeheer)<a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('dienstverbanden.create', $medewerker) }}">Dienstverband toevoegen</a>@endif
  </div>
  @if ($medewerker->dienstverbanden->isEmpty())
    <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">Nog geen dienstverband vastgelegd.</p></div>
  @else
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Type</th><th>Van</th><th>Tot</th><th style="text-align:right;">Uren/week</th><th style="text-align:right;">FTE</th><th>Functie</th>@if($magBeheer)<th class="row-act"></th>@endif</tr></thead>
      <tbody>
        @foreach ($medewerker->dienstverbanden as $dv)
          <tr @if(! $dv->isLopend()) style="opacity:.55;" @endif>
            <td>{{ $dv->contracttype?->label() }}@if($dv->isLopend()) <span class="iuasr-dash-status s-approved">lopend</span>@endif</td>
            <td class="dt">{{ $dv->startdatum?->format('d-m-Y') }}</td>
            <td class="dt">{{ $dv->einddatum?->format('d-m-Y') ?? 'onbepaald' }}</td>
            <td class="tnum" style="text-align:right;">{{ number_format((float) $dv->uren_per_week, 1, ',', '.') }}</td>
            <td class="tnum" style="text-align:right;">{{ number_format($dv->fte(), 2, ',', '.') }}</td>
            <td>{{ $dv->functie?->naam ?? '—' }}</td>
            @if($magBeheer)
              <td class="row-act" style="white-space:nowrap;">
                <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('dienstverbanden.edit', $dv) }}">Bewerken</a>
                <form method="POST" action="{{ route('dienstverbanden.destroy', $dv) }}" onsubmit="return confirm('Dienstverband verwijderen?');" style="display:inline;">@csrf @method('DELETE')<button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">×</button></form>
              </td>
            @endif
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>

{{-- Documenten (Fase A). --}}
<div class="sis-card" id="documenten" style="margin-bottom:16px;">
  <div class="sis-card__hd"><b>Documenten ({{ $medewerker->documenten->count() }})</b></div>
  <div style="padding:14px 16px;">
    @if ($magBeheer)
      <form method="POST" action="{{ route('hrdocumenten.store', $medewerker) }}" enctype="multipart/form-data" style="margin-bottom:14px;">
        @csrf
        <div class="sis-fld-row sis-fld-row--2">
          <div class="sis-fld"><label>Categorie <span class="req">*</span></label><select name="categorie" required>@foreach (\App\Models\HrDocument::CATEGORIEEN as $w => $l)<option value="{{ $w }}">{{ $l }}</option>@endforeach</select></div>
          <div class="sis-fld"><label>Titel</label><input type="text" name="titel" maxlength="255" placeholder="Optioneel"></div>
        </div>
        <div class="sis-fld-row sis-fld-row--2">
          <div class="sis-fld"><label>Bestand <span class="req">*</span></label><input type="file" name="bestand" required></div>
          <div class="sis-fld" style="align-self:end; text-align:right;"><button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Uploaden</button></div>
        </div>
        <small class="sis-muted">PDF, afbeelding of Office-bestand (max. 16 MB). Op de private schijf; inzage gelogd.</small>
      </form>
    @endif
    @if ($medewerker->documenten->isEmpty())
      <p class="sis-muted" style="margin:0;">Nog geen documenten.</p>
    @else
      <table class="iuasr-dash-tbl">
        <thead><tr><th>Categorie</th><th>Bestand</th><th>Geüpload</th>@if($magBeheer)<th class="row-act"></th>@endif</tr></thead>
        <tbody>
          @foreach ($medewerker->documenten as $doc)
            <tr>
              <td>{{ $doc->categorieLabel() }}@if($doc->titel)<br><small class="sis-muted">{{ $doc->titel }}</small>@endif</td>
              <td class="nm"><a href="{{ route('hrdocumenten.download', $doc) }}">{{ $doc->bestandsnaam }}</a></td>
              <td class="dt"><small>{{ $doc->created_at?->format('d-m-Y') }}<br>{{ $doc->geuploadDoor?->naam }}</small></td>
              @if($magBeheer)<td class="row-act"><form method="POST" action="{{ route('hrdocumenten.destroy', $doc) }}" onsubmit="return confirm('Document verwijderen?');" style="display:inline;">@csrf @method('DELETE')<button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">×</button></form></td>@endif
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>
</div>

{{-- Verlof (Fase B). --}}
<div class="sis-card" id="verlof" style="margin-bottom:16px;">
  <div class="sis-card__hd"><b>Verlof {{ $jaar }}</b></div>
  <div style="padding:14px 16px;">
    <table class="iuasr-dash-tbl" style="margin-bottom:12px;">
      <thead><tr><th>Type</th><th style="text-align:right;">Recht (uren)</th><th style="text-align:right;">Opgenomen</th><th style="text-align:right;">Saldo</th></tr></thead>
      <tbody>
        @foreach ($saldo as $rij)
          <tr>
            <td>{{ $rij['type']->label() }}</td>
            <td class="tnum" style="text-align:right;">{{ number_format($rij['recht'], 1, ',', '.') }}</td>
            <td class="tnum" style="text-align:right;">{{ number_format($rij['opgenomen'], 1, ',', '.') }}</td>
            <td class="tnum" style="text-align:right;">{{ number_format($rij['saldo'], 1, ',', '.') }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>

    @if ($magBeheer)
      <form method="POST" action="{{ route('verlofsaldo.bijwerken', $medewerker) }}" style="margin-bottom:14px;">
        @csrf
        <input type="hidden" name="jaar" value="{{ $jaar }}">
        <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:end;">
          @foreach ($saldo as $rij)
            <div class="sis-fld" style="min-width:150px;"><label>{{ $rij['type']->label() }} (recht)</label><input type="number" step="0.5" min="0" name="recht[{{ $rij['type']->value }}]" value="{{ $rij['recht'] }}"></div>
          @endforeach
          <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Recht opslaan</button>
        </div>
      </form>
    @endif

    @if ($medewerker->verlofaanvragen->isEmpty())
      <p class="sis-muted" style="margin:0;">Nog geen verlofaanvragen.</p>
    @else
      <table class="iuasr-dash-tbl">
        <thead><tr><th>Type</th><th>Van</th><th>Tot</th><th style="text-align:right;">Uren</th><th style="text-align:center;">Status</th><th>Beoordeeld</th>@if($magBeoordelen)<th class="row-act"></th>@endif</tr></thead>
        <tbody>
          @foreach ($medewerker->verlofaanvragen as $a)
            <tr>
              <td>{{ $a->verloftype?->label() }}@if($a->reden)<br><small class="sis-muted">{{ \Illuminate\Support\Str::limit($a->reden, 50) }}</small>@endif</td>
              <td class="dt">{{ $a->van?->format('d-m-Y') }}</td>
              <td class="dt">{{ $a->tot?->format('d-m-Y') }}</td>
              <td class="tnum" style="text-align:right;">{{ number_format((float) $a->uren, 1, ',', '.') }}</td>
              <td style="text-align:center;"><span class="iuasr-dash-status {{ $a->status?->badge() }}">{{ $a->status?->label() }}</span></td>
              <td><small class="sis-muted">{{ $a->beoordelaar?->naam ?? '—' }}@if($a->opmerking_beoordelaar)<br>{{ \Illuminate\Support\Str::limit($a->opmerking_beoordelaar, 40) }}@endif</small></td>
              @if($magBeoordelen)
                <td class="row-act" style="white-space:nowrap;">
                  @if ($a->beoordeelbaarVoor(auth()->user()))
                    <form method="POST" action="{{ route('verlof.beoordelen', $a) }}" style="display:inline;">@csrf<input type="hidden" name="besluit" value="goedgekeurd"><button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Goedkeuren</button></form>
                    <form method="POST" action="{{ route('verlof.beoordelen', $a) }}" style="display:inline;">@csrf<input type="hidden" name="besluit" value="afgewezen"><button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">Afwijzen</button></form>
                  @endif
                </td>
              @endif
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>
</div>

{{-- Verzuim (Fase B). --}}
<div class="sis-card" id="verzuim" style="margin-bottom:16px;">
  <div class="sis-card__hd"><b>Verzuim ({{ $medewerker->ziekmeldingen->count() }})</b></div>
  <div style="padding:14px 16px;">
    @if ($magBeoordelen)
      <form method="POST" action="{{ route('ziekmeldingen.store') }}" style="margin-bottom:14px;">
        @csrf
        <input type="hidden" name="medewerker_id" value="{{ $medewerker->id }}">
        <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:end;">
          <div class="sis-fld" style="min-width:160px;"><label>Ziek vanaf</label><input type="date" name="ziek_van" value="{{ now()->toDateString() }}" required></div>
          <div class="sis-fld" style="min-width:120px;"><label>Percentage</label><input type="number" name="percentage" min="1" max="100" value="100"></div>
          <div class="sis-fld" style="flex:1; min-width:200px;"><label>Opmerking</label><input type="text" name="opmerking" maxlength="1000"></div>
          <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" type="submit">Ziek melden</button>
        </div>
      </form>
    @endif
    @if ($medewerker->ziekmeldingen->isEmpty())
      <p class="sis-muted" style="margin:0;">Geen ziekmeldingen.</p>
    @else
      <table class="iuasr-dash-tbl">
        <thead><tr><th>Ziek van</th><th>Hersteld</th><th style="text-align:right;">%</th><th style="text-align:right;">Dagen</th>@if($magBeoordelen)<th class="row-act"></th>@endif</tr></thead>
        <tbody>
          @foreach ($medewerker->ziekmeldingen as $z)
            <tr>
              <td class="dt">{{ $z->ziek_van?->format('d-m-Y') }}</td>
              <td class="dt">@if($z->hersteld_op){{ $z->hersteld_op->format('d-m-Y') }}@else<span class="iuasr-dash-status s-rejected">ziek</span>@endif</td>
              <td class="tnum" style="text-align:right;">{{ $z->percentage }}%</td>
              <td class="tnum" style="text-align:right;">{{ $z->dagen() }}</td>
              @if($magBeoordelen)
                <td class="row-act">
                  @if ($z->isOpen())
                    <form method="POST" action="{{ route('ziekmeldingen.herstel', $z) }}" style="display:inline; white-space:nowrap;">@csrf<input type="date" name="hersteld_op" value="{{ now()->toDateString() }}" style="width:140px;"><button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Hersteld</button></form>
                  @endif
                </td>
              @endif
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>
</div>

{{-- Gesprekken en onboarding/offboarding volgen in de fasen C en E. --}}
<div class="sis-card">
  <div class="sis-card__hd"><b>Overige onderdelen</b></div>
  <div style="padding:14px 16px;"><p class="sis-muted" style="margin:0;">Gesprekken (beoordelings-/functionerings-/exitgesprek) en onboarding/offboarding verschijnen hier zodra de fasen C en E van de module zijn opgeleverd.</p></div>
</div>
@endsection
