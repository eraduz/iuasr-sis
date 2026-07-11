# Module HR / Personeelszaken — Functioneel & Technisch Ontwerp

Interne SIS-module van IUASR voor personeelsadministratie: medewerkersregistratie,
dienstverband en FTE, verlof & verzuim (met self-service), gespreks- en
performancecyclus, organisatiestructuur, onboarding/offboarding en HR-rapportages.

> **Status:** ONTWERP — bouw per fase, met akkoord van de opdrachtgever (CLAUDE.md).
> **Laatst bijgewerkt:** 2026-07-11. **Bron:** `hr presoneelszaken promt.txt`.

---

## 0. Uitgangspunten

### 0.1 Stack — we volgen het bestaande platform (niet de generieke promptsuggestie)

De prompt noemt React/Vue + Node/.NET + PostgreSQL + Azure. Ons platform ligt vast
op **PHP/Laravel + MySQL/InnoDB + Microsoft Entra ID (SSO/OIDC)** en één leidend
design system (CLAUDE.md, niet-onderhandelbaar). De HR-module wordt daarom als
**nieuwe module in het bestaande multi-module platform** gebouwd (naast
Studentenzaken, Cursussen, Relatiebeheer), met dezelfde conventies. De door de
prompt gewenste **Azure AD-authenticatie** sluit hier naadloos op aan (Entra ID).

De placeholder-module `hr` staat al in de `modules`-registry; die wordt geactiveerd
zodra Fase A is opgeleverd.

### 0.2 Meertaligheid (NL/EN)

De prompt vraagt NL/EN. Het hele SIS is **Nederlandstalig** (CLAUDE.md: UI en
documentatie in het Nederlands, U-vorm). We bouwen daarom **NL** en houden Engels
als latere uitbreiding (i18n) buiten de MVP. Dit is een bewuste afwijking van de
prompt, om consistent te blijven met het platform.

### 0.3 Niet-onderhandelbare principes (CLAUDE.md)

1. **Surrogaatsleutels overal**; leesbare nummers (personeelsnummer) zijn een uniek
   VELD, nooit een koppelsleutel.
2. **Rolscheiding server-side** (Gates + `rol`-middleware).
3. **Gevoelige data** (BSN) versleuteld, inzage gelogd — hergebruik van de
   bestaande `VersleuteldGevoeligVeld`-cast en `AuditLogger`.
4. **Audit-logging** op inzage/mutatie van gevoelige gegevens.
5. **Genormaliseerd datamodel**, echte FK's, InnoDB.
6. **AVG-hard:** uitsluitend synthetische data in ontwikkeling; nooit productiedata
   in Git. Personeelsdossiers zijn extra gevoelig — strikte rolscheiding + logging.
7. **Design system leidend**; UI in het Nederlands, geen emoji.
8. **Handleidingen** bijwerken bij elke functie.

---

## 1. Systeemarchitectuur (tekstdiagram)

```
Browser (intern, IP-beperkt) ── Entra ID SSO ──► Laravel-app (SIS)
                                                   │
         ┌─────────────────────────────────────────┼───────────────────────────┐
         │ Module Studentenzaken   Module Cursussen  Module Relatiebeheer        │
         │ Module HR / Personeelszaken  ◄── deze module                          │
         └─────────────────────────────────────────┼───────────────────────────┘
                                                   │
   Controllers (App\Http\Controllers\Hr\*) ─► Models (App\Models\*) ─► MySQL/InnoDB
                                                   │
   Support: FteBerekening, Verlofsaldo, AuditLogger, Documentondertekening (hergebruik)
   Views: resources/views/hr/* (design system, server-gerenderd, chart-partials)
```

- **Moduletoegang** volgt de rol (`Rol::moduleSleutels()`), keuzescherm na de login.
- **Self-service** (medewerker) loopt via de eigen, aan een `user` gekoppelde
  `medewerker`-record — geen aparte inlog, maar de bestaande SSO/dev-login.

---

## 2. Rollen & rechten

Conform de projectlijn breiden we de bestaande `Rol`-enum uit (geen RBAC-herbouw).

| Rol (nieuw/bestaand) | Rechten in HR |
|---|---|
| **HR-medewerker** (nieuw) | Volledige personeelsadministratie: medewerkers, dienstverbanden, documenten, verlof registreren/afhandelen, verzuim, gesprekken, rapportages. |
| **Manager** (nieuw) | Eigen team: teamoverzicht, **verlof goedkeuren/afwijzen**, ziekmeldingen zien, gesprekken voeren/vastleggen, teamrapportage. |
| **Medewerker (self-service)** | Elke ingelogde gebruiker die aan een `medewerker` is gekoppeld: **eigen** gegevens inzien, **verlof aanvragen**, eigen verlofsaldo, eigen gesprekken/documenten. Geen aparte rol — afgeleid uit de koppeling `medewerkers.user_id`. |
| **Beheerder** (bestaand) | Alles + opzoektabellen (afdelingen, functies, verloftypen). |
| **Schoolbestuur** (bestaand) | Instellingsbrede HR-rapportage, alleen-lezen (FTE, verzuim, bezetting). |

