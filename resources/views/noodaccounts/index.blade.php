@extends('layouts.app')

@section('titel', 'Noodaccounts')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ route('gebruikers') }}">Gebruikers &amp; rollen</a><span class="sep">›</span><b>Noodaccounts</b></div>

<div class="iuasr-dash-vhead">
  <div>
    <h1>Noodaccounts</h1>
    <div class="summary"><b>{{ $noodaccounts->count() }}</b> van <b>{{ $maximum }}</b> plaatsen in gebruik · toegang met wachtwoord, buiten Entra ID om</div>
  </div>
  {{-- 'noodtoegang' toont geslaagde ÉN mislukte pogingen. Filteren op alleen
       'noodlogin' zou juist de mislukte pogingen verbergen — precies wat u wilt zien. --}}
  <div class="iuasr-dash-vhead__actions"><a class="iuasr-dash-btn" href="{{ route('audit-log', ['actie' => 'noodtoegang']) }}">Inlogpogingen</a></div>
</div>

<div class="iuasr-dash-alert iuasr-dash-alert--warn" style="margin-bottom:18px;">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
  <div>
    <b>Dit is de enige plaats in het systeem waar een wachtwoord toegang geeft.</b>
    Alle andere medewerkers loggen in via Microsoft Entra ID. Een noodaccount is bedoeld voor het geval Entra ID onbereikbaar is
    en geeft volledige beheerrechten — zonder de tweestapsverificatie die Entra biedt. Houd het aantal daarom op ten hoogste {{ $maximum }},
    bewaar het wachtwoord in de kluis of wachtwoordmanager, en geef de twee wachtwoorden aan twee verschillende personen.
    Elke inlogpoging wordt vastgelegd in het audit-logboek, geslaagd én mislukt. De noodtoegang werkt uitsluitend vanaf het interne netwerk;
    van buiten gaat u eerst de VPN op.
  </div>
</div>

<div class="sis-card" style="margin-bottom:18px;">
  <div class="sis-card__hd"><h3>Huidige noodaccounts</h3><span class="hint">Aangewezen beheerders die met een wachtwoord kunnen inloggen</span></div>
  @if ($noodaccounts->isEmpty())
    <p class="sis-muted" style="font-size:13px;margin:0;">
      Er zijn nog geen noodaccounts. Wijs er hieronder een aan, of zet het eerste wachtwoord op de server met
      <code>php artisan sis:noodaccount-instellen &lt;e-mailadres&gt;</code>.
    </p>
  @else
    <div class="iuasr-dash-tbl-card" style="border:0;overflow-x:auto;">
      <table class="iuasr-dash-tbl" style="min-width:640px;">
        <thead><tr><th>Plaats</th><th>Naam</th><th>E-mail</th><th>Wachtwoord gewijzigd</th><th>Laatst ingelogd</th><th class="row-act">Actie</th></tr></thead>
        <tbody>
          @foreach ($noodaccounts as $n)
            <tr>
              <td class="dt">{{ $n->noodaccount_slot }}</td>
              <td class="nm">
                {{ $n->naam }} <span class="sis-rolebadge r-{{ $n->rol->value }}">{{ $n->rol->label() }}</span>
                @unless ($n->magNoodloginGebruiken())
                  {{-- Slot bezet maar niet bruikbaar: de rol is gewijzigd of het account
                       is gedeactiveerd. Zonder deze melding denkt u twee werkende
                       noodaccounts te hebben terwijl er maar één werkt. --}}
                  <span class="sis-pill-soft" style="color:var(--secColor100);border-color:var(--secColor100);">werkt niet — geen actieve Beheerder</span>
                @endunless
              </td>
              <td class="dt">{{ $n->email }}</td>
              <td class="dt">{{ $n->wachtwoord_gewijzigd_op?->format('d-m-Y H:i') ?? '—' }}</td>
              <td class="dt">{{ $n->laatst_ingelogd_op?->format('d-m-Y H:i') ?? 'nooit' }}</td>
              <td class="row-act">
                <form method="POST" action="{{ route('noodaccounts.destroy', $n) }}"
                      onsubmit="return confirm('De noodtoegang van {{ $n->naam }} intrekken? Het wachtwoord wordt gewist; het account zelf blijft bestaan.');">
                  @csrf @method('DELETE')
                  <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--danger iuasr-dash-btn--sm">Intrekken</button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <p class="sis-tblnote">Intrekken wist het wachtwoord en maakt de plaats vrij. Het gebruikersaccount en de rol blijven ongewijzigd.</p>
  @endif
