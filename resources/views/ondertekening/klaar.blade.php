@extends('layouts.app')

@section('titel', 'Document gewaarmerkt')

@section('inhoud')
<div class="sis-crumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">›</span><a href="{{ route('ondertekening') }}">Ondertekende documenten</a><span class="sep">›</span><b>Gewaarmerkt</b></div>

<div class="sis-card" style="max-width:720px;">
  <div style="display:flex;gap:12px;align-items:flex-start;margin-bottom:6px;">
    <span style="flex:none;width:40px;height:40px;border-radius:50%;background:#e7f2ee;color:#285C4D;display:flex;align-items:center;justify-content:center;">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
    </span>
    <div>
      <h1 style="margin:0 0 4px;font-size:22px;">Document gewaarmerkt</h1>
      <p class="sis-muted" style="margin:0;font-size:13.5px;">Verificatiecode <b style="color:var(--priColor100);">{{ $document->code }}</b> · verstrekt aan {{ $document->ontvanger ?? '—' }}</p>
    </div>
  </div>

  <div class="iuasr-dash-alert iuasr-dash-alert--info" style="margin:14px 0 18px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/></svg>
    <span>Uw <b>originele bestand is ongewijzigd bewaard</b>. U krijgt <b>twee bestanden</b>: uw origineel én een apart digitaal <b>waarmerk-certificaat</b>. Stuur beide samen naar de ontvanger.</span>
  </div>

  <div class="sis-grid-2" style="gap:12px;">
    <div class="sis-card" style="margin:0;background:var(--greyBg,#f7f7fa);">
      <div style="font-size:12px;color:var(--blackAltText);text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px;">Bestand 1 · uw origineel</div>
      <div style="font-weight:600;margin-bottom:10px;word-break:break-all;">{{ $document->bestandsnaam }}</div>
      <a class="iuasr-dash-btn iuasr-dash-btn--sm" href="{{ route('ondertekening.download', $document) }}">Origineel downloaden</a>
    </div>
    <div class="sis-card" style="margin:0;background:var(--greyBg,#f7f7fa);">
      <div style="font-size:12px;color:var(--blackAltText);text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px;">Bestand 2 · digitaal waarmerk</div>
      <div style="font-weight:600;margin-bottom:10px;">Waarmerk-certificaat ({{ $document->code }})</div>
      <a class="iuasr-dash-btn iuasr-dash-btn--sm iuasr-dash-btn--primary" href="{{ route('ondertekening.waarmerk', $document) }}">Waarmerk downloaden</a>
    </div>
  </div>

  <p class="sis-tblnote" style="margin-top:16px;">De ontvanger controleert de echtheid op de <a href="{{ route('verificatie') }}" target="_blank">publieke verificatiepagina</a> met code <b>{{ $document->code }}</b>, en kan het originele bestand daar uploaden om te bevestigen dat het ongewijzigd is.</p>

  <div class="sis-form__actions" style="margin-top:8px;">
    <a class="iuasr-dash-btn" href="{{ route('ondertekening') }}">Naar het archief</a>
    <div class="right"><a class="iuasr-dash-btn iuasr-dash-btn--primary" href="{{ route('ondertekening.uploaden') }}">Nog een document ondertekenen</a></div>
  </div>
</div>
@endsection
