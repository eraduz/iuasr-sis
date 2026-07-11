# Module Relatiebeheer & Stagebeheer — Functioneel & Technisch Ontwerp

Interne SIS-module van IUASR voor het beheren van externe relaties (stagescholen,
werkveldorganisaties, samenwerkingspartners), hun contactpersonen, de contactmomenten,
de stage-/werkveldplaatsen en de plaatsing van studenten daarop, inclusief documenten,
overeenkomsten, taken, agenda, historie en rapportages.

> **Status:** ONTWERP — nog niet gebouwd. Dit document beschrijft de logica en fasering.
> Er wordt geen fase gebouwd zonder akkoord van de opdrachtgever (zie CLAUDE.md).
>
> **Laatst bijgewerkt:** 2026-07-11.

---

## 0. Uitgangspunten

### 0.1 Opleidingoverstijgend (belangrijk)

De module is **niet pabo-specifiek**. Zij wordt gebruikt door meerdere opleidingen:

- **PABO** — stage/werkplekleren op basisscholen (Samen Opleiden). Eerste implementatie.
- **BA Islamitische Theologie (ISLTH)** — praktijk-/werkveldstage bij o.a. moskeeën,
  gemeenschappen en maatschappelijke organisaties.
- **Master Islamitische Geestelijke Verzorging (MGV / "Master IGV")** — stage
  geestelijke verzorging bij zorginstellingen, justitie, defensie, ziekenhuizen.

Gevolg voor het ontwerp: de **kern is generiek en opleiding-gescoped**. Alles wat per
opleiding verschilt (soorten organisaties, benaming van begeleiders, stage-eisen) is
**configureerbaar via opzoektabellen**, niet hardcoded. PABO/Samen Opleiden is de eerste
concrete invulling, geen uitzondering in het datamodel.

De opleidinggebonden zichtbaarheid volgt hetzelfde patroon als de bestaande **Directie per
opleiding** (`directie_opleidingen`): een medewerker ziet uitsluitend de relaties en
stages van de opleiding(en) waaraan hij is gekoppeld; instellingsbrede rollen (Bestuur,
Beheer) zien alles.

### 0.2 Past binnen het multi-module platform

Dit wordt een nieuwe module in de bestaande `modules`-registry, naast Studentenzaken en
Cursussen Administratie (en de nog te bouwen modules Scriptie, HR). Sleutel: `relatiebeheer`.
Toegang afgeleid uit de rol (`Rol::moduleSleutels()`), keuzescherm na de login, eigen sidebar.

### 0.3 Niet-onderhandelbare principes (uit CLAUDE.md)

1. **Surrogaatsleutels overal.** Elke entiteit een betekenisloze PK; een leesbaar
   relatienummer/stagenummer is een uniek VELD, nooit een koppelsleutel.
2. **Rolscheiding server-side.** Autorisatie via Gates + `rol`-middleware, nooit alleen UI.
3. **Gevoelige data blijft binnen**, versleuteld waar nodig, toegang gelogd.
4. **Audit-logging** op inzage/mutatie van gevoelige gegevens (beoordelingen, contactgegevens).
5. **Genormaliseerd datamodel**, echte foreign keys, InnoDB.
6. **AVG-hard:** uitsluitend synthetische data in ontwikkeling; nooit productiedata in Git.
7. **Design system leidend** (`iuasr-dash-*`, `sis-*`); geen ad-hoc merkkleuren.
8. **UI in het Nederlands (U-vorm), geen emoji.** Handleidingen bij elke functie bijwerken.

---

## 1. AVG-kader voor deze module (hard)

Stagegegevens zijn AVG-gevoelig; het HBO/pabo-veld heeft hier expliciete afspraken over
(Samen Opleiden, Kennisnet/Aanpak IBP, praktijkovereenkomst). De harde grenzen:

- **GEEN leerling-/cliëntgegevens.** Het systeem registreert de RELATIE en de STAGE, nooit
  persoonsgegevens van basisschoolleerlingen (pabo) of cliënten/patiënten (MGV). Geen
  video/audio uit de klas of de zorgpraktijk. De stageschool/instelling is en blijft
  **verwerkingsverantwoordelijke** voor haar eigen leerling-/cliëntgegevens.
