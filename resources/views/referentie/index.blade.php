@extends('layouts.app')

@section('titel', 'Opzoektabellen')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><b>Opzoektabellen</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Opzoektabellen</h1>
    <div class="summary">Referentiedata — vroeger losse Access-tabellen, nu centraal en gekoppeld</div>
  </div>
  <div class="iuasr-dash-vhead__actions">
    <a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('opzoektabellen.create', $tabel) }}">{{ $conf['enkel'] }} toevoegen</a>
  </div>
</div>

<div class="sis-subtabs" role="tablist">
  @foreach ($tabbladen as $sleutel => $tab)
    <a class="sis-subtab {{ $sleutel === $tabel ? 'is-active' : '' }}" href="{{ route('opzoektabellen.tabel', $sleutel) }}">{{ $tab['label'] }}<span class="n">{{ $tab['aantal'] }}</span></a>
  @endforeach
</div>

<div class="iuasr-dash-tbl-card">
  <table class="iuasr-dash-tbl">
    <thead>
      <tr>
        @foreach ($conf['kolommen'] as $kop => $fn)<th>{{ $kop }}</th>@endforeach
        <th class="row-act"></th>
      </tr>
    </thead>
    <tbody>
      @forelse ($rijen as $rij)
        <tr>
          @foreach ($conf['kolommen'] as $kop => $fn)
            <td class="{{ $loop->first ? 'nm' : '' }}">{{ $fn($rij) }}</td>
          @endforeach
          <td class="row-act" style="white-space:nowrap;">
            <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('opzoektabellen.edit', [$tabel, $rij->id]) }}">Bewerken</a>
            <form method="POST" action="{{ route('opzoektabellen.destroy', [$tabel, $rij->id]) }}" style="display:inline;" onsubmit="return confirm('Deze rij verwijderen?');">
              @csrf @method('DELETE')
              <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--danger">Verwijderen</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="{{ count($conf['kolommen']) + 1 }}"><div class="iuasr-dash-empty" style="border:0;"><h3>Nog geen rijen</h3><p>Voeg de eerste {{ strtolower($conf['enkel']) }} toe.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<p class="sis-tblnote">Alle koppelingen verlopen via <b>codes en surrogaatsleutels</b>, niet via losse tekst zoals in het oude Access-systeem. Zo blijven studenten, cijfers en rapporten consistent gekoppeld.</p>
@endsection
