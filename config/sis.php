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
    'versie' => '1.16.0',

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
    | BEVESTIGD (opdrachtgever, 2026-07-15): student 21 dagen, docent 60 dagen.
    |
    | Herinnering: het aantal dagen vóór de vervaldatum waarop de automatische
    | herinnering uitgaat (opdracht: 3). Te late docenten krijgen elke
    | `docent_herinnering_interval` dagen een herhaling (opdracht: 3).
    |
    | BOETE: bevestigd 2026-07-15 op EUR 10,00 per boek. Dit bedrag wordt UITSLUITEND
    | GENOEMD in de te-laat-mail voor studenten (variabele {{Boete}}); het systeem
    | int, boekt of administreert de boete NIET — dat loopt (voorlopig) buiten het
    | systeem om. Docenten krijgen geen boete. `boete_per_boek` is env-overschrijfbaar.
    */
    'bibliotheek' => [
        /*
        | Verrijking van de catalogus met een externe bibliografische bron:
        | ISBN, uitgavejaar en de juiste schrijfwijze van de titel.
        |
        | Uitgaand verkeer gaat UITSLUITEND naar de host hieronder (whitelist,
        | net als bij het onderwijsnieuws); SSL-verificatie blijft altijd aan.
        | Open Library vereist geen sleutel. Google Books geeft zonder API-sleutel
        | meteen HTTP 429 (getest) en is daarom niet de standaard.
        |
        | Alleen voor Nederlandse, Engelse en Turkse titels (keuze opdrachtgever):
        | Arabische titels staan in de bron door elkaar in transliteratie en in
        | Arabisch schrift, en worden door deze bronnen slecht gedekt.
        |
        | ZEKERHEID BOVEN VOLLEDIGHEID: er wordt alleen iets gewijzigd bij een
        | zekere match (titelgelijkenis >= 92% én overeenkomende auteur).
        */
        'verrijking' => [
            'bron' => env('SIS_BIEB_VERRIJKING_BRON', 'openlibrary'),
            'host' => env('SIS_BIEB_VERRIJKING_HOST', 'openlibrary.org'),
            'timeout' => (int) env('SIS_BIEB_VERRIJKING_TIMEOUT', 20),
            // Pauze tussen twee verzoeken (milliseconden) — beleefd tegen de bron.
            'pauze_ms' => (int) env('SIS_BIEB_VERRIJKING_PAUZE_MS', 400),
            // Eigen CA-bundel als de server er geen in php.ini heeft; valt terug op
            // die van het nieuws. SSL-verificatie blijft altijd AAN.
            'cacert' => env('SIS_BIEB_VERRIJKING_CACERT'),
        ],

        'uitleentermijn_student_dagen' => (int) env('SIS_BIEB_TERMIJN_STUDENT', 21),
        'uitleentermijn_docent_dagen' => (int) env('SIS_BIEB_TERMIJN_DOCENT', 60),
        'herinnering_dagen_vooraf' => (int) env('SIS_BIEB_HERINNERING_DAGEN', 3),
        'docent_herinnering_interval_dagen' => (int) env('SIS_BIEB_DOCENT_INTERVAL', 3),
        // Boete per te laat ingeleverd boek (studenten). Alleen GENOEMD in de mail;
        // niet geadministreerd of geïnd door het systeem. BEVESTIGD 2026-07-15: EUR 10.
        'boete_per_boek' => (float) env('SIS_BIEB_BOETE_PER_BOEK', 10),
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
    | Scriptie Coördinatie
    |----------------------------------------------------------------------
    | Toelatingseis EC voor de scriptie (stap 1 van het traject). Instelbaar
    | zoals de overige OER-normen; standaard 180 EC. De toelatingsvakken
    | (Methoden/Methodes en Technieken I en II) en het scriptievak worden per
    | opleiding op VAKCODE herkend — de naam wijkt in de bron af ("Methodes"
    | i.p.v. "Methoden") en dezelfde code kan in meerdere opleidingen bestaan.
    | Alleen de opleidingen met een scriptie staan hier (PABO volgt later).
    | Het leesbare scriptienummer: prefix + 2-cijferige jaarprefix + volgnummer.
    */
    'scriptie' => [
        'toelating_ec' => (float) env('SIS_SCRIPTIE_TOELATING_EC', 180),
        'scriptienummer' => [
            'prefix' => 'S',
            'volgnummer_lengte' => (int) env('SIS_SCRIPTIE_VOLGNUMMER_LENGTE', 4),
        ],
        'toelating_vakken' => [
            'ISLTH' => ['mt1' => 'B-MT04-A', 'mt2' => 'B-MT04-B', 'scriptie' => 'B-BR01'],
            'MGV' => ['mt1' => 'M-GV16a', 'mt2' => 'M-GV16b', 'scriptie' => 'M-GV17'],
        ],
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
    | Quotes in de zijbalk (99 Schone Namen + eigen spreuken)
    |----------------------------------------------------------------------
    | Bovenaan het menu wisselt om de zoveel minuten een Schone Naam van Allah
    | of een eigen spreuk — bedoeld als bemoediging, zonder verdere functie.
    | Welke quote er staat wordt AFGELEID uit de klok (zie Quoteroulatie), dus
    | iedereen ziet in hetzelfde tijdvak dezelfde tekst en de reeks loopt door
    | ongeacht hoe vaak iemand navigeert.
    |
    | De afbeeldingen staan op de private schijf (map `quotes/`) en worden
    | uitgeserveerd via een route, zodat er geen storage:link nodig is en er
    | niets in de webroot belandt.
    */
    'quote' => [
        'interval_minuten' => (int) env('SIS_QUOTE_INTERVAL_MINUTEN', 5),
        // Weergavemaat in de zijbalk: 4 cm ≈ 152 px. Lever de afbeelding op het
        // drievoudige (456 px) zodat hij op een scherpe monitor niet vaag wordt.
        'afbeelding_px' => 152,
        'max_upload_kb' => (int) env('SIS_QUOTE_MAX_UPLOAD_KB', 1024),
    ],

    /*
    |----------------------------------------------------------------------
    | Noodtoegang (break-glass)
    |----------------------------------------------------------------------
    | Offline noodtoegang voor als Entra ID (SSO) onbereikbaar is. Maximaal
    | TWEE accounts met de rol Beheerder mogen met gebruikersnaam+wachtwoord
    | inloggen. Dat maximum wordt op DATABASENIVEAU afgedwongen (unieke
    | `users.noodaccount_slot` met CHECK 1..2) én in de applicatie; deze
    | instelling is de derde controle en mag het er nooit mee oneens zijn.
    | Reguliere accounts hebben en krijgen géén wachtwoord.
    |
    | Elke inlogpoging (geslaagd én mislukt) en elke wachtwoordwijziging komt
    | in het audit-logboek. Het wachtwoord zelf wordt daar NOOIT in vastgelegd.
    |
    | De noodtoegang blijft achter de netwerkbeperking (`toegestane_ips`):
    | een beheerder die van buiten moet werken gaat eerst de VPN op. Er is
    | bewust GEEN uitzondering op de IP-beperking — die zou de enige
    | wachtwoorddeur van het systeem aan het internet blootstellen.
    |
    | BEVESTIGD (opdrachtgever, 2026-07-17): maximaal 2 noodaccounts, geen
    | tweede factor, alleen bereikbaar vanaf het interne netwerk.
    */
    'noodaccount' => [
        'maximum' => 2, // NIET instelbaar via env: de database dwingt hetzelfde af.
        // Lengte boven complexiteit (NCSC): één lange wachtwoordzin.
        'wachtwoord_min_lengte' => (int) env('SIS_NOODACCOUNT_WACHTWOORD_MIN_LENGTE', 16),
        // Verzoeklimiet per gebruikersnaam+IP, en ruimer per IP alleen. Bewust
        // GEEN permanente accountblokkade: dan kan een aanvaller met een handvol
        // foute pogingen de noodtoegang dichtzetten juist wanneer die nodig is.
        'max_pogingen' => (int) env('SIS_NOODACCOUNT_MAX_POGINGEN', 5),
        'max_pogingen_per_ip' => (int) env('SIS_NOODACCOUNT_MAX_POGINGEN_PER_IP', 20),
    ],

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
