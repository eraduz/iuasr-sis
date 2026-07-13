@php
    use App\Enums\Rol;

    $rol = auth()->user()->rol->value;

    // Iconen uit het design system (sis-shell.js), server-side gerenderd.
    $icon = function (string $k): string {
        return match ($k) {
            'dash' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>',
            'students' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            'plus' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>',
            'refresh' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>',
            'userx' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="17" y1="8" x2="22" y2="13"/><line x1="22" y1="8" x2="17" y2="13"/></svg>',
            'report' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg>',
            'cert' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M8.21 13.89 7 22l5-3 5 3-1.21-8.11"/></svg>',
            'book' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
            'grade' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
            'eye' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>',
            'users' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            'db' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>',
            'log' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
            'money' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><circle cx="12" cy="14.5" r="1.6"/></svg>',
            'euro' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M18 7a7 7 0 1 0 0 10"/><line x1="3" y1="10" x2="13" y2="10"/><line x1="3" y1="14" x2="13" y2="14"/></svg>',
            'check' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
            'taak' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l2 2 4-4"/><rect x="3" y="4" width="18" height="17" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>',
            'search' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
            'alert' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            default => '',
        };
    };

    // Menu per rol: [groepstitel => [ [label, routenaam, icoon, actief-patroon], ... ] ]
    $menus = [
        Rol::Studentenzaken->value => [
            'Overzicht' => [
                ['Dashboard', 'dashboard', 'dash', 'dashboard'],
                ['Taken', 'taken', 'taak', 'taken'],
            ],
            'Studenten' => [
                ['Alle studenten', 'studenten.index', 'students', 'studenten.*'],
                ['Student inschrijven', 'inschrijven', 'plus', 'inschrijven'],
                ['Bulk inschrijven', 'bulk-inschrijven', 'plus', 'bulk-inschrijven*'],
                ['Herinschrijven', 'herinschrijven', 'refresh', 'herinschrijven'],
                ['Uitschrijven', 'uitschrijven', 'userx', 'uitschrijven'],
                ['Afstuderen', 'afstuderen.kandidaten', 'cert', 'afstuderen.kandidaten'],
                ['Migratie (import)', 'migratie', 'db', 'migratie'],
            ],
            'Onderwijs' => [
                ['Vakstructuur', 'vakstructuur', 'book', 'vakstructuur*'],
            ],
            'Documenten' => [
                ['Rapporten', 'rapporten', 'report', 'rapporten'],
                ['Verklaringen', 'verklaringen', 'cert', 'verklaringen'],
                ['Ondertekende documenten', 'ondertekening', 'cert', 'ondertekening*'],
            ],
            'Financieel' => [
                ['Collegegeld', 'collegegeld', 'euro', 'collegegeld'],
            ],
        ],
        Rol::Financien->value => [
            'Overzicht' => [['Dashboard', 'dashboard', 'dash', 'dashboard']],
            'Financiën' => [
                ['Betalingen & achterstand', 'financien', 'money', 'financien*'],
            ],
        ],
        Rol::Docent->value => [
            'Overzicht' => [['Dashboard', 'dashboard', 'dash', 'dashboard']],
            'Onderwijs' => [
                ['Mijn vakken', 'mijn-vakken', 'book', 'mijn-vakken'],
                ['Aanwezigheid', 'presentieoverzicht', 'check', 'presentieoverzicht'],
            ],
        ],
        Rol::Examencommissie->value => [
            'Overzicht' => [['Dashboard', 'dashboard', 'dash', 'dashboard']],
            'Studenten' => [
                ['Alle studenten', 'studenten.index', 'students', 'studenten.*'],
                // Snelkoppeling: direct naar de studentenlijst om een student te
                // kiezen en meteen bij het vrijstellingsformulier op het dossier
                // uit te komen.
                ['Vrijstelling', 'studenten.index', 'cert', 'studenten.*', ['doel' => 'vrijstelling']],
            ],
            'Afstuderen' => [
                ['Kandidaten', 'afstuderen.kandidaten', 'cert', 'afstuderen.kandidaten'],
            ],
            'Cijfers' => [
                ['Cijferoverzicht', 'cijferoverzicht', 'grade', 'cijferoverzicht'],
                ['Cijferlijst', 'cijferlijst', 'report', 'cijferlijst'],
                ['Leerjaar-herbeoordeling', 'overgang', 'grade', 'overgang'],
                ['EC-rapport', 'ec-rapport', 'report', 'ec-rapport'],
                ['Historisch dossier', 'historisch.index', 'db', 'historisch.*'],
            ],
            'Onderwijs' => [['Aanwezigheid', 'presentieoverzicht', 'check', 'presentieoverzicht']],
            'Rapporten' => [
                ['Rapporten', 'rapporten.inzage', 'report', 'rapporten.inzage'],
                ['Alumni', 'rapporten.alumni', 'cert', 'rapporten.alumni'],
            ],
        ],
        Rol::Directie->value => [
            'Overzicht' => [['Dashboard', 'dashboard', 'dash', 'dashboard']],
            'Studenten' => [['Studenten (beperkt)', 'studenten.index', 'students', 'studenten.*']],
            'Cijfers' => [
                ['Cijferoverzicht', 'cijferoverzicht', 'eye', 'cijferoverzicht'],
                ['Cijferlijst', 'cijferlijst', 'report', 'cijferlijst'],
                ['Leerjaar-herbeoordeling', 'overgang', 'grade', 'overgang'],
                ['EC-rapport', 'ec-rapport', 'report', 'ec-rapport'],
                ['Historisch dossier', 'historisch.index', 'db', 'historisch.*'],
            ],
            'Onderwijs' => [['Aanwezigheid', 'presentieoverzicht', 'check', 'presentieoverzicht']],
            'Rapporten' => [['Rapporten', 'rapporten.inzage', 'report', 'rapporten.inzage']],
            'Documenten' => [['Ondertekende documenten', 'ondertekening', 'cert', 'ondertekening*']],
        ],
        // Het Schoolbestuur werkt uitsluitend vanuit deze ene pagina. Alle
        // (alleen-lezen) rapportages van de andere modules staan hier als directe
        // links, zodat het Bestuur nooit onbedoeld in een andere module belandt en
        // geen module-menu met beheerlinks te zien krijgt die 403 geven.
        Rol::Bestuur->value => [
            'Overzicht' => [
                ['Bestuursoverzicht', 'bestuur', 'dash', 'bestuur'],
                ['Alle studenten', 'studenten.index', 'students', 'studenten.*'],
            ],
            'Rapportages' => [
                ['Alumni', 'rapporten.alumni', 'cert', 'rapporten.alumni'],
                ['Aanwezigheid', 'presentieoverzicht', 'check', 'presentieoverzicht'],
                ['Cursusrapportage', 'cursussen.rapport', 'report', 'cursussen.rapport'],
                ['Relatiebeheer', 'relatiebeheer.dashboard', 'db', 'relatiebeheer.dashboard,relatiebeheer.rapport'],
                ['HR-rapportage', 'hr.rapport', 'report', 'hr.rapport'],
                ['HR verzuim & verlof', 'hr.verzuimverlof', 'cert', 'hr.verzuimverlof'],
            ],
            'Documenten' => [['Ondertekende documenten', 'ondertekening', 'cert', 'ondertekening*']],
            'Handleidingen' => [
                ['Medewerkershandleiding', 'handleiding.medewerkers', 'report', 'handleiding.medewerkers'],
                ['Technische handleiding', 'handleiding.technisch', 'report', 'handleiding.technisch'],
            ],
        ],
        Rol::Beheerder->value => [
            'Overzicht' => [
                ['Dashboard', 'dashboard', 'dash', 'dashboard'],
                ['Bestuursoverzicht', 'bestuur', 'dash', 'bestuur'],
            ],
            'Studenten' => [
                ['Alle studenten', 'studenten.index', 'students', 'studenten.*'],
                ['Historisch dossier', 'historisch.index', 'db', 'historisch.*'],
            ],
            'Beheer' => [
                ['Gebruikers & rollen', 'gebruikers', 'users', 'gebruikers'],
                ['Opzoektabellen', 'opzoektabellen', 'db', 'opzoektabellen'],
                ['Nieuwsbronnen', 'nieuws', 'report', 'nieuws'],
                ['Audit-log', 'audit-log', 'log', 'audit-log'],
                ['Back-up & herstel', 'backup', 'db', 'backup'],
                ['Technische handleiding', 'handleiding.technisch', 'report', 'handleiding.technisch'],
            ],
            'Documenten' => [
                ['Ondertekende documenten', 'ondertekening', 'cert', 'ondertekening*'],
            ],
        ],
    ];

    // Binnen de module Cursussen Administratie geldt een eigen menu. Het is
    // rolbewust: de cursusadministratie beheert cursussen/cursisten, de
    // Financiële Administratie (boekhouding) doet de cursusgelden, en Beheer
    // ziet alles. Het actief-patroon per item mag een wildcard zijn zodat
    // sub-schermen de juiste regel oplichten.
    $gebruiker = auth()->user();
    $cursusMenu = [
        'Cursussen' => [
            ['Overzicht', 'cursussen.dashboard', 'dash', 'cursussen.dashboard'],
            ['Rapportage', 'cursussen.rapport', 'report', 'cursussen.rapport'],
        ],
    ];

    if ($gebruiker->magCursusBeheer()) {
        $cursusMenu['Cursussen'][] = ['Cursusbeheer', 'cursussen.beheer', 'book', 'cursussen.beheer,cursussen.create,cursussen.edit'];
        $cursusMenu['Cursisten'] = [
            ['Alle cursisten', 'cursisten', 'students', 'cursisten,cursisten.show,cursisten.edit'],
            ['Cursist toevoegen', 'cursisten.create', 'plus', 'cursisten.create'],
        ];
    } elseif ($gebruiker->magCursusInzien()) {
        // Schoolbestuur: alleen-lezen cursistinzage.
        $cursusMenu['Cursisten'] = [
            ['Alle cursisten', 'cursisten', 'students', 'cursisten,cursisten.show'],
        ];
    }

    if ($gebruiker->magCursusFinancien()) {
        $cursusMenu['Boekhouding'] = [
            ['Cursusgelden', 'cursussen.betalingen', 'euro', 'cursussen.betalingen'],
        ];
    }

    // Binnen de module Relatiebeheer & Stage geldt eveneens een eigen, rolbewust
    // menu. De relatiebeheerder/stagecoördinator beheert organisaties; Directie
    // en Bestuur kijken mee (alleen-lezen).
    $relatieMenu = [
        'Relatiebeheer' => [
            ['Overzicht', 'relatiebeheer.dashboard', 'dash', 'relatiebeheer.dashboard'],
            ['Organisaties', 'relaties', 'students', 'relaties,relaties.show,relaties.edit'],
            ['Stages', 'stages', 'cert', 'stages,stages.edit,stages.create,stageplaatsen.create,stageplaatsen.edit'],
            ['Agenda & taken', 'agenda', 'taak', 'agenda,afspraken.create,afspraken.edit,relatietaken.edit'],
            ['Zoeken', 'relatiebeheer.zoeken', 'eye', 'relatiebeheer.zoeken'],
            ['Rapportage', 'relatiebeheer.rapport', 'report', 'relatiebeheer.rapport'],
        ],
    ];
    if ($gebruiker->magRelatiebeheer()) {
        $relatieMenu['Relatiebeheer'][] = ['Organisatie toevoegen', 'relaties.create', 'plus', 'relaties.create'];
    }

    // Module HR / Personeelszaken — rolbewust menu. HR/Beheer beheren; Bestuur
    // kijkt mee. De Zelfservice-groep geldt voor elke gekoppelde medewerker.
    $hrMenu = [
        'HR / Personeelszaken' => [
            ['Overzicht', 'hr.dashboard', 'dash', 'hr.dashboard'],
            ['Zoeken', 'hr.zoeken', 'search', 'hr.zoeken'],
            ['Medewerkers', 'medewerkers', 'students', 'medewerkers,medewerkers.show,medewerkers.edit,dienstverbanden.edit,dienstverbanden.create'],
            ['Verlof', 'verlof', 'cert', 'verlof'],
            ['Verzuim', 'verzuim', 'check', 'verzuim'],
            ['Signaleringen', 'hr.signaleringen', 'alert', 'hr.signaleringen'],
            ['Gesprekken', 'gesprekken', 'report', 'gesprekken,gesprekken.show,gesprekken.create'],
            ['Organisatie', 'hr.organisatie', 'db', 'hr.organisatie'],
            ['Rapportage', 'hr.rapport', 'report', 'hr.rapport'],
            ['Verzuim & verlof', 'hr.verzuimverlof', 'cert', 'hr.verzuimverlof'],
        ],
        'Zelfservice' => [
            ['Mijn HR', 'hr.mijn', 'eye', 'hr.mijn'],
            ['Mijn verlof', 'verlof.mijn', 'taak', 'verlof.mijn,verlof.create'],
        ],
    ];
    if ($gebruiker->magHrBeheer()) {
        $hrMenu['HR / Personeelszaken'][] = ['Medewerker toevoegen', 'medewerkers.create', 'plus', 'medewerkers.create'];
    }

    // Module Balie/Receptie — één logboek voor telefoon, bezoek en post. De Balie
    // registreert; Directie en Bestuur kijken mee (alleen-lezen), en zien daarom
    // de aanmaakknop niet.
    $balieMenu = [
        'Balie / Receptie' => [
            ['Overzicht', 'balie.dashboard', 'dash', 'balie.dashboard'],
            ['Logboek', 'balie', 'report', 'balie,balie.edit'],
        ],
    ];
    if ($gebruiker->magBalieBeheren()) {
        $balieMenu['Balie / Receptie'][] = ['Nieuwe registratie', 'balie.create', 'plus', 'balie.create'];
    }

    // Module Bibliotheek — catalogus, tijdschriftartikelen, uitlenen en innemen.
    // De bibliotheekmedewerker beheert; het Schoolbestuur kijkt mee (alleen-lezen)
    // en ziet daarom de aanmaak- en uitleenknoppen niet.
    $biebMenu = [
        'Bibliotheek' => [
            ['Overzicht', 'bibliotheek.dashboard', 'dash', 'bibliotheek.dashboard'],
            ['Catalogus', 'bibliotheek.publicaties', 'book', 'bibliotheek.publicaties,bibliotheek.publicaties.show,bibliotheek.publicaties.edit'],
            ['Boekreeksen', 'bibliotheek.reeksen', 'db', 'bibliotheek.reeksen,bibliotheek.reeksen.show,bibliotheek.reeksen.create'],
            ['Artikelen zoeken', 'bibliotheek.artikelen', 'search', 'bibliotheek.artikelen,bibliotheek.uitgaven.show'],
            ['Uitleningen', 'bibliotheek.uitleningen', 'cert', 'bibliotheek.uitleningen,bibliotheek.innemen,bibliotheek.lener'],
            ['Rapportage', 'bibliotheek.rapport', 'report', 'bibliotheek.rapport'],
        ],
    ];
    if ($gebruiker->magBibliotheekBeheren()) {
        $biebMenu['Bibliotheek'][] = ['Publicatie toevoegen', 'bibliotheek.publicaties.create', 'plus', 'bibliotheek.publicaties.create'];
        $biebMenu['Bibliotheek'][] = ['Uitlenen', 'bibliotheek.uitlenen', 'taak', 'bibliotheek.uitlenen'];
        $biebMenu['Bibliotheek'][] = ['Importeren', 'bibliotheek.import', 'db', 'bibliotheek.import'];
        $biebMenu['Bibliotheek'][] = ['Verrijking (ISBN)', 'bibliotheek.verrijking', 'search', 'bibliotheek.verrijking'];
    }
    if ($gebruiker->magBibliotheekSjablonenBeheren()) {
        $biebMenu['Bibliotheek'][] = ['E-mailsjablonen', 'bibliotheek.sjablonen', 'log', 'bibliotheek.sjablonen'];
    }

    // Standaardmenu buiten een module. Bij multi-rol worden de menu's van álle
    // rollen samengevoegd, zodat de gebruiker elk scherm bereikt waar hij recht op
    // heeft. Groepen worden op titel gecombineerd; dubbele items (zelfde route +
    // label + doel) verschijnen één keer. De relatiebeheerder/stagecoördinator en
    // de HR-medewerker hebben geen eigen rol-menu in $menus; hun thuisbasis is hun
    // module. Het Schoolbestuur houdt bewust zijn eigen, afgeschermde menu (zie
    // onder) en wordt daarom nooit in het gemengde menu opgenomen.
    $mergeMenu = function (array $doel, array $bron): array {
        foreach ($bron as $groep => $items) {
            $bestaand = $doel[$groep] ?? [];
            $sleutels = array_map(fn ($i) => $i[1].'|'.$i[0].'|'.($i[4]['doel'] ?? ''), $bestaand);
            foreach ($items as $item) {
                $sleutel = $item[1].'|'.$item[0].'|'.($item[4]['doel'] ?? '');
                if (! in_array($sleutel, $sleutels, true)) {
                    $bestaand[] = $item;
                    $sleutels[] = $sleutel;
                }
            }
            $doel[$groep] = $bestaand;
        }

        return $doel;
    };

    if ($gebruiker->rol === Rol::Bestuur) {
        $standaardMenu = $menus[Rol::Bestuur->value];
    } else {
        $standaardMenu = [];
        foreach ($gebruiker->alleRollen() as $r) {
            if ($r === Rol::Bestuur) {
                continue; // Het afgeschermde Bestuur-menu niet in een gemengd menu mengen.
            }
            $rolMenu = $menus[$r->value]
                ?? (in_array($r, [Rol::Relatiebeheerder, Rol::Stagecoordinator], true)
                    ? $relatieMenu
                    : ($r === Rol::Hrmedewerker ? $hrMenu
                        : ($r === Rol::Balie ? $balieMenu
                            : ($r === Rol::Bibliotheek ? $biebMenu : null))));
            if ($rolMenu !== null) {
                $standaardMenu = $mergeMenu($standaardMenu, $rolMenu);
            }
        }
        if (empty($standaardMenu)) {
            $standaardMenu = $menus[Rol::Studentenzaken->value];
        }
    }

    $inCursusmodule = request()->routeIs('cursussen.*') || request()->routeIs('cursisten*');
    $inHrmodule = request()->routeIs('hr.*') || request()->routeIs('medewerkers*') || request()->routeIs('dienstverbanden*') || request()->routeIs('hrdocumenten*') || request()->routeIs('verlof*') || request()->routeIs('verzuim*') || request()->routeIs('ziekmeldingen*') || request()->routeIs('gesprekken*') || request()->routeIs('gespreksdoelen*') || request()->routeIs('competentiescores*') || request()->routeIs('checklist*') || request()->routeIs('hr.*');
    $inRelatiemodule = request()->routeIs('relatiebeheer.*') || request()->routeIs('relaties*') || request()->routeIs('contactpersonen*') || request()->routeIs('contactmomenten*') || request()->routeIs('stages*') || request()->routeIs('stageplaatsen*') || request()->routeIs('agenda*') || request()->routeIs('afspraken*') || request()->routeIs('relatietaken*') || request()->routeIs('overeenkomsten*') || request()->routeIs('relatiedocumenten*');
    // Het Schoolbestuur houdt ALTIJD het eigen Bestuur-menu, ook wanneer het een
    // rapportage van een andere module opent. Zo verschijnt er nooit een module-menu
    // met beheerlinks waarvoor het Bestuur geen rechten heeft (die 403 zouden geven),
    // en blijft de gebruiker in de vertrouwde Bestuur-context.
    $inBaliemodule = request()->routeIs('balie') || request()->routeIs('balie.*');
    $inBiebmodule = request()->routeIs('bibliotheek.*');
    $menu = ($rol === Rol::Bestuur->value)
        ? $standaardMenu
        : ($inCursusmodule
            ? $cursusMenu
            : ($inRelatiemodule ? $relatieMenu
                : ($inHrmodule ? $hrMenu
                    : ($inBaliemodule ? $balieMenu
                        : ($inBiebmodule ? $biebMenu : $standaardMenu)))));

    // "Bibliotheek IUASR" — de catalogus als alleen-lezen raadpleegscherm, voor
    // IEDERE medewerker en in ELKE module. Binnen de bibliotheekmodule zelf staat
    // de catalogus al in het menu; daar wordt de link niet herhaald.
    if (! $inBiebmodule) {
        $menu['Bibliotheek IUASR'] = [
            ['Boek zoeken', 'catalogus', 'book', 'catalogus,catalogus.show'],
        ];
    }

    // Zelfservice "Mijn HR" is er voor iedere gekoppelde medewerker, ook buiten de
    // HR-module (bv. een docent met een personeelsdossier). Voeg de groep toe aan
    // het actieve menu wanneer die er nog niet in staat.
    if (! array_key_exists('Zelfservice', $menu) && $gebruiker->medewerker !== null) {
        $menu['Zelfservice'] = [
            ['Mijn HR', 'hr.mijn', 'eye', 'hr.mijn'],
            ['Mijn verlof', 'verlof.mijn', 'taak', 'verlof.mijn,verlof.create'],
        ];
    }
@endphp

@foreach ($menu as $titel => $items)
  <div class="iuasr-dash-sidebar__group">
    <div class="iuasr-dash-sidebar__title">{{ $titel }}</div>
    @foreach ($items as $item)
      @php
        [$label, $routeNaam, $ic, $actiefPatroon] = $item;
        // Optioneel 5e element: query-parameters (bv. ['doel' => 'vrijstelling']).
        // Zo kunnen twee items naar dezelfde route wijzen met een eigen context;
        // het 'doel' bepaalt ook welk item oplicht.
        $params = $item[4] ?? [];
        $isActief = request()->routeIs(explode(',', $actiefPatroon))
            && (request('doel') ?: null) === ($params['doel'] ?? null);
      @endphp
      <a class="iuasr-dash-sidenav {{ $isActief ? 'is-active' : '' }}"
         href="{{ Route::has($routeNaam) ? route($routeNaam, $params) : '#' }}">
        <span aria-hidden="true">{!! $icon($ic) !!}</span>
        <span>{{ $label }}</span>
      </a>
    @endforeach
  </div>
@endforeach
