@extends('layouts.app')

@section('titel', 'Taken')

@php
  $ik = auth()->user();
  $naam = fn ($s) => trim($s->voornaam.' '.($s->tussenvoegsel ? $s->tussenvoegsel.' ' : '').$s->achternaam);
@endphp

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Taken</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Taken</h1>
    <div class="summary">Gedeelde takenlijst van Studentenzaken · {{ $taken->total() }} {{ $taken->total() === 1 ? 'taak' : 'taken' }}</div>
  </div>
  <div class="iuasr-dash-vhead__actions">
    <button class="iuasr-dash-btn iuasr-dash-btn--primary" type="button" id="taak-nieuw">Nieuwe taak</button>
  </div>
</div>

@if ($teLaat > 0)
  <div class="iuasr-dash-alert iuasr-dash-alert--warn" style="margin-bottom:16px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    <span><b>{{ $teLaat }}</b> {{ $teLaat === 1 ? 'taak is' : 'taken zijn' }} over de vervaldatum heen.</span>
  </div>
@endif

{{-- Nieuwe taak — standaard ingeklapt, zoals de knop 'Nieuwe taak' in Outlook --}}
<form method="POST" action="{{ route('taken.store') }}" class="sis-card sis-form" id="taak-form" style="margin-bottom:16px;" @if (! $errors->any()) hidden @endif>
  @csrf
  <div class="sis-card__hd"><h3>Nieuwe taak</h3><span class="hint">alleen het onderwerp is verplicht</span></div>

  @if ($errors->any())
    <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:12px;">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>{{ $errors->first() }}</span>
    </div>
  @endif

  <div class="sis-fld"><label>Onderwerp <span class="req">*</span></label>
    <input type="text" name="titel" value="{{ old('titel') }}" maxlength="200" placeholder="Bijv. Diploma opvragen bij student" required>
  </div>

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Begindatum</label><input type="date" name="startdatum" value="{{ old('startdatum') }}"></div>
    <div class="sis-fld"><label>Moet af op</label><input type="date" name="vervaldatum" value="{{ old('vervaldatum', now()->addWeek()->toDateString()) }}"></div>
  </div>

  <div class="sis-fld-row sis-fld-row--2">
    <div class="sis-fld"><label>Prioriteit</label>
      <select name="prioriteit">
        @foreach ($prioriteiten as $waarde => $label)
          <option value="{{ $waarde }}" @selected(old('prioriteit', 'normaal') === $waarde)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="sis-fld"><label>Toegewezen aan</label>
      <select name="toegewezen_aan_id">
        <option value="">— niemand (vrij op te pakken) —</option>
        @foreach ($medewerkers as $m)
          <option value="{{ $m->id }}" @selected(old('toegewezen_aan_id', $ik->id) == $m->id)>{{ $m->naam }}</option>
        @endforeach
      </select>
    </div>
  </div>

  <div class="sis-fld"><label>Student</label>
    <select name="student_id">
      <option value="">— geen student —</option>
      @foreach ($studenten as $s)
        <option value="{{ $s->id }}" @selected(old('student_id') == $s->id)>{{ $s->studentnummer }} — {{ $naam($s) }}</option>
      @endforeach
    </select>
    <span class="sis-muted" style="font-size:11px;">De taak verschijnt dan ook op het dossier van deze student.</span>
  </div>

  <div class="sis-fld"><label>Toelichting</label><textarea name="omschrijving" placeholder="Optioneel">{{ old('omschrijving') }}</textarea></div>

  <div class="sis-form__actions">
    <button class="iuasr-dash-btn" type="button" id="taak-annuleer">Annuleren</button>
    <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Taak opslaan</button></div>
  </div>
</form>