**Manager-hiërarchie:** `medewerkers.manager_id` (self-ref). Een Manager ziet de
medewerkers waarvan hij leidinggevende is (need-to-know); HR ziet iedereen.

---

## 3. Datamodel (tabellen)

Alle tabellen: surrogaat-`id`, `timestamps`, echte FK's.

### 3.1 `afdelingen`
`id`, `code` (uniek), `naam`, `bovenliggende_afdeling_id` (self-ref, nullable),
`manager_id` (→ `medewerkers`, nullable), `actief`. Teams = afdelingen met een
bovenliggende afdeling (één boom, geen aparte teamtabel nodig voor de MVP).

### 3.2 `functies` (opzoektabel)
`id`, `code`, `naam`, `categorie` (docent / staf / management), `actief`.

### 3.3 `medewerkers` — personeelsmaster
| Veld | Type | Opmerking |
|---|---|---|
| `id` | PK | |
| `personeelsnummer` | uniek | leesbaar (bv. `P260001`) |
| `user_id` | FK → users, nullable | self-service login (Entra/dev) |
| `docent_id` | FK → docenten, nullable | koppeling bestaand docentprofiel |
| `manager_id` | FK → medewerkers, nullable | leidinggevende |
| `afdeling_id` | FK → afdelingen, nullable | |
| `functie_id` | FK → functies, nullable | |
| `voornaam`, `tussenvoegsel`, `achternaam`, `aanhef` | | |
| `geboortedatum` | date | |
| `bsn` | versleuteld | `VersleuteldGevoeligVeld`, inzage gelogd |
| `adres`, `postcode`, `woonplaats`, `telefoon`, `email`, `email_prive` | | |
| `status` | enum | actief / ziek / verlof / uit_dienst |
| `actief` | bool | |

### 3.4 `dienstverbanden` — contracthistorie
`id`, `medewerker_id` FK, `contracttype` (vast/tijdelijk), `startdatum`,
`einddatum` (nullable), `uren_per_week` (decimal), `fte` (decimal, **afgeleid** uit
uren ÷ voltijdsnorm), `functie_id`, `afdeling_id`, `opmerking`. Meerdere
dienstverbanden per medewerker (historie/verlenging); het lopende bepaalt de status.

### 3.5 `verlofaanvragen`
`id`, `medewerker_id`, `verloftype` (enum: vakantie / bijzonder / ouderschap /
studie), `van`, `tot`, `uren` (afgeleid of ingevuld), `status`
(aangevraagd/goedgekeurd/afgewezen/ingetrokken), `beoordelaar_id` (→ users),
`beoordeeld_op`, `reden`, `opmerking_beoordelaar`. Workflow: **aanvraag → manager
goedkeurt → HR registreert**.

### 3.6 `verlofsaldi`
`id`, `medewerker_id`, `jaar`, `verloftype`, `recht_uren`, (opgenomen = afgeleid uit
goedgekeurde aanvragen). Saldo = recht − opgenomen (afgeleid, niet dubbel opgeslagen).

### 3.7 `ziekmeldingen`
`id`, `medewerker_id`, `ziek_van`, `hersteld_op` (nullable), `percentage`
(deelverzuim), `opmerking`, `gemeld_door_id`. Verzuimrapportage afgeleid.

### 3.8 `gesprekken`
`id`, `medewerker_id`, `type` (beoordeling/functionering/exit), `datum`,
`gespreksvoerder_id` (→ users), `status` (gepland/gehouden/afgerond),
`samenvatting`, `feedback`. Detailregels voor doelen/competenties:

### 3.9 `gespreksdoelen` / `competentiescores`
`gesprek_id` FK, `omschrijving`/`competentie`, `score`/`status`. (KPI's, feedback,
competentiebeoordeling — één-op-veel onder een gesprek.)

### 3.10 `hr_documenten`
Per medewerker documenten (contract, diploma, overig) — **hergebruik van het
documentmodule-patroon** (private schijf, versie, gelogd). Categorieën:
contract / diploma / identiteitsbewijs / correspondentie / overig.

### 3.11 `onboarding_taken` / `offboarding_taken` (checklists)
`medewerker_id`, `titel`, `verantwoordelijke_id`, `gereed`, `gereed_op`. Sjabloon
bij in/uitdiensttreding; afvinkbaar (naar het model van de bestaande Takenlijst).

### 3.12 Historie / audit
Mutaties op medewerker/dienstverband/BSN via de bestaande `auditlogs`. Een
afgeleide tijdlijn per medewerker (zoals de relatiekaart-tijdlijn).

---

## 4. Schermen (design system)

- **Modulekiezer-tegel** "HR / Personeelszaken" + eigen sidebar.
- **HR-dashboard**: aantallen (medewerkers, FTE, in/uit dienst), openstaande
  verlofaanvragen, actuele ziekmeldingen, geplande gesprekken, aflopende contracten.