- **Contactpersonen en werkplekbegeleiders zijn persoonsgegevens van externen.** Grondslag:
  uitvoering van de (stage-/samenwerkings)overeenkomst en gerechtvaardigd belang. Vastleggen
  in het verwerkingsregister/DPIA. Minimale gegevensset; geen bijzondere persoonsgegevens.
- **De student valt tijdens de stage onder het AVG-regime van het stageschoolbestuur.** De
  afspraken staan in de **(tripartiete) praktijk-/stageovereenkomst** tussen opleiding,
  stageschool en student. Het systeem legt die overeenkomst en de ondertekening vast; het
  vervangt de overeenkomst niet.
- **Beoordelingen zijn gevoelig (gaan over de student).** Zelfde rolscheiding als cijfers:
  alleen stagebegeleider/examencommissie/directie; niet vrij zichtbaar, inzage gelogd.
- **Niets verwijderen — inactiveren.** Historie blijft zichtbaar. Contactpersonen en
  organisaties worden op inactief gezet, niet gewist. Er komt wel een **bewaartermijnbeleid**
  (te bevestigen, zie §9): externe contactpersonen zonder actieve relatie na X jaar
  anonimiseren.
- **Toegang gelogd** op inzage/mutatie van beoordelingen en van contactgegevens van externen.

---

## 2. Rollen & autorisatie

De opdrachtgever noemde: Relatiebeheerder, Stagecoördinator, Docent, Opleidingsmanager,
Backoffice. Conform de projectlijn (zoals bij de module Cursussen) breiden we de bestaande
`Rol`-enum uit in plaats van RBAC te herbouwen, en hergebruiken we bestaande rollen waar
mogelijk.

| Voorgestelde rol in SIS | Herkomst | Rechten in deze module |
|---|---|---|
| **Relatiebeheerder** (nieuw) | — | Organisaties, contactpersonen, contactmomenten, documenten, notities beheren binnen eigen opleiding(en). |
| **Stagecoördinator** (nieuw) | — | Alles van Relatiebeheerder + stageplaatsen en plaatsingen beheren, matching, stagestatus, agenda stagebezoeken. |
| **Docent** | bestaand | Als stagebegeleider/instituutsopleider: eigen begeleide stages, contactmomenten, beoordeling invoeren. |
| **Directie** (= Opleidingsmanager) | bestaand, opleidinggebonden | Alle relaties/stages van de eigen opleiding(en), rapportages; alleen-lezen op mutaties naar keuze. |
| **Studentenzaken / Backoffice** | bestaand | Ondersteunend: contactgegevens en documenten bijwerken. Rol "Backoffice" = Studentenzaken, tenzij opdrachtgever een aparte rol wenst. |
| **Schoolbestuur** | bestaand, instellingsbreed | Alleen-lezen dashboards en rapportages over alle opleidingen. |
| **Beheerder** | bestaand | Alles + opzoektabellen (organisatietypes, contactmoment-types), rol-/opleidingtoewijzing. |

**Besloten (2026-07-11):** er komen **twee nieuwe rollen** — `relatiebeheerder` en
`stagecoordinator`. Docent, Directie (= Opleidingsmanager), Studentenzaken (= Backoffice),
Bestuur en Beheer worden hergebruikt.

**Scoping.** Relatiebeheerder, Stagecoördinator, Docent en Directie zijn **opleidinggebonden**
(zien alleen hun opleiding[en]); Bestuur en Beheer zijn instellingsbreed. Implementatie via
scopes analoog aan `Student::scopeZichtbaarVoor()` en `directie_opleidingen`.

---

## 3. Datamodel

Alle tabellen: surrogaat-PK (`id`), `timestamps`, echte FK's (InnoDB), zachte inactivatie in
plaats van harde verwijdering waar historie relevant is.

```
Opleiding (bestaand)
   │  (elke relatie/stage is aan één of meer opleidingen gekoppeld → scoping)
   │
Organisatie ───────────────┐
   ├── Contactpersonen      │
   ├── Stageplaatsen        │
   ├── Stages ──────────────┼── Student (bestaand)  + Stagebegeleider (User/Docent)
   │                        │                        + Werkplekbegeleider (Contactpersoon)
   ├── Overeenkomsten ──────┼── OndertekendDocument (bestaande ondertekenmodule)
   ├── Contactmomenten      │
   ├── Relatietaken         │
   ├── Agenda-afspraken     │
   ├── Relatiedocumenten ───┼── (private schijf, versiebeheer, gelogd)
   ├── Notities             │
   └── Historie ────────────┘── (afgeleid uit audit-log + contactmomenten + notities)
```