{{-- Filterbalk --}}
<form method="GET" action="{{ route('taken') }}" class="sis-toolbar" style="margin-bottom:12px;">
  <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op onderwerp of studentnummer" style="min-width:260px;">
  <select name="status">
    <option value="openstaand" @selected($status === 'openstaand')>Openstaand</option>
    @foreach ($statussen as $waarde => $label)
      <option value="{{ $waarde }}" @selected($status === $waarde)>{{ $label }}</option>
    @endforeach
    <option value="alle" @selected($status === 'alle')>Alle</option>
  </select>
  <label class="sis-check-inline"><input type="checkbox" name="mijn" value="1" @checked($vanMij)> Alleen mijn taken</label>
  <button class="iuasr-dash-btn iuasr-dash-btn--sm" type="submit">Filteren</button>
  <span class="grow"></span>
  <span class="meta">Te laat: <b>{{ $teLaat }}</b></span>
</form>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead>
      <tr>
        <th style="width:40px;"></th>
        <th>Onderwerp</th>
        <th>Student</th>
        <th>Toegewezen aan</th>
        <th style="text-align:center;">Moet af op</th>
        <th style="text-align:center;">Prioriteit</th>
        <th style="text-align:center;">Status</th>
        <th class="row-act"></th>
      </tr>
    </thead>
    <tbody>
      @forelse ($taken as $taak)
        @php $laat = $taak->isTeLaat(); @endphp
        <tr class="{{ $taak->isAfgerond() ? 'taak-af' : '' }}">
          <td style="text-align:center;">
            <form method="POST" action="{{ route('taken.afronden', $taak) }}" style="display:inline;">
              @csrf
              <button class="sis-taak-vink {{ $taak->isAfgerond() ? 'is-af' : '' }}" type="submit"
                      title="{{ $taak->isAfgerond() ? 'Heropenen' : 'Afronden' }}" aria-label="{{ $taak->isAfgerond() ? 'Heropenen' : 'Afronden' }}">
                @if ($taak->isAfgerond())
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                @endif
              </button>
            </form>
          </td>
          <td class="nm">
            {{ $taak->titel }}
            @if ($taak->prioriteit === App\Enums\TaakPrioriteit::Hoog && ! $taak->isAfgerond())
              <span class="sis-pill-prio">hoog</span>
            @endif
            @if ($taak->omschrijving)<small>{{ Str::limit($taak->omschrijving, 70) }}</small>@endif
          </td>
          <td>
            @if ($taak->student)
              <a href="{{ route('studenten.show', $taak->student) }}">{{ $taak->student->studentnummer }}</a>
            @else
              <span class="sis-muted">—</span>
            @endif
          </td>
          <td>{{ $taak->toegewezenAan?->naam ?? '—' }}</td>
          <td style="text-align:center;" class="dt">
            @if ($taak->vervaldatum)
              {{ $taak->vervaldatum->format('d-m-Y') }}
              <small class="{{ $laat ? 'is-laat' : 'sis-muted' }}" style="display:block;font-size:11px;">{{ $taak->urgentie() }}</small>
            @else
              <span class="sis-muted">—</span>
            @endif
          </td>
          <td style="text-align:center;">{{ $taak->prioriteit->label() }}</td>
          <td style="text-align:center;">
            <span class="iuasr-dash-status {{ $laat ? 's-rejected' : $taak->status->badge() }}">{{ $laat ? 'Te laat' : $taak->status->label() }}</span>
          </td>
          <td class="row-act" style="white-space:nowrap;">
            <button class="iuasr-dash-btn iuasr-dash-btn--sm taak-bewerk" type="button" data-taak="{{ $taak->id }}">Bewerken</button>
            <form method="POST" action="{{ route('taken.destroy', $taak) }}" onsubmit="return confirm('Taak verwijderen?');" style="display:inline;">
              @csrf @method('DELETE')
              <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">Verwijderen</button>
            </form>
          </td>
        </tr>

        {{-- Bewerkregel, uitklapbaar onder de taak --}}
        <tr class="taak-edit" data-edit="{{ $taak->id }}" hidden>
          <td colspan="8" style="background:var(--priColor102);">
            <form method="POST" action="{{ route('taken.update', $taak) }}" class="sis-form" style="padding:6px 2px;">
              @csrf @method('PUT')
              <div class="sis-fld"><label>Onderwerp <span class="req">*</span></label><input type="text" name="titel" value="{{ $taak->titel }}" maxlength="200" required></div>
              <div class="sis-fld-row sis-fld-row--2">
                <div class="sis-fld"><label>Begindatum</label><input type="date" name="startdatum" value="{{ $taak->startdatum?->toDateString() }}"></div>
                <div class="sis-fld"><label>Moet af op</label><input type="date" name="vervaldatum" value="{{ $taak->vervaldatum?->toDateString() }}"></div>
              </div>
              <div class="sis-fld-row sis-fld-row--2">
                <div class="sis-fld"><label>Status</label>
                  <select name="status">
                    @foreach ($statussen as $waarde => $label)
                      <option value="{{ $waarde }}" @selected($taak->status->value === $waarde)>{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="sis-fld"><label>Prioriteit</label>
                  <select name="prioriteit">
                    @foreach ($prioriteiten as $waarde => $label)
                      <option value="{{ $waarde }}" @selected($taak->prioriteit->value === $waarde)>{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="sis-fld-row sis-fld-row--2">
                <div class="sis-fld"><label>Toegewezen aan</label>
                  <select name="toegewezen_aan_id">
                    <option value="">— niemand —</option>
                    @foreach ($medewerkers as $m)<option value="{{ $m->id }}" @selected($taak->toegewezen_aan_id === $m->id)>{{ $m->naam }}</option>@endforeach
                  </select>
                </div>
                <div class="sis-fld"><label>Student</label>
                  <select name="student_id">
                    <option value="">— geen student —</option>
                    @foreach ($studenten as $s)<option value="{{ $s->id }}" @selected($taak->student_id === $s->id)>{{ $s->studentnummer }} — {{ $naam($s) }}</option>@endforeach
                  </select>
                </div>
              </div>
              <div class="sis-fld"><label>Toelichting</label><textarea name="omschrijving">{{ $taak->omschrijving }}</textarea></div>
              <div class="sis-form__actions">
                <button class="iuasr-dash-btn taak-sluit" type="button" data-taak="{{ $taak->id }}">Sluiten</button>
                <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div>
              </div>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="8"><div class="iuasr-dash-empty" style="border:0;"><h3>Geen taken</h3><p>Er staan geen taken open. Klik op <b>Nieuwe taak</b> om er een toe te voegen.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div style="margin-top:12px;">{{ $taken->links() }}</div>

<p class="sis-tblnote">Deze lijst is <b>gedeeld</b> binnen Studentenzaken: iedereen ziet alle taken en kan een taak die aan niemand is toegewezen oppakken. Een taak wordt <b>te laat</b> zodra de vervaldatum is verstreken en zij niet is afgerond — dat is geen aparte status, maar wordt afgeleid. Taken zonder vervaldatum staan onderaan en tellen niet mee in de signalering.</p>

@push('scripts')
<script>
  (function () {
    var form = document.getElementById('taak-form');
    document.getElementById('taak-nieuw').addEventListener('click', function () {
      form.hidden = false;
      form.querySelector('input[name="titel"]').focus();
    });
    var annuleer = document.getElementById('taak-annuleer');
    if (annuleer) annuleer.addEventListener('click', function () { form.hidden = true; });

    function toon(id, aan) {
      var rij = document.querySelector('.taak-edit[data-edit="' + id + '"]');
      if (rij) rij.hidden = !aan;
    }
    document.querySelectorAll('.taak-bewerk').forEach(function (b) {
      b.addEventListener('click', function () { toon(b.dataset.taak, true); });
    });
    document.querySelectorAll('.taak-sluit').forEach(function (b) {
      b.addEventListener('click', function () { toon(b.dataset.taak, false); });
    });
  })();
</script>
@endpush
@endsection