- **Medewerkerslijst** (filters: afdeling, functie, status, contracttype) + zoeken.
- **Medewerkerkaart (360°)**: persoons-/dienstverbandgegevens, verlof, verzuim,
  gesprekken, documenten, onboarding/offboarding, historie/tijdlijn.
- **Verlof**: self-service aanvraagformulier; manager-goedkeurscherm; HR-overzicht.
- **Verzuim**: ziek-/herstelmelding; verzuimrapportage.
- **Gesprekken**: plannen, formulier (doelen/feedback/competenties), historie.
- **Self-service "Mijn HR"**: eigen gegevens, eigen verlof + saldo, eigen gesprekken.
- **Rapportages**: medewerkers per afdeling, verzuimpercentage, FTE-overzicht; CSV.

---

## 5. Routes / API-endpoints (basis, Laravel-web)

Prefix `hr`, rolgescheiden groepen. (Web-routes; dezelfde controllers vormen de
"API".)

```
GET  /hr                         hr.dashboard            (HR, Manager, Beheer, Bestuur)
GET  /hr/medewerkers             medewerkers.index       (HR, Beheer, Bestuur; Manager=team)
GET  /hr/medewerkers/{m}         medewerkers.show
GET/POST/PUT  medewerkers.create/store/edit/update       (HR, Beheer)
POST /hr/medewerkers/{m}/dienstverband  dienstverband.store
GET  /hr/verlof                  verlof.index            (HR, Manager)
POST /hr/verlof                  verlof.store            (self-service)
POST /hr/verlof/{v}/beoordelen   verlof.beoordelen       (Manager, HR)
POST /hr/ziekmeldingen           ziekmelding.store       (HR, Manager)
POST /hr/ziekmeldingen/{z}/herstel  ziekmelding.herstel
GET/POST  gesprekken.*           (HR, Manager)
GET  /hr/mijn                    hr.mijn                 (self-service: eigen dossier)
POST /hr/medewerkers/{m}/documenten  hrdocumenten.store  (HR, Beheer)
GET  /hr/rapport                 hr.rapport + export.csv (HR, Beheer, Bestuur)
```

Autorisatie: `rol:`-middleware per groep + `abort_unless($model->zichtbaarVoor())`
(Manager = eigen team; self-service = eigen record).

---

## 6. Fasering (elk met verifieerbaar opleverpunt)

- **Fase A — Fundament & medewerkersregistratie (MVP-kern).** Module + rollen
  (HR-medewerker, Manager); tabellen `afdelingen`, `functies`, `medewerkers`,
  `dienstverbanden`; **FTE afgeleid**; personeelsnummer; BSN versleuteld + gelogd;
  medewerkers-CRUD + kaart; documenten. *Opleverpunt:* medewerker met dienstverband
  en FTE, rolgescheiden.
- **Fase B — Verlof & verzuim (MVP).** Verlofaanvraag (self-service) → manager
  goedkeuring → HR; verloftypen + saldo; ziek-/herstelmelding; dashboardsignalering.
- **Fase C — Gesprekken & performance.** Beoordelings-/functionerings-/exitgesprek,
  formulieren (doelen/feedback/competenties), historie, notificatie (dashboard).
- **Fase D — Organisatiestructuur & rapportages.** Afdelingen/teams-boom,
  manager-hiërarchie; rapportages (per afdeling, verzuim%, FTE) + CSV.
- **Fase E — Onboarding/offboarding.** Checklists met sjablonen, afvinkbaar.
- **Fase F — Dashboard, self-service "Mijn HR" & agenda.** Rolgericht HR-dashboard;
  eigen dossier; iCal-export van gesprekken/verlof (intranet-veilig).
- **Fase G — Slimme functies (optioneel).** Globaal zoeken, aflopende-contract- en
  verzuimsignaleringen. **Buiten scope:** live Teams/Outlook-integratie en e-mail
  (externe provider; intranet-only) en NL/EN-i18n — als toekomst genoteerd.

**MVP = Fasen A + B** (registratie + verlof/verzuim), aangevuld met een basaal
dashboard.

---

## 7. Parameters

**Besloten (2026-07-11):**
- **Voltijdsnorm = 40 uur/week** → FTE = uren ÷ 40 (afgeleid). Vastgelegd in
  `config/sis.php` (`sis.hr.voltijd_uren`), per medewerker/contract berekend.
- **BSN: veld klaar, standaard UIT** — versleuteld veld + gelogde inzage, via config
  uitgeschakeld tot akkoord FG (`sis.hr.bsn_ingeschakeld`, default false), net als
  bij studenten.
- **Verlof: manager keurt goed/af; HR is terugval** wanneer er geen manager is. HR
  ziet en registreert altijd alles.

**Nog te bevestigen (defaults gebruikt):**
- **Verlofrechten** per verloftype (uren/jaar) en of dit met FTE schaalt.
- **Personeelsnummerformaat** — default `P` + jaar + volgnummer (bv. `P260001`).
- **Self-service reikwijdte** in de MVP (default: eigen gegevens + verlof + saldo).
