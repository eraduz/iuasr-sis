# PROGRESS.md — IUASR Intern Studentbeheersysteem (SIS)

Continuiteitsbestand tussen sessies. Werk dit bij aan het einde van elke sessie.
Bouw per fase; ga nooit een fase vooruit zonder akkoord van de opdrachtgever.

---

## Projectstatus

- **Huidige fase:** Fase 0 — fundament & AVG-nulmeting
- **Laatst bijgewerkt:** 2026-07-06
- **Repo:** git@github.com:eraduz/iuasr-sis.git (nog niet gepusht — eerste push in
  overleg met de opdrachtgever)

---

## Faseoverzicht

Elke fase eindigt bij een verifieerbaar opleverpunt. Zet het vinkje pas als dat
opleverpunt aantoonbaar klaar is.

- [ ] **Fase 0 — Fundament & AVG-nulmeting** (in uitvoering)
  - Git-repo, remote, AVG-veilige `.gitignore`, PROGRESS.md, leeg mappenskelet.
  - Nog te doen: DPIA-opzet en synthetische seed-dataset (latere sessie).
- [ ] **Fase 1 — Functioneel Ontwerp (FO) + datamodel**
  - Genormaliseerd datamodel met surrogaatsleutels, rolmodel, gevoelige-data-plan.
- [ ] **Fase 2 — Technisch Ontwerp (TO)**
  - Laravel-projectopzet, Entra ID/OIDC-auth, InnoDB-schema, migratiestrategie.
- [ ] **Fase 3 — Kern-CRUD**
  - Student/inschrijving/opleiding-beheer door SZ (identiteit, geen cijfers).
- [ ] **Fase 4 — Cijfers + rolscheiding**
  - Genormaliseerde resultaatregels, docent-invoer eigen vak, server-side autorisatie.
- [ ] **Fase 5 — Rapporten + documenten**
  - Cijferlijsten, overzichten, documentgeneratie.
- [ ] **Fase 6 — Portaalkoppeling**
  - Koppeling met publiek aanmeldportaal (gescheiden regime).
- [ ] **Fase 7 — Migratie**
  - Migratie van echte data uit oud Access-systeem, laatste fase, onder toezicht FG.
- [ ] **Fase 8 — Uitbreidingen**
  - Latere modules na oplevering kern.

---

## Nog niet begonnen / expliciet uit scope

Niet bouwen tenzij expliciet gevraagd:

- **DUO/BRON-koppeling** — blijft handmatig, apart regime.
- **Betalingsmodule** — latere fase.
- **Moodle-provisioning** — latere fase.
- **E-mailautomatisering** — latere fase.
- **Migratie van echte data** — pas Fase 7, onder toezicht FG.

---

## Openstaande parameters (TE BEVESTIGEN — niet zelf verzinnen)

Deze zijn nog niet vastgesteld. Vraag de opdrachtgever; verzin geen waarden.

- [ ] **Studentnummerformaat** — bron noemt zowel 5 als 6 cijfers. Moet eenduidig.
- [ ] **Nummerbeleid bij heringstroom** — behoudt student oud nummer of nieuw nummer?
- [ ] **Voldoende-grens per opleiding** — welke cijfergrens telt als voldoende?
- [ ] **EC-drempels per opleiding** — normen voor voortgang/BSA per opleiding.

---

## Beslissingenlogboek

| Datum | Beslissing |
|------------|------------|
| 2026-07-06 | Stack vastgelegd: PHP + Laravel (geen kale PHP/WordPress). |
| 2026-07-06 | Database: MySQL / InnoDB met echte foreign keys, surrogaatsleutels overal. |
| 2026-07-06 | Authenticatie via Microsoft Entra ID (SSO/OIDC); geen eigen login. |
| 2026-07-06 | Cijfers genormaliseerd (resultaatregels + toetsonderdelen + weging); nooit blok-kolommen. |
| 2026-07-06 | Git-repo geinitialiseerd, remote origin gezet; eerste push in overleg. |
| 2026-07-06 | AVG-veilige `.gitignore`: alle DB-/dumpformaten, secrets en gevoelige-data-mappen uitgesloten. |

---

## AVG-grenzen (hard)

- **Geen echte persoonsgegevens in ontwikkeling** — bouwen en testen uitsluitend
  op synthetische data.
- **Synthetische data alleen** — nooit productiedata committen, in geen enkele fase.
- **BSN** — pas toevoegen na expliciet akkoord (mogelijk pas bij DUO-processen).
- **Migratie van echte data** — uitsluitend in de laatste fase, onder toezicht van
  de Functionaris Gegevensbescherming (FG).
- **Regel voor deze repo:** er komt NOOIT een productiedatabase of echte
  persoonsgegevens in Git. De aanwezige `IUTSTD-*.mdb` (oude Access-database)
  wordt door `.gitignore` structureel buiten Git gehouden en niet gecommit.

---

## Aandachtspunten voor volgende sessie

- **Composer ontbreekt** op de ontwikkelmachine — installeren vóór Fase 1
  (Laravel-scaffolding). PHP 8.5, Node 24, npm 11 en mysql-client 9.6 zijn aanwezig.
- **Oude Access-database `IUTSTD-met oudestudenten9-9.mdb` (129 MB)** staat nog in
  de repo-map. Bevat vermoedelijk echte persoonsgegevens. Overweeg dit bestand
  buiten de repo-map te verplaatsen, onder toezicht FG. Nu al door `.gitignore` gedekt.
- **Nog te doen in Fase 0:** DPIA-opzet en generatie synthetische seed-dataset.