</div>

@foreach ($noodaccounts as $n)
  <div class="sis-card" style="margin-bottom:18px;border-left:3px solid var(--secColor100);">
    <div class="sis-card__hd">
      <h3>Wachtwoord wijzigen — {{ $n->naam }}</h3>
      <span class="hint">Plaats {{ $n->noodaccount_slot }} · typ ter bevestiging het e-mailadres exact over</span>
    </div>
    <form method="POST" action="{{ route('noodaccounts.wachtwoord', $n) }}" class="sis-form">
      @csrf @method('PUT')
      <div class="sis-fld">
        <label for="bevestig_email_{{ $n->id }}">Bevestig het e-mailadres <span class="req">*</span></label>
        <input id="bevestig_email_{{ $n->id }}" name="bevestig_email" type="text" required autocomplete="off"
               placeholder="{{ $n->email }}">
      </div>
      <div class="sis-fld">
        <label for="wachtwoord_{{ $n->id }}">Nieuw wachtwoord <span class="req">*</span></label>
        <input id="wachtwoord_{{ $n->id }}" name="wachtwoord" type="password" required autocomplete="new-password">
      </div>
      <div class="sis-fld">
        <label for="wachtwoord_confirmation_{{ $n->id }}">Herhaal het wachtwoord <span class="req">*</span></label>
        <input id="wachtwoord_confirmation_{{ $n->id }}" name="wachtwoord_confirmation" type="password" required autocomplete="new-password">
      </div>
      <p class="sis-muted" style="font-size:12px;margin:0 0 12px;">
        Minimaal {{ config('sis.noodaccount.wachtwoord_min_lengte') }} tekens. Kies één lange zin die u niet elders gebruikt —
        lengte beschermt hier beter dan leestekens. Het wachtwoord is daarna nergens meer op te vragen.
      </p>
      <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--primary">Wachtwoord wijzigen</button>
    </form>
  </div>
@endforeach

@if ($vrijeSlots > 0)
  <div class="sis-card">
    <div class="sis-card__hd">
      <h3>Noodaccount aanwijzen</h3>
      <span class="hint">{{ $vrijeSlots }} {{ $vrijeSlots === 1 ? 'plaats' : 'plaatsen' }} vrij · alleen actieve beheerders</span>
    </div>
    @if ($kandidaten->isEmpty())
      <p class="sis-muted" style="font-size:13px;margin:0;">
        Er zijn geen actieve accounts met de rol Beheerder die nog geen noodaccount zijn.
        Maak er eerst een aan via <a href="{{ route('gebruikers') }}">Gebruikers &amp; rollen</a>.
      </p>
    @else
      <form method="POST" action="{{ route('noodaccounts.store') }}" class="sis-form">
        @csrf
        <div class="sis-fld">
          <label for="user_id">Beheerder <span class="req">*</span></label>
          <select id="user_id" name="user_id" required>
            <option value="">— kies een account —</option>
            @foreach ($kandidaten as $k)
              <option value="{{ $k->id }}" @selected(old('user_id') == $k->id)>{{ $k->naam }} — {{ $k->email }}</option>
            @endforeach
          </select>
        </div>
        <div class="sis-fld">
          <label for="nieuw_wachtwoord">Wachtwoord <span class="req">*</span></label>
          <input id="nieuw_wachtwoord" name="wachtwoord" type="password" required autocomplete="new-password">
        </div>
        <div class="sis-fld">
          <label for="nieuw_wachtwoord_confirmation">Herhaal het wachtwoord <span class="req">*</span></label>
          <input id="nieuw_wachtwoord_confirmation" name="wachtwoord_confirmation" type="password" required autocomplete="new-password">
        </div>
        <p class="sis-muted" style="font-size:12px;margin:0 0 12px;">
          Minimaal {{ config('sis.noodaccount.wachtwoord_min_lengte') }} tekens.
        </p>
        <button type="submit" class="iuasr-dash-btn iuasr-dash-btn--primary">Aanwijzen en wachtwoord zetten</button>
      </form>
    @endif
  </div>
@endif
@endsection