### 3.1 `organisaties`

Stageschool, schoolbestuur, zorginstelling, moskee, samenwerkingspartner, enz.

| Veld | Type | Opmerking |
|---|---|---|
| `id` | PK | surrogaat |
| `relatienummer` | string, uniek | leesbaar, bv. `R26-0001` (jaarprefix + volgnr) |
| `naam` | string | |
| `kvk_nummer` | string, nullable | |
| `brin_nummer` | string, nullable | onderwijsspecifiek (pabo); leeg voor niet-scholen |
| `type_organisatie_id` | FK → opzoektabel | basisschool, schoolbestuur, zorginstelling, moskee, partner… |
| `adres`, `postcode`, `plaats`, `provincie` | string | |
| `website`, `telefoon`, `email` | string, nullable | algemeen e-mailadres |
| `actief` | boolean | inactiveren i.p.v. verwijderen |
| `opmerkingen` | text, nullable | |

Koppeling opleiding via pivot `organisatie_opleidingen` (een organisatie kan voor meerdere
opleidingen relevant zijn — bv. een instelling die zowel pabo- als MGV-stagiairs neemt).

### 3.2 `contactpersonen`

| Veld | Type | Opmerking |
|---|---|---|
| `id` | PK | |
| `organisatie_id` | FK | |
| `voornaam`, `achternaam`, `functie` | string | |
| `email`, `mobiel`, `telefoon` | string, nullable | persoonsgegeven extern |
| `afdeling` | string, nullable | |
| `voorkeur_communicatie` | enum | e-mail / telefoon / Teams |
| `linkedin` | string, nullable | |
| `actief` | boolean | |

### 3.3 `stageplaatsen`

Aanbod per organisatie (capaciteit), los van een concrete student.

| Veld | Type | Opmerking |
|---|---|---|
| `id`, `organisatie_id` | PK/FK | |
| `opleiding_id` | FK | voor welke opleiding |
| `leerjaar` | smallint, nullable | |
| `aantal_plaatsen`, `max_studenten` | smallint | bezetting = aantal actieve stages |
| `periode_id` | FK, nullable | hergebruik bestaande `perioden` (studiejaar/blok) |
| `eisen`, `specialisaties`, `werkdagen` | text/string, nullable | vrije invulling per opleiding |
| `actief` | boolean | |

### 3.4 `stages` (plaatsing student op organisatie)

| Veld | Type | Opmerking |
|---|---|---|
| `id` | PK | |
| `stagenummer` | string, uniek | leesbaar |
| `student_id` | FK → `studenten` | bestaande student |
| `organisatie_id` | FK | |
| `stageplaats_id` | FK, nullable | koppeling aan het aanbod (voor bezetting) |
| `opleiding_id` | FK | scoping |
| `stagebegeleider_id` | FK → `users` | instituutsopleider/stagebegeleider (docent) |
| `werkplekbegeleider_id` | FK → `contactpersonen`, nullable | groepsleerkracht/coach op locatie |
| `startdatum`, `einddatum` | date | |
| `status` | enum | aangevraagd / lopend / afgerond / afgebroken |
| `beoordeling` | enum, nullable | **voldoende / onvoldoende** (besloten 2026-07-11); GEVOELIG — rolscheiding + gelogd |
| `beoordeling_toelichting` | text, nullable | |

De **terminologie** (werkplekbegeleider vs. praktijkbegeleider, instituutsopleider vs.
stagedocent) verschilt per opleiding. **Besloten (2026-07-11):** labels én organisatietypes
zijn **per opleiding configureerbaar** (via opzoektabellen); de datastructuur blijft gelijk
(één begeleider op locatie + één vanuit de opleiding).

### 3.5 `contactmomenten`

| Veld | Type | Opmerking |
|---|---|---|
| `id`, `organisatie_id` | PK/FK | |
| `contactpersoon_id` | FK, nullable | |
| `stage_id` | FK, nullable | bv. een stagebezoek hangt aan een stage |
| `medewerker_id` | FK → `users` | wie had het contact |
| `type_id` | FK → opzoektabel | telefoon/e-mail/Teams/bezoek/stagebezoek/overleg/klacht/evaluatie |
| `datum`, `tijd` | date/time | |
| `onderwerp`, `samenvatting` | string/text | |
| `vervolgdatum` | date, nullable | genereert eventueel een taak |

