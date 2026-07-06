/* ============================================================
   IUASR SIS — gedeelde shell (header + sidebar + rolwisselaar)
   Rendert de app-shell op basis van de actieve rol (localStorage).
   Gebruik per pagina:
     <div id="sis-header"></div>
     <div class="iuasr-dash-app">
       <nav class="iuasr-dash-sidebar" id="sis-sidebar"></nav>
       <main class="iuasr-dash-main"> ... </main>
     </div>
     <script src="assets/sis-shell.js"></script>
     <script>SIS.render({ active: 'studenten' });</script>
   ============================================================ */
(function () {
  var STORE_KEY = 'sis_role';

  var ICON = {
    dash:   '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>',
    students:'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    plus:   '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>',
    refresh:'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>',
    userx:  '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="17" y1="8" x2="22" y2="13"/><line x1="22" y1="8" x2="17" y2="13"/></svg>',
    report: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg>',
    cert:   '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M8.21 13.89 7 22l5-3 5 3-1.21-8.11"/></svg>',
    book:   '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
    grade:  '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
    eye:    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>',
    users:  '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    db:     '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>',
    log:    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'
  };

  var ROLES = {
    studentenzaken: {
      label: 'Studentenzaken', user: 'Fatima Yıldız', initial: 'F',
      groups: [
        { title: 'Overzicht', items: [ { id:'dashboard', label:'Dashboard', href:'dashboard.html', icon:'dash' } ] },
        { title: 'Studenten', items: [
          { id:'studenten', label:'Studenten', href:'studenten.html', icon:'students', count:'342' },
          { id:'inschrijven', label:'Student inschrijven', href:'inschrijven.html', icon:'plus' },
          { id:'herinschrijven', label:'Herinschrijven', href:'herinschrijven.html', icon:'refresh' },
          { id:'uitschrijven', label:'Uitschrijven', href:'uitschrijven.html', icon:'userx' }
        ] },
        { title: 'Documenten', items: [
          { id:'rapporten', label:'Rapporten', href:'rapporten.html', icon:'report' },
          { id:'verklaringen', label:'Verklaringen', href:'verklaringen.html', icon:'cert' }
        ] }
      ]
    },
    docent: {
      label: 'Docent', user: 'dr. Yusuf Aydın', initial: 'Y',
      groups: [
        { title: 'Overzicht', items: [ { id:'dashboard', label:'Dashboard', href:'dashboard.html', icon:'dash' } ] },
        { title: 'Onderwijs', items: [
          { id:'mijn-vakken', label:'Mijn vakken', href:'mijn-vakken.html', icon:'book', count:'4' },
          { id:'cijferinvoer', label:'Cijferinvoer', href:'cijferinvoer.html', icon:'grade' }
        ] }
      ]
    },
    examencommissie: {
      label: 'Examencommissie', user: 'prof. Karima Nassar', initial: 'K',
      groups: [
        { title: 'Overzicht', items: [ { id:'dashboard', label:'Dashboard', href:'dashboard.html', icon:'dash' } ] },
        { title: 'Studenten', items: [ { id:'studenten', label:'Studenten', href:'studenten.html', icon:'students' } ] },
        { title: 'Cijfers', items: [ { id:'cijferoverzicht', label:'Cijferoverzicht', href:'cijferoverzicht.html', icon:'grade' } ] },
        { title: 'Rapporten', items: [ { id:'rapporten', label:'Rapporten', href:'rapporten.html', icon:'report' } ] }
      ]
    },
    directie: {
      label: 'Directie', user: 'drs. Bram de Wit', initial: 'B',
      groups: [
        { title: 'Overzicht', items: [ { id:'dashboard', label:'Dashboard', href:'dashboard.html', icon:'dash' } ] },
        { title: 'Studenten', items: [ { id:'studenten', label:'Studenten (beperkt)', href:'studenten.html', icon:'students' } ] },
        { title: 'Cijfers', items: [ { id:'cijferoverzicht', label:'Cijferoverzicht', href:'cijferoverzicht.html', icon:'eye' } ] },
        { title: 'Rapporten', items: [ { id:'rapporten', label:'Rapporten', href:'rapporten.html', icon:'report' } ] }
      ]
    },
    beheerder: {
      label: 'Beheerder', user: 'Ismail Kaya', initial: 'I',
      groups: [
        { title: 'Overzicht', items: [ { id:'dashboard', label:'Dashboard', href:'dashboard.html', icon:'dash' } ] },
        { title: 'Beheer', items: [
          { id:'gebruikers', label:'Gebruikers & rollen', href:'gebruikers.html', icon:'users' },
          { id:'opzoektabellen', label:'Opzoektabellen', href:'opzoektabellen.html', icon:'db' },
          { id:'audit-log', label:'Audit-log', href:'audit-log.html', icon:'log' }
        ] }
      ]
    }
  };

  var ORDER = ['studentenzaken','docent','examencommissie','directie','beheerder'];

  function getRole() {
    var r = localStorage.getItem(STORE_KEY);
    return ROLES[r] ? r : 'studentenzaken';
  }
  function setRole(r) { if (ROLES[r]) localStorage.setItem(STORE_KEY, r); }

  function esc(s){ return String(s).replace(/[&<>"]/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'})[c]; }); }

  function headerHTML(roleKey) {
    var role = ROLES[roleKey];
    var opts = ORDER.map(function (k) {
      return '<option value="'+k+'"'+(k===roleKey?' selected':'')+'>'+esc(ROLES[k].label)+'</option>';
    }).join('');
    return ''+
    '<header class="iuasr-dash-header">'+
      '<div class="iuasr-dash-header__inner">'+
        '<div class="iuasr-dash-header__brand">'+
          '<a class="iuasr-dash-header__logo" href="dashboard.html" aria-label="IUASR SIS"><img src="assets/logo-dark.png" alt="IUASR"></a>'+
          '<span class="role role--'+roleKey+'">'+esc(role.label)+'</span>'+
          '<span class="sis-pill-soft" style="letter-spacing:0.04em;">SIS · Studentbeheer</span>'+
        '</div>'+
        '<div class="iuasr-dash-header__spacer"></div>'+
        '<div class="sis-roleswitch" title="Wissel van rol (demo)">'+
          '<span class="sis-roleswitch__lbl">Rol</span>'+
          '<select id="sis-role-select" aria-label="Actieve rol">'+opts+'</select>'+
        '</div>'+
        '<div class="iuasr-dash-header__user" style="margin-left:6px;">'+
          '<a class="iuasr-dash-header__user-link" href="#">'+
            '<span class="avatar" aria-hidden="true">'+esc(role.initial)+'</span>'+
            '<span class="name">'+esc(role.user)+'</span>'+
          '</a>'+
          '<a class="logout" href="login.html">Uitloggen</a>'+
        '</div>'+
      '</div>'+
    '</header>';
  }

  function sidebarHTML(roleKey, active) {
    var role = ROLES[roleKey];
    return role.groups.map(function (g) {
      var items = g.items.map(function (it) {
        var cls = 'iuasr-dash-sidenav' + (it.id === active ? ' is-active' : '');
        var cnt = it.count ? '<span class="count">'+esc(it.count)+'</span>' : '';
        return '<a class="'+cls+'" href="'+it.href+'">'+
          '<span aria-hidden="true">'+(ICON[it.icon]||'')+'</span>'+
          '<span>'+esc(it.label)+'</span>'+cnt+'</a>';
      }).join('');
      return '<div class="iuasr-dash-sidebar__group">'+
        '<div class="iuasr-dash-sidebar__title">'+esc(g.title)+'</div>'+items+'</div>';
    }).join('');
  }

  function applyRoleVisibility(roleKey) {
    document.body.setAttribute('data-role', roleKey);
    // data-role-only="a b" → alleen tonen voor genoemde rollen
    document.querySelectorAll('[data-role-only]').forEach(function (el) {
      var list = el.getAttribute('data-role-only').split(/\s+/);
      el.style.display = list.indexOf(roleKey) === -1 ? 'none' : '';
    });
    // data-role-hide="a b" → verbergen voor genoemde rollen
    document.querySelectorAll('[data-role-hide]').forEach(function (el) {
      var list = el.getAttribute('data-role-hide').split(/\s+/);
      el.style.display = list.indexOf(roleKey) !== -1 ? 'none' : '';
    });
  }

  var SIS = {
    roles: ROLES,
    role: getRole,
    render: function (opts) {
      opts = opts || {};
      var roleKey = getRole();
      var h = document.getElementById('sis-header');
      var s = document.getElementById('sis-sidebar');
      if (h) h.innerHTML = headerHTML(roleKey);
      if (s) s.innerHTML = sidebarHTML(roleKey, opts.active || '');
      applyRoleVisibility(roleKey);
      var sel = document.getElementById('sis-role-select');
      if (sel) sel.addEventListener('change', function () {
        setRole(this.value);
        var stay = opts.roleStay; // pagina's die bij rolwissel op hun plek blijven
        location.href = stay ? location.pathname.split('/').pop() : 'dashboard.html';
      });
      if (typeof opts.onRole === 'function') opts.onRole(roleKey);
    }
  };
  window.SIS = SIS;
})();
