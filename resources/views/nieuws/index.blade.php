@extends('layouts.app')

@section('titel', 'Onderwijsnieuws — bronnen')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Onderwijsnieuws</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Onderwijsnieuws — bronnen</h1>
    <div class="summary">Bronnen voor het nieuwsblok op het bestuursoverzicht. Automatische bronnen worden dagelijks om 23:00 opgehaald; hier kunt u handmatig ophalen en bij handmatige bronnen zelf berichten toevoegen.</div>
  </div>
  <div class="iuasr-dash-vhead__actions">
    <form method="POST" action="{{ route('nieuws.ophalen') }}" style="display:inline;">@csrf
      <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--primary">Nu ophalen</button>
    </form>
  </div>
</div>

<div class="sis-card" style="margin-bottom:18px;">
  <div class="sis-card__hd"><h3>Bronnen</h3><span class="hint">{{ $bronnen->where('actief', true)->count() }} actief · uitgaand verkeer beperkt tot de whitelist</span></div>
  <div class="iuasr-dash-tbl-card" style="border:0;">
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Bron</th><th>Type</th><th>Categorie</th><th>Berichten</th><th>Laatst opgehaald</th><th>Status</th><th class="row-act">Actie</th></tr></thead>
      <tbody>
        @foreach ($bronnen as $bron)
          <tr>
            <td class="nm">{{ $bron->naam }}<small>{{ $bron->host() }}</small></td>
            <td>{{ $bron->type->label() }}</td>
            <td class="dt">{{ $bron->categorie ?? '—' }}</td>
            <td>{{ $bron->berichten_count }}</td>
            <td class="dt">{{ $bron->laatst_opgehaald_op?->format('d-m-Y H:i') ?? '—' }}</td>
            <td>
              @if ($bron->laatste_fout)
                <span class="iuasr-dash-status s-rejected" title="{{ $bron->laatste_fout }}">Fout</span>
              @elseif ($bron->actief)
                <span class="iuasr-dash-status s-approved">Actief</span>
              @else
                <span class="iuasr-dash-status s-draft">Uit</span>
              @endif
            </td>
            <td class="row-act">
              <form method="POST" action="{{ route('nieuws.bron.toggle', $bron) }}" style="display:inline;">@csrf @method('PUT')
                <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--sm">{{ $bron->actief ? 'Deactiveren' : 'Activeren' }}</button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @if ($bronnen->firstWhere('laatste_fout'))
    <p class="sis-tblnote" style="color:var(--secColor100);">Een bron met status <b>Fout</b>: beweeg over "Fout" voor de melding. Vaak een SSL-/CA-bundel op de server (config <code>sis.nieuws.cacert</code>).</p>
  @endif
</div>

@if ($handmatige->isNotEmpty())
  <div class="sis-card" style="margin-bottom:18px;border-left:3px solid var(--priColor300,#D69A2D);">
    <div class="sis-card__hd"><h3>Handmatig bericht toevoegen</h3><span class="hint">Voor bronnen zonder feed (bv. Onderwijsinspectie)</span></div>
    <form method="POST" action="{{ route('nieuws.bericht') }}" style="display:flex;flex-wrap:wrap;gap:12px 16px;align-items:flex-end;">
      @csrf
      <label style="display:flex;flex-direction:column;gap:3px;font-size:12px;">Bron
        <select name="nieuwsbron_id" style="height:32px;font-size:13px;">
          @foreach ($handmatige as $b)<option value="{{ $b->id }}">{{ $b->naam }}</option>@endforeach
        </select>
      </label>
      <label style="display:flex;flex-direction:column;gap:3px;font-size:12px;min-width:260px;flex:1 1 260px;">Titel
        <input type="text" name="titel" maxlength="200" required value="{{ old('titel') }}" style="height:32px;font-size:13px;">
      </label>
      <label style="display:flex;flex-direction:column;gap:3px;font-size:12px;min-width:260px;flex:1 1 260px;">Link (URL)
        <input type="url" name="link" maxlength="700" required value="{{ old('link') }}" style="height:32px;font-size:13px;">
      </label>
      <label style="display:flex;flex-direction:column;gap:3px;font-size:12px;">Datum
        <input type="date" name="gepubliceerd_op" value="{{ old('gepubliceerd_op') }}" style="height:32px;font-size:13px;">
      </label>
      <label style="display:flex;flex-direction:column;gap:3px;font-size:12px;flex:1 1 100%;">Samenvatting (optioneel)
        <input type="text" name="samenvatting" maxlength="300" value="{{ old('samenvatting') }}" style="height:32px;font-size:13px;">
      </label>
      <button type="submit" class="iuasr-dash-btn">Toevoegen</button>
    </form>
    @error('link')<p class="sis-tblnote" style="color:var(--secColor100);">{{ $message }}</p>@enderror
  </div>
@endif

<div class="sis-card">
  <div class="sis-card__hd"><h3>Berichten</h3><span class="hint">Nieuwste eerst · dit is wat het bestuur ziet</span></div>
  <div class="iuasr-dash-tbl-card" style="border:0;">
    <table class="iuasr-dash-tbl">
      <thead><tr><th>Datum</th><th>Titel</th><th>Bron</th><th class="row-act">Actie</th></tr></thead>
      <tbody>
        @forelse ($berichten as $b)
          <tr>
            <td class="dt">{{ $b->gepubliceerd_op?->format('d-m-Y') ?? '—' }}</td>
            <td class="nm"><a href="{{ $b->link }}" target="_blank" rel="noopener noreferrer">{{ $b->titel }}</a>@if($b->samenvatting)<small>{{ $b->samenvatting }}</small>@endif</td>
            <td class="dt">{{ $b->bron?->naam }}</td>
            <td class="row-act">
              <form method="POST" action="{{ route('nieuws.bericht.verwijderen', $b) }}" style="display:inline;" onsubmit="return confirm('Dit bericht verwijderen?');">@csrf @method('DELETE')
                <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger">Verwijderen</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="4"><div class="iuasr-dash-empty" style="border:0;"><h3>Nog geen berichten</h3><p>Klik op <b>Nu ophalen</b> of voeg een handmatig bericht toe.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