Bijlagen via `relatiedocumenten`; actiepunten via `relatietaken`.

### 3.6 `overeenkomsten`

Samenwerkingsovereenkomst / convenant / stagecontract met **verloopdatum** (stuurt het
dashboard "contracten die verlopen").

| Veld | Type | Opmerking |
|---|---|---|
| `id`, `organisatie_id` | PK/FK | |
| `type` | enum | samenwerkingsovereenkomst / convenant / stagecontract |
| `startdatum`, `verloopdatum` | date | |
| `status` | enum | concept / getekend / verlopen / opgezegd |
| `ondertekend_document_id` | FK, nullable | **hergebruik bestaande ondertekenmodule** (SHA-256 + verificatiecode + waarmerk) |

### 3.7 `relatiedocumenten`

Documentbeheer met **versiebeheer**, op de PRIVATE schijf (buiten webroot), inzage/afgifte
gelogd — hergebruik van het bestaande documentmodule-patroon.

| Veld | Type | Opmerking |
|---|---|---|
| `id` | PK | |
| `koppelbaar_type`, `koppelbaar_id` | morphs | organisatie of stage (polymorf) |
| `categorie` | enum | stagecontract/convenant/beoordeling/verslag/correspondentie/foto/certificaat |
| `bestandspad` | string | private schijf |
| `versie` | smallint | |
| `vorige_versie_id` | FK, nullable | versieketen |
| `geupload_door_id` | FK | gelogd |

### 3.8 `relatietaken`

Model naar **Outlook Taken / Microsoft Graph `todoTask`**, consistent met de bestaande
Takenlijst van Studentenzaken. Voorstel: **hergebruik de bestaande `taken`-structuur** en maak
de koppeling polymorf (optioneel aan organisatie/stage/contactpersoon), zodat er één
takenconcept in het systeem blijft.

Velden: titel, omschrijving, eigenaar (`toegewezen_id`), prioriteit, startdatum, vervaldatum,
status (open/bezig/afgerond), herinnering (dashboardsignalering), + koppeling. "Te laat" is
afgeleid (vervaldatum verstreken én niet afgerond), geen kolom — zoals in de huidige module.

### 3.9 `agenda_afspraken`

Planning van schoolbezoeken, stagebezoeken, evaluaties, overleggen, open dagen. Model naar
Microsoft Graph `event` (met het oog op latere Outlook-sync, net zoals taken naar `todoTask`).

| Veld | Type | Opmerking |
|---|---|---|
| `id` | PK | |
| `organisatie_id`, `stage_id` | FK, nullable | |
| `medewerker_id` | FK | |
| `type` | enum | schoolbezoek/stagebezoek/evaluatie/overleg/open dag |
| `datum`, `tijd_van`, `tijd_tot` | date/time | |
| `locatie`, `status` | string/enum | |

### 3.10 `notities`

Vrije notities per organisatie: categorie, tags, auteur, datum. Zelfde patroon als de
bestaande interne studentnotities.

### 3.11 Historie / tijdlijn

**Niets verwijderen; alles zichtbaar.** De historie is een AFGELEIDE tijdlijn, samengesteld
uit: audit-logregels (mutaties op organisatie/contactpersoon/stage/overeenkomst),
contactmomenten en notities — chronologisch, met datum, gebruiker en wijziging. Hergebruik de
bestaande `auditlogs`-tabel; er komt geen aparte historietabel voor mutaties.

---

## 4. Schermen (volgen het design system)

- **Modulekiezer-tegel** "Relatiebeheer" + eigen sidebar.
- **Organisatielijst** met filters (type, opleiding, actief, provincie) en zoeken.
- **Relatiekaart (360°)** per organisatie — het kernscherm. Tabbladen/panelen:
  contactpersonen, stageplaatsen, stages & studenten, begeleiders, contactmomenten,
  documenten, overeenkomsten, open acties (taken), agenda, notities, historie/tijdlijn.
- **Contactpersoon-, contactmoment-, stageplaats-, stage-, overeenkomst-, taak-, afspraak-
  formulieren.**
