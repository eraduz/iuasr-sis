<?php

use App\Enums\Rol;

/*
|--------------------------------------------------------------------------
| IUASR Management Systeem — instellingsspecifieke configuratie
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
    | Versie
    |----------------------------------------------------------------------
    | Softwareversie van het systeem (semantisch: MAJOR.MINOR.PATCH). Wordt
    | onderaan elke pagina getoond zodat testers en beheer weten welke versie
    | draait. Bijwerken bij elke release; houd de wijzigingen bij in CHANGELOG.md.
    */
    'versie' => '1.3.0',

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
    /*
    |--------------------------------------------------------------------------
    | E-mail — afdelings-CC
    |--------------------------------------------------------------------------
    | Elke automatische systeem-e-mail krijgt een CC naar de postbus van de
    | verantwoordelijke afdeling, zodat medewerkers zien welke berichten zijn
    | verstuurd. Per module: HR, Studentenzaken, Examencommissie.
    */
    'mail' => [
        'cc' => [
            'hr' => env('SIS_MAIL_CC_HR', 'personeelszaken@iuasr.nl'),
            'studentenzaken' => env('SIS_MAIL_CC_STUDENTENZAKEN', 'szaken@iuasr.nl'),
            'examencommissie' => env('SIS_MAIL_CC_EXAMENCOMMISSIE', 'examencommissie@iuasr.nl'),
            'bibliotheek' => env('SIS_MAIL_CC_BIBLIOTHEEK', 'bibliotheek@iuasr.nl'),
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Bibliotheek
    |----------------------------------------------------------------------
    | Uitleentermijn per lenerstype, in dagen. De baliemedewerker mag de
    | retourdatum per uitlening aanpassen; dit is alleen de standaardwaarde.
    |
    | TE BEVESTIGEN door de opdrachtgever: de termijnen hieronder zijn een
    | werkbare terugval (student korter dan docent), GEEN vastgestelde norm.
    |
    | Herinnering: het aantal dagen vóór de vervaldatum waarop de automatische
    | herinnering uitgaat (opdracht: 3). Te late docenten krijgen elke
    | `docent_herinnering_interval` dagen een herhaling (opdracht: 3).
    |
    | BOETE: bewust NIET ingebouwd. De boeteregels (bedrag per dag, maximum,
    | wie int) zijn nog niet vastgesteld; er worden geen bedragen verzonnen.
    | Te laat leidt nu tot een waarschuwingsmail en een signaal op het
    | Studentenzaken-dashboard. Zie PROGRESS.md, openstaande parameters.
    */
    'bibliotheek' => [
        'uitleentermijn_student_dagen' => (int) env('SIS_BIEB_TERMIJN_STUDENT', 21),
        'uitleentermijn_docent_dagen' => (int) env('SIS_BIEB_TERMIJN_DOCENT', 60),
        'herinnering_dagen_vooraf' => (int) env('SIS_BIEB_HERINNERING_DAGEN', 3),
        'docent_herinnering_interval_dagen' => (int) env('SIS_BIEB_DOCENT_INTERVAL', 3),
        'boete_ingeschakeld' => (bool) env('SIS_BIEB_BOETE_INGESCHAKELD', false), // TE BEVESTIGEN
    ],

    'hr' => [
        'voltijd_uren' => (float) env('SIS_HR_VOLTIJD_UREN', 40),
        'personeelsnummer' => [
            'prefix' => 'P',
            'volgnummer_lengte' => (int) env('SIS_HR_PERSONEELSNUMMER_VOLGNUMMER_LENGTE', 4),
        ],
        'bsn_ingeschakeld' => (bool) env('SIS_HR_BSN_INGESCHAKELD', false), // standaard UIT

        // Signalering aflopende contracten: einddatum binnen dit aantal dagen.
        'contract_signaal_dagen' => (int) env('SIS_HR_CONTRACT_SIGNAAL_DAGEN', 60),

        // Postbus van Personeelszaken: hierheen gaan automatische meldingen van
        // self-service-acties (verlofaanvragen) en van startend wettelijk verlof.
        'notificatie_email' => env('SIS_HR_NOTIFICATIE_EMAIL', 'personeelszaken@iuasr.nl'),

        // Verjaardagvenster op het HR-dashboard: komende X dagen.
        'verjaardag_venster_dagen' => (int) env('SIS_HR_VERJAARDAG_VENSTER_DAGEN', 30),

        // Verzuimsignalering — Wet Verbetering Poortwachter (Fase G). De wettelijke
        // re-integratiemijlpalen, geteld in weken vanaf de eerste ziektedag; het
        // systeem leidt de mijlpaaldata af (geen aparte tabel). `venster_dagen` =
        // een mijlpaal die binnen dit aantal dagen valt geldt als 'binnenkort'.
        // `frequent` = het frequent-verzuimsignaal (aantal meldingen binnen N mnd).
        'poortwachter' => [
            'venster_dagen' => (int) env('SIS_HR_POORTWACHTER_VENSTER_DAGEN', 14),
            'mijlpalen' => [
                ['sleutel' => 'probleemanalyse', 'label' => 'Probleemanalyse bedrijfsarts', 'week' => 6],
                ['sleutel' => 'plan_van_aanpak', 'label' => 'Plan van aanpak', 'week' => 8],
                ['sleutel' => 'uwv_ziekmelding', 'label' => 'Ziekmelding UWV (42e week)', 'week' => 42],
                ['sleutel' => 'eerstejaarsevaluatie', 'label' => 'Eerstejaarsevaluatie', 'week' => 52],
                ['sleutel' => 'wia_aanvraag', 'label' => 'WIA-aanvraag', 'week' => 93],
            ],
            'frequent' => [
                'maanden' => (int) env('SIS_HR_FREQUENT_MAANDEN', 12),
                'drempel' => (int) env('SIS_HR_FREQUENT_DREMPEL', 3),
            ],
        ],
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
        // Cijferschaal. LET OP (studiegids BA ISLTH 2025-2026): sommige modules
        // (o.a. Standaard Arabisch V/VI) hanteren een 0–100-schaal met bonuspunten;
        // het interne systeem werkt op 1–10. Zolang de OER geen afwijkende schaal
        // per opleiding voorschrijft, worden cijfers op 1–10 vastgelegd. De invoer-
        // en veldvalidatie leest deze grenzen (env-overschrijfbaar).
        'schaal_min' => (float) env('SIS_CIJFER_MIN', 1.0),
        'schaal_max' => (float) env('SIS_CIJFER_MAX', 10.0),
        // BEVESTIGD (opdrachtgever, 2026-07-07): cesuur 5,5 voor alle opleidingen.
        // Per opleiding overschrijfbaar via opleidingen.voldoende_grens.
        'voldoende_grens_terugval' => 5.5,
        'decimalen' => 1,

        // EC-model: hoe bepaalt een voldoende dat de vak-EC worden toegekend?
        //  - 'knockout'        : ELK meetellend toetsonderdeel moet ≥ cesuur zijn.
        //  - 'compensatorisch' : het GEWOGEN eindcijfer moet ≥ cesuur zijn (een
        //                        onvoldoende onderdeel kan gecompenseerd worden).
        // De studiegids beschrijft per module een gewogen toetsformule (compensatie),
        // maar de bindende regel staat in het OER. Daarom instelbaar: terugval hier,
        // per opleiding overschrijfbaar via opleidingen.ec_model. TE BEVESTIGEN.
        'ec_model' => env('SIS_EC_MODEL', 'knockout'),
    ],

    'ec' => [
        // Drempel voor overgang naar volgend leerjaar. PvA noemt 40 EC als
        // richtgetal; per opleiding vast te leggen. TE BEVESTIGEN.
        'overgang_drempel_terugval' => null,
        // Herbeoordelingsmomenten leerjaar (semesterstart): eind juli / midden januari.
        'herbeoordeling' => ['juli', 'januari'],
    ],

    /*
    |----------------------------------------------------------------------
    | Collegegeld — facturering & vervaldatum
    |----------------------------------------------------------------------
    | Het collegegeld wordt in termijnen gefactureerd (sep/nov/jan/mrt/mei).
    | IUASR verstuurt de factuur op de FACTUURDAG van de vervalmaand en geeft de
    | student daarna BETAALTERMIJN_DAGEN de tijd om te betalen. De vervaldatum van
    | een termijn is dus factuurdag + betaaltermijn (BEVESTIGD 2026-07-12: 14 + 10
    | = de 24e van de maand). Bepaalt wanneer een onbetaalde termijn 'achterstallig'
    | wordt en dus de blokkades op herinschrijven/verklaringen aanstuurt.
    */
    'collegegeld' => [
        'factuurdag' => (int) env('SIS_COLLEGEGELD_FACTUURDAG', 14),
        'betaaltermijn_dagen' => (int) env('SIS_COLLEGEGELD_BETAALTERMIJN_DAGEN', 10),
    ],

    /*
    |----------------------------------------------------------------------
    | Onderwijsnieuws (bestuursdashboard)
    |----------------------------------------------------------------------
    | Nieuws wordt op een schema (dagelijks 23:00) op de achtergrond opgehaald
    | en lokaal opgeslagen; het dashboard leest alleen uit de lokale tabel.
    | Uitgaand verkeer is beperkt tot de WHITELIST hieronder (alleen deze hosts
    | mogen worden benaderd — voorkomt misbruik/SSRF en houdt het intern-beheersbaar).
    */
    'nieuws' => [
        'toegestane_hosts' => array_filter(array_map('trim', explode(',', (string) env(
            'SIS_NIEUWS_HOSTS',
            'www.vereniginghogescholen.nl,www.onderwijsinspectie.nl'
        )))),
        'timeout' => (int) env('SIS_NIEUWS_TIMEOUT', 15),
        'max_per_bron' => (int) env('SIS_NIEUWS_MAX_PER_BRON', 15),
        'toon_aantal' => (int) env('SIS_NIEUWS_TOON_AANTAL', 6),
        // Pad naar een CA-bundel (cacert.pem) voor SSL-verificatie, als de server
        // die niet globaal in php.ini heeft. SSL-verificatie blijft altijd AAN.
        'cacert' => env('SIS_NIEUWS_CACERT'),
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
    | 8 weken per blok, één college per week (BEVESTIGD 2026-07-09).
    |
    | Norm 75% (studiegids BA ISLTH 2025-2026, §2.3.3: "Aanwezigheid is voor
    | minimaal 75% verplicht. Wie dit percentage niet heeft behaald mag geen toets
    | afleggen."). Eerder stond hier 80%; teruggebracht naar de studiegids-norm en
    | env-overschrijfbaar zodat het per de OER/opdrachtgever kan worden bijgesteld.
    | 50% geldt voor studenten aan wie de 50%-aanwezigheidsregeling is toegekend.
    | LET OP: sommige modules eisen "100% aanwezigheid, max 25% afwezig" (= ook 75%);
    | een per-vak-norm is nog niet gemodelleerd (zie PROGRESS, studiegids-analyse).
    */
    'presentie' => [
        'weken_per_blok' => (int) env('SIS_PRESENTIE_WEKEN_PER_BLOK', 8),
        'norm' => (float) env('SIS_PRESENTIE_NORM', 0.75),
        'norm_regeling' => (float) env('SIS_PRESENTIE_NORM_REGELING', 0.50),
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
