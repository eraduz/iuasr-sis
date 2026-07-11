<?php

use App\Enums\Rol;

/*
|--------------------------------------------------------------------------
| IUASR SIS — instellingsspecifieke configuratie
|--------------------------------------------------------------------------
|
| Dit bestand bundelt de niet-onderhandelbare principes en de nog vast te
| leggen parameters uit het Plan van Aanpak op één plek. Waarden die nog
| door de opdrachtgever bevestigd moeten worden staan bewust op null en
| worden NIET met een aanname ingevuld (zie PROGRESS.md, hoofdstuk 13 PvA).
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | Studentnummer
    |----------------------------------------------------------------------
    | Formaat: jaarprefix (2 cijfers, bv. "26") + een vast aantal cijfers.
    | De bron noemt zowel 5 als 6 cijfers ná de prefix — dit is een
    | OPENSTAANDE PARAMETER en moet eenduidig worden vastgesteld voordat
    | er nummers worden uitgegeven. Blijft null tot bevestigd.
    |
    | Let op: het studentnummer is een uniek, leesbaar VELD — nooit een
    | koppelsleutel. Interne relaties lopen via de surrogaatsleutel (id).
    */
    'studentnummer' => [
        // BEVESTIGD (opdrachtgever, 2026-07-06): jaarprefix van 2 cijfers + een
        // volgnummer, totaal 6 tekens. Voorbeeld: 261234 (26 = 2026, 1234 = volgnr).
        'jaarprefix_lengte' => 2,
        'volgnummer_lengte' => (int) env('SIS_STUDENTNUMMER_VOLGNUMMER_LENGTE', 4),
        // Nummerbeleid bij heringstroom: behoudt of nieuw nummer? TE BEVESTIGEN.
        'behoud_bij_heringstroom' => null,
    ],

    /*
    |----------------------------------------------------------------------
    | Relatienummer (module Relatiebeheer & Stagebeheer)
    |----------------------------------------------------------------------
    | Leesbaar nummer voor een externe organisatie/relatie: prefix 'R' +
    | 2-cijferige jaarprefix + volgnummer (bijv. R260001). Net als het
    | studentnummer een uniek, leesbaar VELD — nooit een koppelsleutel.
    */
    'relatienummer' => [
        'prefix' => 'R',
        'volgnummer_lengte' => (int) env('SIS_RELATIENUMMER_VOLGNUMMER_LENGTE', 4),
    ],

    /*
    |----------------------------------------------------------------------
    | HR / Personeelszaken
    |----------------------------------------------------------------------
    | Voltijdsnorm voor de FTE-berekening (BEVESTIGD 2026-07-11: 40 uur/week);
    | FTE = uren_per_week ÷ voltijd_uren. Personeelsnummer = prefix + jaar(2) +
    | volgnummer. BSN van medewerkers: veld klaar maar standaard UIT tot akkoord
    | van de Functionaris Gegevensbescherming (zoals bij studenten).
    */
    'hr' => [
        'voltijd_uren' => (float) env('SIS_HR_VOLTIJD_UREN', 40),
        'personeelsnummer' => [
            'prefix' => 'P',
            'volgnummer_lengte' => (int) env('SIS_HR_PERSONEELSNUMMER_VOLGNUMMER_LENGTE', 4),
        ],
        'bsn_ingeschakeld' => (bool) env('SIS_HR_BSN_INGESCHAKELD', false), // standaard UIT
    ],

    /*
    |----------------------------------------------------------------------
    | Cijfer- en EC-normen
    |----------------------------------------------------------------------
    | Voldoende-grens en EC-drempels verschillen mogelijk per opleiding en
    | worden daarom als kolom op de opleiding vastgelegd (data-gedreven).
    | De onderstaande waarden zijn uitsluitend een NEUTRALE terugval en géén
    | vastgestelde norm — per opleiding te bevestigen met de opdrachtgever.
    */
    'cijfers' => [
        'schaal_min' => 1.0,
        'schaal_max' => 10.0,
        // BEVESTIGD (opdrachtgever, 2026-07-07): cesuur 5,5 voor alle opleidingen.
        // Per opleiding overschrijfbaar via opleidingen.voldoende_grens.
        'voldoende_grens_terugval' => 5.5,
        'decimalen' => 1,
    ],

    'ec' => [
        // Drempel voor overgang naar volgend leerjaar. PvA noemt 40 EC als
        // richtgetal; per opleiding vast te leggen. TE BEVESTIGEN.
        'overgang_drempel_terugval' => null,
        // Herbeoordelingsmomenten leerjaar (semesterstart): eind juli / midden januari.
        'herbeoordeling' => ['juli', 'januari'],
    ],

    'kennistoetsen' => [
        // Landelijke kennistoetsen (bv. PABO: RWT reken-/wiskundetoets + LKT
        // taal en rekenen). Termijn waarbinnen de student ze moet halen,
        // gerekend vanaf de (eerste) inschrijfdatum.
        'termijn_jaren' => 2,
    ],

    /*
    |----------------------------------------------------------------------
    | Presentie (aanwezigheidsregistratie)
    |----------------------------------------------------------------------
    | De docent registreert per college de aanwezigheid: 1 = aanwezig,
    | 0 = afwezig, leeg = nog niet geregistreerd. Een blok telt een vast
    | aantal onderwijsweken; per week legt de docent één registratie vast.
    |
    | BEVESTIGD (opdrachtgever, 2026-07-09): 8 weken per blok, één college
    | per week; norm 80% aanwezigheid, of 50% voor studenten aan wie de
    | 50%-aanwezigheidsregeling is toegekend.
    */
    'presentie' => [
        'weken_per_blok' => 8,
        'norm' => 0.80,
        'norm_regeling' => 0.50,
    ],

    /*
    |----------------------------------------------------------------------
    | Rollen (rolscheiding vanaf de eerste regel code)
    |----------------------------------------------------------------------
    | Bij voorkeur ontleend aan Entra-groepen. Autorisatie wordt ALTIJD
    | server-side afgedwongen (zie AutorisatieServiceProvider en policies).
    */
    'rollen' => Rol::waarden(),

    /*
    |----------------------------------------------------------------------
    | Gevoelige velden (AVG)
    |----------------------------------------------------------------------
    | BSN en rekeningnummer worden versleuteld opgeslagen; inzage/mutatie
    | wordt gelogd. BSN wordt pas toegevoegd na expliciet akkoord van de
    | Functionaris Gegevensbescherming (mogelijk pas bij DUO-processen).
    */
    'gevoelige_velden' => [
        'bsn_ingeschakeld' => (bool) env('SIS_BSN_INGESCHAKELD', false), // standaard UIT
        'rekeningnummer_ingeschakeld' => (bool) env('SIS_REKENINGNUMMER_INGESCHAKELD', false),
    ],

    /*
    |----------------------------------------------------------------------
    | Netwerkbeperking (intranet)
    |----------------------------------------------------------------------
    | Het systeem draait intern en is IP-beperkt. Leeg = geen filter
    | (alleen toegestaan in lokale ontwikkeling).
    */
    'toegestane_ips' => array_filter(array_map(
        'trim',
        explode(',', (string) env('SIS_TOEGESTANE_IPS', ''))
    )),

];
