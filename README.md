# IUASR — Intern Studentbeheersysteem (SIS)

Intern studentbeheersysteem voor Studentenzaken van IUASR. Vervangt een
verouderd Access/VBA-systeem. Draait op het interne netwerk (intranet),
strikt gescheiden van het publieke aanmeldportaal.

## Stack

- PHP + Laravel
- MySQL / InnoDB (echte foreign keys, surrogaatsleutels)
- Authenticatie via Microsoft Entra ID (SSO/OIDC)
- Intern, IP-beperkt

## Status

In opbouw — **Fase 0 (fundament)**. Zie **[PROGRESS.md](PROGRESS.md)** voor de
fasering, beslissingen en openstaande parameters, en **[CLAUDE.md](CLAUDE.md)**
voor de niet-onderhandelbare principes en AVG-grenzen.

## Belangrijke regels

- Bouwen per fase; niet vooruitlopen zonder akkoord.
- **Geen echte persoonsgegevens in ontwikkeling** — uitsluitend synthetische data.
- Er komt nooit een productiedatabase of echte persoonsgegevens in Git.
- Taal in UI en documentatie: Nederlands (U-vorm).

## Mappenstructuur

- `docs/` — ontwerp- en projectdocumentatie (FO, TO, datamodel, AVG/DPIA).
- `synthetische-data/` — synthetische seed-datasets (nooit echte data).
- Laravel-projectstructuur (`app/`, `database/`, `routes/`, …) volgt in Fase 1/2.
