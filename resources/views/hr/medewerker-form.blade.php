@extends('layouts.app')

@php $titel = $medewerker->exists ? 'Medewerker bewerken' : 'Nieuwe medewerker'; @endphp

@section('titel', $titel)

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('medewerkers') }}">Medewerkers</a><span class="sep">›</span><b>{{ $medewerker->exists ? $medewerker->volledigeNaam() : 'Nieuwe medewerker' }}</b></div>

<div class="iuasr-dash-vhead"><div><h1>{{ $titel }}</h1>@if ($medewerker->exists)<div class="summary">Personeelsnummer <b>{{ $medewerker->personeelsnummer }}</b></div>@endif</div></div>

<form method="POST" action="{{ $medewerker->exists ? route('medewerkers.update', $medewerker) : route('medewerkers.store') }}" class="sis-card sis-form" style="max-width:820px;">
  @csrf
  @if ($medewerker->exists) @method('PUT') @endif

  @if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $errors->first() }}</span></div>
  @endif

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Voornaam <span class="req">*</span></label><input type="text" name="voornaam" value="{{ old('voornaam', $medewerker->voornaam) }}" maxlength="255" required></div>
    <div class="sis-fld"><label>Achternaam <span class="req">*</span></label><input type="text" name="achternaam" value="{{ old('achternaam', $medewerker->achternaam) }}" maxlength="255" required></div>
  </div>
  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Tussenvoegsel</label><input type="text" name="tussenvoegsel" value="{{ old('tussenvoegsel', $medewerker->tussenvoegsel) }}" maxlength="255"></div>
    <div class="sis-fld"><label>Aanhef</label><input type="text" name="aanhef" value="{{ old('aanhef', $medewerker->aanhef) }}" maxlength="20" placeholder="dhr. / mevr. / dr."></div>
  </div>
  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld">
      <label>Soort</label>
      @php $srt = old('soort', $medewerker->soort?->value ?? 'personeel'); @endphp
      <select name="soort">@foreach ($soorten as $s)<option value="{{ $s->value }}" @selected($srt===$s->value)>{{ $s->label() }}</option>@endforeach</select>
      <small class="sis-muted">Vrijwilligers tellen niet mee in de FTE.</small>
    </div>
    <div class="sis-fld"><label>Geboortedatum</label><input type="date" name="geboortedatum" value="{{ old('geboortedatum', $medewerker->geboortedatum?->toDateString()) }}"></div>
  </div>
  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld">
      <label>Status</label>
      @php $st = old('status', $medewerker->status?->value ?? 'actief'); @endphp
      <select name="status" id="mw-status">@foreach ($statussen as $s)<option value="{{ $s->value }}" @selected($st===$s->value)>{{ $s->label() }}</option>@endforeach</select>
    </div>
  </div>

  {{-- Offboarding: alleen relevant bij status 'uit dienst'. De uit-dienstdatum
       sluit ook een vast contract af (dat heeft geen eigen einddatum). --}}
  <div id="mw-uitdienst" class="sis-fld-row sis-fld-row--2" style="{{ $st === 'uit_dienst' ? '' : 'display:none;' }}">
    <div class="sis-fld">
      <label>Uit-dienstdatum <span class="req">*</span></label>
      <input type="date" name="uit_dienst_datum" value="{{ old('uit_dienst_datum', $medewerker->uit_dienst_datum?->toDateString()) }}">
      <small class="sis-muted">Laatste dienstdag. Sluit ook een lopend (vast) contract af.</small>
    </div>
    <div class="sis-fld">
      <label>Reden uitdiensttreding</label>
      <input type="text" name="uit_dienst_reden" value="{{ old('uit_dienst_reden', $medewerker->uit_dienst_reden) }}" maxlength="255" placeholder="bv. eigen verzoek, einde contract, pensioen">
    </div>
  </div>

  @if ($bsnInschakelen)
    <div class="sis-fld"><label>BSN</label><input type="text" name="bsn" value="{{ old('bsn') }}" maxlength="9" inputmode="numeric" placeholder="9 cijfers"><small class="sis-muted">Versleuteld opgeslagen; inzage wordt gelogd (AVG).</small></div>
  @endif

  <div class="sis-card__hd" style="margin:6px 0 8px; padding:0;"><b>Dienstverband &amp; organisatie</b></div>
  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Functie</label>@php $fid = old('functie_id', $medewerker->functie_id); @endphp<select name="functie_id"><option value="">— geen —</option>@foreach ($functies as $f)<option value="{{ $f->id }}" @selected((int)$fid===$f->id)>{{ $f->naam }}</option>@endforeach</select></div>
    <div class="sis-fld"><label>Afdeling</label>@php $aid = old('afdeling_id', $medewerker->afdeling_id); @endphp<select name="afdeling_id"><option value="">— geen —</option>@foreach ($afdelingen as $a)<option value="{{ $a->id }}" @selected((int)$aid===$a->id)>{{ $a->naam }}</option>@endforeach</select></div>
  </div>
  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Leidinggevende (manager)</label>@php $mid = old('manager_id', $medewerker->manager_id); @endphp<select name="manager_id"><option value="">— geen —</option>@foreach ($managers as $mgr)<option value="{{ $mgr->id }}" @selected((int)$mid===$mgr->id)>{{ $mgr->volledigeNaam() }}</option>@endforeach</select></div>
    <div class="sis-fld"><label>Self-service login</label>@php $uid = old('user_id', $medewerker->user_id); @endphp<select name="user_id"><option value="">— geen koppeling —</option>@foreach ($gebruikers as $u)<option value="{{ $u->id }}" @selected((int)$uid===$u->id)>{{ $u->naam }} ({{ $u->rol->label() }})</option>@endforeach</select><small class="sis-muted">Koppeling aan een account voor ‘Mijn HR’.</small></div>
  </div>

  <div class="sis-card__hd" style="margin:6px 0 8px; padding:0;"><b>Contact</b></div>
  <div class="sis-fld"><label>Adres</label><input type="text" name="adres" value="{{ old('adres', $medewerker->adres) }}" maxlength="255"></div>
  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Postcode</label><input type="text" name="postcode" value="{{ old('postcode', $medewerker->postcode) }}" maxlength="12"></div>
    <div class="sis-fld"><label>Woonplaats</label><input type="text" name="woonplaats" value="{{ old('woonplaats', $medewerker->woonplaats) }}" maxlength="255"></div>
  </div>
  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Telefoon</label><input type="text" name="telefoon" value="{{ old('telefoon', $medewerker->telefoon) }}" maxlength="30"></div>
    <div class="sis-fld"><label>E-mail (werk)</label><input type="email" name="email" value="{{ old('email', $medewerker->email) }}" maxlength="255"></div>
  </div>
  <div class="sis-fld"><label>E-mail (privé)</label><input type="email" name="email_prive" value="{{ old('email_prive', $medewerker->email_prive) }}" maxlength="255"></div>

  <div class="sis-fld"><label>Opmerkingen</label><textarea name="opmerkingen" rows="2">{{ old('opmerkingen', $medewerker->opmerkingen) }}</textarea></div>
  <div class="sis-fld"><label class="sis-check-inline"><input type="checkbox" name="actief" value="1" @checked(old('actief', $medewerker->actief ?? true))> Actief</label></div>

  <div class="sis-form__actions">
    <a class="iuasr-dash-btn" href="{{ $medewerker->exists ? route('medewerkers.show', $medewerker) : route('medewerkers') }}">Annuleren</a>
    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div>
  </div>
</form>

<script>
  (function () {
    var status = document.getElementById('mw-status');
    var blok = document.getElementById('mw-uitdienst');
    if (!status || !blok) return;
    function sync() { blok.style.display = status.value === 'uit_dienst' ? '' : 'none'; }
    status.addEventListener('change', sync);
    sync();
  })();
</script>
@endsection
