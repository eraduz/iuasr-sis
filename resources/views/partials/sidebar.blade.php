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
            'Cijfers' => [
                ['Cijferoverzicht', 'cijferoverzicht', 'grade', 'cijferoverzicht'],
                ['Cijferlijst', 'cijferlijst', 'report', 'cijferlijst'],
                ['Leerjaar-herbeoordeling', 'overgang', 'grade', 'overgang'],
                ['EC-rapport', 'ec-rapport', 'report', 'ec-rapport'],
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
            ],
            'Onderwijs' => [['Aanwezigheid', 'presentieoverzicht', 'check', 'presentieoverzicht']],
            'Rapporten' => [['Rapporten', 'rapporten.inzage', 'report', 'rapporten.inzage']],
            'Documenten' => [['Ondertekende documenten', 'ondertekening', 'cert', 'ondertekening*']],
        ],
        Rol::Bestuur->value => [
            'Overzicht' => [
                ['Bestuur', 'bestuur', 'dash', 'bestuur'],
                ['Dashboard', 'dashboard', 'dash', 'dashboard'],
            ],
            'Studenten' => [['Alle studenten', 'studenten.index', 'students', 'studenten.*']],
            'Onderwijs' => [['Aanwezigheid', 'presentieoverzicht', 'check', 'presentieoverzicht']],
            'Rapporten' => [['Alumni', 'rapporten.alumni', 'cert', 'rapporten.alumni']],
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
            'Studenten' => [['Alle studenten', 'studenten.index', 'students', 'studenten.*']],
            'Beheer' => [
                ['Gebruikers & rollen', 'gebruikers', 'users', 'gebruikers'],
                ['Opzoektabellen', 'opzoektabellen', 'db', 'opzoektabellen'],
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
            ['Organisaties', 'relaties', 'students', 'relaties,relaties.show,relaties.edit'],
            ['Stages', 'stages', 'cert', 'stages,stages.edit,stages.create,stageplaatsen.create,stageplaatsen.edit'],
        ],
    ];
    if ($gebruiker->magRelatiebeheer()) {
        $relatieMenu['Relatiebeheer'][] = ['Organisatie toevoegen', 'relaties.create', 'plus', 'relaties.create'];
    }

    // Standaardmenu buiten een module. De relatiebeheerder en stagecoördinator
    // hebben geen eigen rol-menu in $menus; hun thuisbasis is de Relatiebeheer-module.
    $standaardMenu = $menus[$rol]
        ?? (in_array($rol, [Rol::Relatiebeheerder->value, Rol::Stagecoordinator->value], true)
            ? $relatieMenu
            : $menus[Rol::Studentenzaken->value]);

    $inCursusmodule = request()->routeIs('cursussen.*') || request()->routeIs('cursisten*');
    $inRelatiemodule = request()->routeIs('relaties*') || request()->routeIs('contactpersonen*') || request()->routeIs('contactmomenten*') || request()->routeIs('stages*') || request()->routeIs('stageplaatsen*');
    $menu = $inCursusmodule
        ? $cursusMenu
        : ($inRelatiemodule ? $relatieMenu : $standaardMenu);
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