- **Stageplaatsings-/matchingscherm** (student ↔ beschikbare plaats).
- **Rolgericht dashboard** en **rapportages** (zie §6).

---

## 5. Workflow (voorbeeld PABO; generiek toepasbaar)

```
Nieuwe organisatie
      ↓
Contactpersoon toevoegen
      ↓
Kennismakingsgesprek (contactmoment)
      ↓
Samenwerkingsovereenkomst (ondertekend + verloopdatum)
      ↓
Stageplaatsen beschikbaar (aanbod/capaciteit)
      ↓
Student koppelen (plaatsing + begeleiders)
      ↓
Stage loopt (contactmomenten, stagebezoeken in agenda)
      ↓
Evaluatie & beoordeling (gevoelig, rolgescheiden)
      ↓
Nieuwe stageperiode / verlenging
```

Voor MGV/ISLTH is de keten identiek; alleen de organisatietypes (zorginstelling, moskee) en
begeleidersbenamingen verschillen — die zijn configureerbaar.

---

## 6. Dashboard & rapportages

**Dashboardtegels (rolafhankelijk):** nieuwe organisaties, open acties, vandaag bezoeken,
stageplaatsen beschikbaar, stageplaatsen tekort, contactmomenten vandaag, contracten die
verlopen, nieuwe documenten, te beoordelen stages.

**Rapportages:** aantal organisaties, contactpersonen, stageplaatsen; bezettingsgraad;
gemiddelde evaluatiescore; nieuwe samenwerkingen; actieve gekoppelde studenten; bezoeken per
organisatie; open taken; werkvoorraad per medewerker. Per opleiding gescoped; CSV-export
(gelogd). Hergebruik de bestaande `Statistiek`-aggregaties en chart-partials (bar/spark/donut,
server-gerenderd — intranet-veilig, geen externe libs).

---

## 7. Slimme functies (grotendeels latere fase)

- **Automatische herinneringen** — via dashboardsignalering (geen e-mail), consistent met de
  bestaande takenmodule.
- **Digitale ondertekening** van overeenkomsten — **hergebruik de bestaande ondertekenmodule**.
- **Geavanceerd zoeken/filteren** en **tijdlijn** — binnen de module.
- **Bestandsversiebeheer** — in `relatiedocumenten`.
- **Koppeling met het SIS** — native: studenten, opleidingen en docenten zitten al in dezelfde
  database. Dit is een structureel voordeel t.o.v. externe pakketten (bv. OnStage), waar de
  koppeling met het studentsysteem apart gebouwd moet worden.
- **Outlook-/Graph-integratie (agenda + e-mail koppelen)** — buiten de huidige intranet-only
  scope; alleen bouwen na expliciet akkoord (aparte fase, §8 Fase H).
- **Koppeling leeromgeving (Moodle)** — buiten scope (staat al zo in CLAUDE.md).

---

## 8. Fasering (stap voor stap, elk met verifieerbaar opleverpunt)

Ga nooit een fase vooruit zonder akkoord van de opdrachtgever. Elke fase eindigt bij een
aantoonbaar opleverpunt; daarna PROGRESS.md bijwerken, committen/pushen en beide handleidingen
actualiseren.

- **Fase A — Fundament & organisaties.** Module in de registry + rollen; organisaties-CRUD met
  configureerbaar type (opzoektabel), relatienummer, actief/inactief, opleidingkoppeling +
  scoping. *Opleverpunt:* organisaties beheren, zichtbaar per opleiding, tegel in de modulekiezer.
- **Fase B — Contactpersonen & relatiekaart (360°).** Contactpersonen-CRUD; het
  360°-relatiekaartscherm dat alle deelgebieden samenbrengt. *Opleverpunt:* volledig
  360°-overzicht per organisatie.
- **Fase C — Contactmomenten, notities & tijdlijn.** Contactmoment registreren (types),
  notities (categorie/tags), gecombineerde historie/tijdlijn, actiepunt → taak. *Opleverpunt:*
  chronologische tijdlijn met alle gebeurtenissen.
- **Fase D — Stagebeheer.** Stageplaatsen (aanbod/capaciteit) + plaatsing student ↔ organisatie
  met begeleiders, statusverloop en (rolgescheiden) beoordeling; bezettingsberekening.
  *Opleverpunt:* student geplaatst met beide begeleiders en een statusverloop.
