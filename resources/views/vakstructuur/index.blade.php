@extends('layouts.app')

@section('titel', 'Vakstructuur')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Vakstructuur</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Vakstructuur</h1>
    <div class="summary">Leg per studiejaar en periode (blok) de vakken vast · basis voor de automatische vaktoewijzing</div>
  </div>
  <div class="iuasr-dash-vhead__actions">
    <form method="GET" action="{{ route('vakstructuur') }}">
      <select name="opleiding" class="iuasr-dash-btn" style="border-color:var(--borderColor);" onchange="this.form.submit()" aria-label="Opleiding">
        @foreach ($opleidingen as $o)
          <option value="{{ $o->id }}" @selected($opleiding && $opleiding->id === $o->id)>{{ $o->naam }}</option>
        @endforeach
      </select>
    </form>
  </div>
</div>

@if ($errors->any())
  <div class="iuasr-dash-alert iuasr-dash-alert--danger" style="margin-bottom:16px;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg><span>Controleer de invoer: {{ $errors->first() }}</span></div>
@endif

@if ($opleiding)
  {{-- Nieuw vak toevoegen --}}
  <form method="POST" action="{{ route('vakstructuur.store') }}" class="sis-card sis-form" style="margin-bottom:16px;">
    @csrf
    <input type="hidden" name="opleiding_id" value="{{ $opleiding->id }}">
    <div class="sis-card__hd"><h3>Vak toevoegen</h3><span class="hint">aan {{ $opleiding->naam }}</span></div>
    <div class="sis-fld-row sis-fld-row--3">
      <div class="sis-fld"><label>Studiejaar</label>
        <select name="leerjaar">@for ($j=1;$j<=$maxLeerjaar;$j++)<option value="{{ $j }}">Jaar {{ $j }}</option>@endfor</select>
      </div>
      <div class="sis-fld"><label>Periode (blok)</label>
        <select name="blok">@for ($b=1;$b<=4;$b++)<option value="{{ $b }}">Blok {{ $b }}</option>@endfor</select>
      </div>
      <div class="sis-fld"><label>EC</label><input type="number" name="ec" min="0" max="60" value="6"></div>
    </div>
    <div class="sis-fld-row sis-fld-row--21">
      <div class="sis-fld"><label>Vaknaam <span class="req">*</span></label><input type="text" name="naam" value="{{ old('naam') }}" required></div>
      <div class="sis-fld"><label>Code <span class="req">*</span></label><input type="text" name="code" value="{{ old('code') }}" required></div>
    </div>
    <div class="sis-fld"><label>Docent</label>
      <select name="docent_id"><option value="">— nog niet toegewezen —</option>
        @foreach ($docenten as $d)<option value="{{ $d->id }}">{{ $d->volledigeNaam() }}</option>@endforeach
      </select>
    </div>
    <div class="sis-form__actions"><span></span><div class="right"><button class="iuasr-dash-btn iuasr-dash-btn--primary" type="submit">Vak toevoegen</button></div></div>
  </form>

  {{-- Studiejaar-tabs --}}
  <div class="sis-subtabs" role="tablist">
    @for ($j=1;$j<=$maxLeerjaar;$j++)
      @php $aantal = ($structuur[$j] ?? collect())->flatten()->count(); @endphp
      <button class="sis-subtab {{ $j===1 ? 'is-active' : '' }}" data-lj="lj{{ $j }}">Jaar {{ $j }}<span class="n">{{ $aantal }}</span></button>
    @endfor
  </div>

  @for ($j=1;$j<=$maxLeerjaar;$j++)
    <div class="vs-panel" id="lj{{ $j }}" data-panel="lj{{ $j }}" @if($j!==1) hidden @endif>
      @for ($b=1;$b<=4;$b++)
        @php $vakken = $structuur[$j][$b] ?? collect(); @endphp
        <div class="sis-card" style="margin-bottom:12px;">
          <div class="sis-card__hd"><h3>Periode · Blok {{ $b }}</h3><span class="hint">{{ $vakken->count() }} vak(ken)</span></div>
          @if ($vakken->isEmpty())
            <p class="sis-muted" style="font-size:13px;margin:0;">Geen vakken in dit blok.</p>
          @else
            <div class="iuasr-dash-tbl-card" style="border:0;">
              <table class="iuasr-dash-tbl">
                <thead><tr><th>Code</th><th>Vak</th><th>EC</th><th>Docent</th><th class="row-act"></th></tr></thead>
                <tbody>
                  @foreach ($vakken as $vak)
                    <tr>
                      <td class="tnum">{{ $vak->code }}</td>
                      <td class="nm">{{ $vak->naam }}</td>
                      <td class="tnum">{{ $vak->ec }}</td>
                      <td>{{ $vak->docent?->achternaam ?? '—' }}</td>
                      <td class="row-act" style="white-space:nowrap;">
                        <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('vakstructuur.edit', $vak) }}">Bewerken</a>
                        <form method="POST" action="{{ route('vakstructuur.destroy', $vak) }}" onsubmit="return confirm('Vak verwijderen?');" style="display:inline;">
                          @csrf @method('DELETE')
                          <button class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger" type="submit">Verwijderen</button>
                        </form>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      @endfor
    </div>
  @endfor

  <p class="sis-tblnote">Deze structuur wordt bewaard en gebruikt om vakken automatisch aan studenten toe te wijzen bij (her)inschrijving. Een vak dat al aan studenten is toegewezen blijft bewaard voor de historie.</p>
@else
  <div class="iuasr-dash-empty"><h3>Geen opleiding</h3><p>Voeg eerst een opleiding toe via Opzoektabellen.</p></div>
@endif

@push('scripts')
<script>
  document.querySelectorAll('.sis-subtab[data-lj]').forEach(function (b) {
    b.addEventListener('click', function () {
      document.querySelectorAll('.sis-subtab[data-lj]').forEach(function (x){ x.classList.remove('is-active'); });
      b.classList.add('is-active');
      var t = b.getAttribute('data-lj');
      document.querySelectorAll('.vs-panel').forEach(function (p){ p.hidden = p.getAttribute('data-panel') !== t; });
    });
  });
</script>
@endpush
@endsection
