# CLAUDE.md — IUASR Intern Studentbeheersysteem (SIS)

## Wat dit is
Intern studentbeheersysteem voor Studentenzaken van IUASR. Vervangt een
verouderd Access/VBA-systeem. Draait op het interne netwerk (intranet),
strikt gescheiden van het publieke aanmeldportaal. Zie
`IUASR-Plan-van-Aanpak.docx` voor het volledige ontwerp.

## Stack (vastgelegd)
- PHP + **Laravel** (geen kale PHP, geen WordPress)
- **MySQL / InnoDB** — échte foreign keys, geen tekstuele koppelsleutels
- Auth via **Microsoft Entra ID (SSO/OIDC)** — bouw NOOIT een eigen login
- Draait intern, IP-beperkt

## Design system (leidend)
- De SIS-schermen volgen het design system uit **`IUASR/iuasr-sis/`** — dit is
  LEIDEND. De genummerde HTML-schermen daar en het design system zijn het
  referentiepunt voor elke UI. De overige mappen onder `IUASR/` (homepage,
  aanmeldportaal, `pages/`) horen bij een andere/publieke site en zijn NIET
  leidend voor het interne SIS.
- Het design system is overgenomen naar `public/assets/` (`css/sis.css`,
  `css/iuasr-plugin-dash.css`, `js/sis-shell.js`) en gekoppeld in
  `resources/views/layouts/app.blade.php`. Wijzig de merk-tokens/kleuren niet
  ad hoc; bouw nieuwe schermen met de bestaande `iuasr-dash-*` en `sis-*` klassen.
- Fonts: DM Serif Display (koppen) + Fira Sans (tekst). Kleuren o.a.
  `--priColor100` #1E1446, `--secColor100` #C8102E, heritage-groen #285C4D, goud #D69A2D.

## Niet-onderhandelbare principes
1. **Surrogaatsleutels overal.** Elke entiteit heeft een betekenisloze,
   systeem-gegenereerde PK. Het leesbare studentnummer is een uniek VELD,
   nooit een koppelsleutel.
2. **Rolscheiding vanaf de eerste regel code.** Rollen: SZ, Docent,
   Examencommissie, Directie, Admin. SZ beheert identiteit/inschrijving maar
   ziet en muteert GEEN cijfers. Docent voert in voor eigen vak. Autorisatie
   ALTIJD server-side afdwingen, nooit alleen in de UI.
3. **Gevoelige data blijft binnen.** BSN en rekeningnummer: versleuteld,
   toegang gelogd, alleen bevoegde rollen. Deze velden verlaten het intranet
   nooit via een koppeling.
4. **Audit-logging** op elke inzage/mutatie van cijfers en BSN
   (wie, wat, wanneer, welk record).
5. **Cijfers genormaliseerd** in losse resultaatregels met toetsonderdelen +
   weging. NOOIT terug naar vaste blok-kolommen (BL1–BL4) zoals het oude systeem.

## AVG — hard
- **Geen echte persoonsgegevens in ontwikkeling.** Bouw en test uitsluitend
  op **synthetische** data. Echte migratie pas in de laatste fase, onder
  toezicht van de Functionaris Gegevensbescherming.
- Genereer een synthetische seed-dataset; commit NOOIT productiedata.
- BSN alleen toevoegen na expliciet akkoord (mogelijk pas bij DUO-processen).

## Werkwijze in deze repo
- **Lees eerst de context, dan pas bouwen.** Vóór elke fase die gebouwd wordt,
  worden ALTIJD eerst uitgelezen: (1) de git-commits (`git log`), (2) `PROGRESS.md`
  en (3) deze `CLAUDE.md`. Zo staat vast waar het project staat, welke
  beslissingen al genomen zijn en welke parameters nog openstaan. Begin geen
  fase zonder deze drie te hebben gelezen.
- **Werk elke fase bij in de continuïteitsbestanden.** Na afronding van een fase
  worden `PROGRESS.md` (status, vinkjes, beslissingenlogboek) en waar nodig
  `CLAUDE.md` bijgewerkt, waarna de code wordt gecommit en direct gepusht.
- **Bouw per fase, niet alles tegelijk.** Volg de fasering uit het PvA.
  Elke fase eindigt bij een verifieerbaar opleverpunt; ga niet door voordat
  dat aantoonbaar klaar is.
- Fase 1–3 volledig op synthetische data. Portaalkoppeling en migratie zijn
  latere fasen.
- Vraag om bevestiging vóór destructieve acties (migraties die data wissen,
  schema-drops).
- `php -l` / linting groen vóór elke commit. Migraties reversible.
- Taal in UI en documentatie: Nederlands (U-vorm), geen emoji.
- **Handleidingen bijhouden.** Bij ELKE nieuwe of gewijzigde functie worden de
  twee PDF-handleidingen bijgewerkt: `resources/views/pdf/handleiding-medewerkers.blade.php`
  (eindgebruikers) en `resources/views/pdf/handleiding-technisch.blade.php`
  (technisch beheer/data-recovery). Genereer daarna opnieuw met
  `php artisan handleidingen:genereren` (bestanden in `docs/handleidingen/`).

## Uit scope (niet bouwen tenzij expliciet gevraagd)
- DUO/BRON-koppeling (blijft handmatig, apart regime)
- Betalingsmodule, Moodle-provisioning, e-mailautomatisering → latere fasen

## Openstaande parameters (vraag ernaar, verzin ze niet)
- Studentnummerformaat (bron noemt 5 én 6 cijfers — moet eenduidig)
- Nummerbeleid bij heringstroom
- Voldoende-grens en EC-drempels per opleiding