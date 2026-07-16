# Module Scriptie Coördinatie

Ontwerp van de module die het volledige **scriptietraject** van een student
begeleidt, van de toelatingscontrole tot de afronding. Opdrachtgever, 2026-07-15
(`Documents/wij beginnen nu Scriptie coördinatie.txt`). Opgeleverd 2026-07-16.

## Doel en scope

Eén overzichtelijke **werkflowpagina met elf tabbladen** — elk tabblad is één stap
met een eigen formulier en/of checklist en een eigen status. De coördinator
regisseert het traject; de begeleiding, goedkeuring en beoordeling zijn per stap
belegd bij de verantwoordelijke rol. Uitgangspunt uit de opdracht: *"formulier goed
en alles moet overzichtelijk zijn; men kan alles makkelijk te zien"*.

## Rollen (actoren → systeemrollen)

| Actor in het proces        | Systeemrol                    |
|----------------------------|-------------------------------|
| Scriptiecoördinator        | **`Rol::Scriptiecoordinator`** (nieuw, opleidinggebonden) |
| Scriptiebegeleider         | `Rol::Docent` (ziet alleen eigen begeleidingstrajecten) |
| Opleidingsdirecteur        | `Rol::Directie` (opleidinggebonden) |
| Scriptiecommissie / examinator | `Rol::Examencommissie` |
| Schoolbestuur (meekijken)  | `Rol::Bestuur` (alleen-lezen) |
| Student                    | data-subject (`App\Models\Student`), geen inlogrol |

De rechten zijn een **unie over alle rollen** van een gebruiker (multi-rol). De
opleiding-scoping hergebruikt de pivot `directie_opleidingen`.

## Toelatingseisen (stap 1 / Scriptie Kandidaten)

Een student mag beginnen als hij:
- minimaal **180 EC** heeft behaald (`config('sis.scriptie.toelating_ec')`, instelbaar);
- **Methoden en Technieken I** heeft afgerond;
- **Methoden en Technieken II** heeft afgerond.

De vakken worden **per opleiding op vakcode** herkend (`config('sis.scriptie.toelating_vakken')`):
ISLTH `B-MT04-A` / `B-MT04-B` (scriptie `B-BR01`), MGV `M-GV16a` / `M-GV16b`
(scriptie `M-GV17`). Geslaagd = de bestaande EC-/cesuurlogica kent EC toe
(`Cijferberekening::ec() > 0`). PABO volgt later (geen scriptie geconfigureerd).

## De elf stappen

| # | Stap | Verantwoordelijke | Bevat |
|---|------|-------------------|-------|
| 1 | Toelatingseisen | Coördinator | controle-momentopname (EC, M&T I/II) |
| 2 | Scriptievoorstel | Coördinator | voorstelformulier |
| 3 | Onderwerpbeoordeling | Examencommissie | checklist + besluit |
| 4 | Begeleider | Coördinator | begeleidergegevens |
| 5 | Scriptieovereenkomst | Coördinator | overeenkomst + 4× digitale goedkeuring + ondertekende PDF |
| 6 | Plan van Aanpak | Docent | PvA-formulier + document + begeleidingsgesprekken |
| 7 | Definitieve inlevering | Docent | inleverchecklist + eindversie |
| 8 | Plagiaatcontrole | Coördinator | controlegegevens + rapport |
| 9 | Beoordeling | Examencommissie | beoordelingsonderdelen + cijfers |
| 10 | Verdediging | Examencommissie | verdedigingsgegevens + presentatie |
| 11 | Afronding | Coördinator | afrondingschecklist; afvinken sluit het traject |

Stappen worden **sequentieel** afgevinkt (de vorige moet gereed zijn) en uitsluitend
door de verantwoordelijke rol (`Scriptiestap::magAfvinkenDoor()`). Per stap een eigen
set toegestane statussen uit de opdracht (`Scriptiestap::statussen()`).

## Datamodel

- **`scripties`** — hoofdrecord per inschrijving (uniek); identiteit + alle
  1:1-stapvelden als echte kolommen; overall status (`lopend/afgerond/afgebroken`).
- **`scriptie_stapstanden`** — 11 rijen; status + gereed-markering per stap (stuurt
  de tabbladen).
- **`scriptie_checklistpunten`** — ja/nee-punten (stap 3, 7, 9, 11); label per traject
  bewaard.
- **`scriptie_gesprekken`** — begeleidingsgesprekken (stap 6).
- **`scriptie_documenten`** — private schijf, versiebeheer via `vorige_versie_id`.
- De ondertekende overeenkomst hergebruikt `ondertekende_documenten`.

## Hergebruik

- **Ondertekening** (`App\Support\Documentondertekening::ondertekenHtml`) voor de
  scriptieovereenkomst-PDF (SHA-256 + verificatiecode + waarmerk).
- **Private-disk-upload** met versiebeheer (patroon van `relatie_documenten`).
- **EC/cesuur** (`Transcript`, `Cijferberekening`) voor de toelatingscontrole.
- **AuditLogger** op elke mutatie, inzage en uitgifte.

## Bewust buiten scope

- Een studentportaal/-login: de student is data-subject; de staf voert de gegevens in.
- Live plagiaatdienst-koppeling (Ephorus/Turnitin): de uitkomst wordt handmatig
  vastgelegd en het rapport geüpload.
- Automatische doorschrijving van het scriptiecijfer naar de cijfermodule: het cijfer
  wordt in dit traject vastgelegd; koppeling met `resultaten` is een latere keuze.

## Tests

`tests/Feature/ScriptieModuleTest.php` — keuzescherm, toegang/rolscheiding, traject
starten (11 stappen + checklistpunten), stapformulier, sequentieel afvinken,
academische stap alleen door examencommissie, checklist, bestuur alleen-lezen,
afronden sluit het traject. 11 tests.
