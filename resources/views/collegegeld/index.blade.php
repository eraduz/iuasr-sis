@extends('layouts.app')

@section('titel', 'Collegegeld')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Collegegeld</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Collegegeld</h1>
    <div class="summary">Stel het jaarlijkse collegegeld per studiejaar in · door de Studentenadministratie bij te werken</div>
  </div>
</div>

@if ($errors->any())
  <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:16px;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>Controleer de invoer: {{ $errors->first() }}</span></div>
@endif

<div class="sis-grid-2">
  <div>
    <form method="POST" action="{{ route('collegegeld.store') }}" class="sis-card sis-form">
      @csrf
      <div class="sis-card__hd"><h3>Tarief instellen / bijwerken</h3></div>
      <div class="sis-fld">
        <label>Studiejaar <span class="req">*</span></label>
        <select name="periode_id" required>
          @foreach ($perioden as $p)
            <option value="{{ $p->id }}" @selected(old('periode_id', $actievePeriode?->id) == $p->id)>{{ $p->naam }}</option>
          @endforeach
        </select>
      </div>
      <div class="sis-fld">
        <label>Opleiding</label>
        <select name="opleiding_id">
          <option value="">Alle opleidingen (standaardtarief)</option>
          @foreach ($opleidingen as $o)
            <option value="{{ $o->id }}" @selected(old('opleiding_id') == $o->id)>{{ $o->naam }}</option>
          @endforeach
        </select>
        <div class="help">Laat op “Alle opleidingen” staan voor één standaardtarief; kies een opleiding voor een afwijkend tarief.</div>
      </div>
      <div class="sis-fld-row sis-fld-row--2">
        <div class="sis-fld">
          <label>Collegegeld (€) <span class="req">*</span></label>
          <div class="sis-inputwrap"><span class="prefix">€</span><input type="number" step="0.01" min="0" name="bedrag" value="{{ old('bedrag') }}" required style="padding-left:26px;"></div>
        </div>
        <div class="sis-fld"><label>Aantal termijnen</label><input type="number" min="1" max="12" name="aantal_termijnen" value="{{ old('aantal_termijnen', 5) }}"></div>
      </div>
      <div class="sis-form__actions">
        <span></span>
        <div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Opslaan</button></div>
      </div>
      <p class="sis-tblnote" style="margin-top:8px;">Een bestaand tarief voor hetzelfde studiejaar en dezelfde opleiding wordt bijgewerkt.</p>
    </form>
  </div>

  <div>
    <div class="sis-card">
      <div class="sis-card__hd"><h3>Ingestelde tarieven</h3></div>
      <div class="iuasr-dash-tbl-card" style="border:0;">
        <table class="iuasr-dash-tbl">
          <thead><tr><th>Studiejaar</th><th>Opleiding</th><th>Bedrag</th><th>Termijnen</th><th class="row-act"></th></tr></thead>
          <tbody>
            @forelse ($tarieven as $t)
              <tr>
                <td class="nm">{{ $t->periode?->naam }}</td>
                <td>{{ $t->opleiding?->naam ?? 'Alle opleidingen' }}</td>
                <td class="tnum">€ {{ number_format($t->bedrag, 2, ',', '.') }}</td>
                <td class="tnum">{{ $t->aantal_termijnen }}</td>
                <td class="row-act">
                  <form method="POST" action="{{ route('collegegeld.destroy', $t) }}" onsubmit="return confirm('Tarief verwijderen?');" style="display:inline;">
                    @csrf @method('DELETE')
                    <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">Verwijderen</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr><td colspan="5"><div class="iuasr-dash-empty" style="border:0;"><h3>Nog geen tarieven</h3><p>Stel links het collegegeld voor het studiejaar in.</p></div></td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