- **Fase E — Taken & agenda.** Relatietaken (hergebruik takenmodel, polymorfe koppeling) +
  agenda voor bezoeken/evaluaties + dashboardsignalering (vandaag bezoeken, deadlines).
  *Opleverpunt:* taken + agenda + herinneringen op het dashboard.
- **Fase F — Documenten, overeenkomsten & ondertekening.** Documentbeheer met versiebeheer
  (private schijf, gelogd); overeenkomsten met verloopdatum; ondertekening via de bestaande
  module; signalering "contracten die verlopen". *Opleverpunt:* getekende overeenkomst
  gearchiveerd + verloopsignalering.
- **Fase G — Dashboards & rapportages.** Rolgericht dashboard en de rapportageset, CSV-export,
  hergebruik `Statistiek` + chart-partials. *Opleverpunt:* dashboard + rapporten per opleiding.
- **Fase H — Slimme functies & integraties (optioneel/later).** Outlook/Graph-agenda-sync,
  e-mail koppelen aan een relatie, geavanceerd zoeken over alles. Grotendeels buiten de
  intranet-only scope; per onderdeel apart akkoord.

---

## 9. Openstaande parameters (te bevestigen — niet zelf verzinnen)

**Besloten (2026-07-11):**

- **Rolinrichting:** twee nieuwe rollen `relatiebeheerder` + `stagecoordinator`; overige
  rollen bestaand hergebruiken (Docent, Directie = Opleidingsmanager, Studentenzaken =
  Backoffice, Bestuur, Beheer).
- **Beoordelingsschaal stage:** voldoende / onvoldoende.
- **Per opleiding configureerbaar:** stageterminologie (begeleiderslabels) én
  organisatietypes worden per opleiding ingesteld via opzoektabellen.

**Nog te bevestigen:**

1. **Organisatietypes — startlijst per opleiding** (pabo: basisschool, schoolbestuur,
   opleidingsschool; MGV: zorginstelling, ziekenhuis, justitie, defensie; ISLTH: moskee,
   gemeenschap, maatschappelijke organisatie). Welke exacte lijst per opleiding?
2. **Begeleiderslabels per opleiding:** welke benamingen willen PABO, ISLTH en MGV
   (werkplekbegeleider / praktijkbegeleider / coach; instituutsopleider / stagedocent)?
3. **Nummerformaat** voor relatienummer en stagenummer (analoog aan het studentnummer:
   jaarprefix + volgnummer?).
4. **Bewaartermijn** voor inactieve externe contactpersonen/organisaties (AVG) — na hoeveel
   jaar anonimiseren?
5. **Overeenkomst-ondertekening:** volstaat de bestaande hash-/waarmerkmodule, of is een
   tripartiete workflow (opleiding + school + student tekenen) gewenst — en tekent de externe
   partij digitaal in dit systeem of buiten?
6. **Outlook-integratie:** wel of niet, en zo ja onder welk regime (intranet vs. Microsoft 365)?

---

## 10. Bronnen (internetonderzoek onderwijs-CRM & stagebeheer)

- OnStage — stagebegeleiding & afstuderen voor het HBO: <https://www.onstagesoftware.com/en/suitable-for/uoas>
- HvA Stagebureau Pabo (rollen werkplekbegeleider/schoolopleider, OnStage, tripartiete
  overeenkomst): <https://www.hva.nl/samenwerken/stagebureaus/pabo>
- HU Pabo — Samen Opleiden / Werkplekleren (rollen en begeleiding):
  <https://husite.nl/stage-en-afstudeerinformatie/hu-pabo-werkplekleren/begeleiden-van-werkplekleren/>
- Kennisnet / Aanpak IBP — Antwoorden op vragen rondom privacy bij stages van pabo's en
  lerarenopleidingen: <https://normenkaderibp.kennisnet.nl/voorbeelddocumenten/>
- Platform Samen Opleiden & Professionaliseren — privacy bij stages:
  <https://www.platformsamenopleiden.nl/>
- GAC — Strategisch relatiebeheer voor onderwijsinstellingen (CRM-domeinen: centraal
  relatiedossier, stage- & scriptiebeheer, alumni): <https://www.gac.nl/nieuws/crm/strategisch-relatiebeheer/>
- Tribe CRM — CRM voor onderwijs (relatiebeheer & 360°-beeld): <https://tribecrm.nl/sectoren/onderwijs/>
