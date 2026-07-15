# Changelog — IUASR Management Systeem

Alle noemenswaardige wijzigingen staan hier, nieuwste bovenaan. Het versienummer
staat onderaan elke pagina (`config/sis.php` → `sis.versie`) en volgt semantische
versienummering: **MAJOR.MINOR.PATCH**. De uitgebreide, technische geschiedenis
staat in `PROGRESS.md`.

Werkwijze bij een release: verhoog `sis.versie`, voeg hieronder een kort blok toe
(PATCH = bugfixes, MINOR = nieuwe functies, MAJOR = ingrijpende wijzigingen) en
noem de datum.

## [1.10.0] — 2026-07-15

- **De catalogus is nu te navigeren.** Met 11.000 titels waren het 441 pagina's met
  alleen vorige/volgende. Nu:
  - een **A–Z-balk** boven de lijst — klik een letter en u ziet alleen die titels
    (de knop **#** vangt titels die met een cijfer of Arabisch schrift beginnen);
  - een keuze voor het **aantal per pagina** (25, 50, 100 of 200);
  - een **verbeterde paginabalk** met paginanummers, eerste/laatste en een
    sprongveld ("naar pagina …"), in plaats van alleen ‹ ›.
  Op alle drie de catalogusschermen: het beheerscherm, de alleen-lezen kaart voor
  collega's en de publieke zoekpagina. De verbeterde paginabalk geldt voor het hele
  systeem.

## [1.9.1] — 2026-07-15

- **Artikel toevoegen is eenvoudiger.** Het kon alleen op de uitgavepagina, drie
  niveaus diep. Nu staat er een formulier **Artikel toevoegen** op de
  tijdschriftpagina zelf: u kiest een bestaande uitgave of vult een nieuw
  uitgavenummer in (die uitgave wordt dan meteen aangemaakt) en voegt het artikel
  in één keer toe.
- **Bij het aanmaken van een tijdschrift** kunt u nu meteen een eerste uitgave met
  een paar artikelen invoeren. Optioneel; laat u het leeg, dan maakt u alleen het
  tijdschrift aan.

## [1.9.0] — 2026-07-14

- **Artikelen zijn nu volledig te beheren.** Toevoegen kon al; **wijzigen en
  verwijderen** ontbraken. Op de uitgavepagina klapt u bij elk artikel een
  formulier open (titel, auteurs, pagina's, trefwoorden, beschrijving) en kunt u het
  ook verwijderen. Elke mutatie wordt gelogd, ook wát er is verwijderd.
- Vanaf de tijdschriftpagina gaat u met **Artikelen beheren** direct naar de juiste
  uitgave.
- **Module hernoemd**: "Scriptie Administratie" heet voortaan **Scriptie
  Coördinatie**. Alleen de zichtbare naam; de sleutel `scriptie` blijft ongewijzigd,
  dus routes en rechten blijven intact.

## [1.8.1] — 2026-07-14

- **De artikelen staan nu op de tijdschriftpagina zelf.** U hoefde niet langer per
  uitgave door te klikken: elke uitgave is een uitklapbaar blok met de artikelen
  erin (titel, auteur, pagina's, Arabische titel), met bovenaan het totaal aantal
  uitgaven en artikelen en een link naar Artikelen zoeken voor dit tijdschrift.
  Zowel in de bibliotheekmodule als op de alleen-lezen kaart voor collega's.
- **Scherm "Dubbele tijdschriften"** (Beheer): zoekt plankregels uit de boekenlijst
  die bij een tijdschrift met uitgaven horen, en stelt voor ze samen te voegen —
  met behoud van exemplaren, rekcode, auteurs, talen en opmerkingen. Samenvoegen
  gebeurt alleen na aanvinken; een tijdschrift dat zelf uitgaven heeft, wordt nooit
  opgeslokt. Op de huidige gegevens levert dit **geen** voorstellen op: de 566
  plankregels uit de boekenlijst blijken losse stukken (met een artikeltitel als
  naam), geen dubbele tijdschriften.

## [1.8.0] — 2026-07-13

- **Tijdschriftinhoud geïmporteerd**: 9.397 artikelen in 571 uitgaven, uit twee
  bronnen — het Engelse Excel-bestand (15.994 regels) en het Arabische
  Word-bestand (4.926 alinea's). Per artikel: titel, auteur, pagina's en de
  Arabische vertaling van de titel. Onder **Artikelen zoeken** vindt u een artikel
  terug op titel, auteur, trefwoord of tijdschriftnaam.
- Nieuw commando `bibliotheek:tijdschriften {bestand} [--proef] [--forceren]
  [--overgeslagen=rapport.csv]`, dat zowel .xlsx als .docx leest en herhaalbaar is.
- **Bij twijfel wordt er niets geraden.** Een artikel wordt alleen vastgelegd als
  het tijdschrift én de uitgave bekend zijn; een auteur alleen als de naam
  overtuigend een naam is. Regels die niet in de structuur passen worden
  overgeslagen en gerapporteerd met regelnummer en reden.
- **Niets gaat verloren**: de volledige oorspronkelijke bronregel blijft bij elk
  artikel bewaard en is doorzoekbaar.
- **ISBN-verrijking afgerond**: alle 4.393 Nederlandse, Engelse en Turkse titels
  zijn bevraagd. 837 zekere correcties toegepast; **613 titels hebben nu een ISBN**
  en 823 een uitgavejaar. 1.404 twijfelgevallen staan klaar onder Verrijking om met
  de hand te beoordelen.

## [1.7.0] — 2026-07-13

- **Publicatiesoort is nu een opzoektabel die u zelf beheert.** Boek, tijdschrift en
  digitaal document stonden vast in de code; **cd** en **dvd** zijn toegevoegd en
  nieuwe soorten voegt de bibliotheek voortaan zelf toe onder **Beheer → Soorten &
  tabellen**, samen met talen, vakgebieden en kasten.
- Een soort draagt twee vlaggen die het **gedrag** bepalen, geen etiket:
  *fysieke exemplaren* (boek, cd, dvd — een digitaal document niet) en
  *uitgaven met artikelen* (tijdschrift). Een nieuwe soort werkt daardoor meteen
  correct: geen exemplaren waar ze niet horen, geen uitleenknop bij iets digitaals.
- Het dashboard telt per soort uit de tabel, dus een nieuwe soort verschijnt vanzelf
  als tegel.
- **Verwijderen kan alleen als er niets aan hangt** (een soort met titels, een taal
  aan een boek, een kast met exemplaren blijft staan) — anders een nette melding en
  de suggestie om de waarde op inactief te zetten.

## [1.6.2] — 2026-07-13

- **Zijbalk van de bibliotheekmodule opgesplitst per onderwerp.** Stond als één
  lijst van negen items onder het kopje "Bibliotheek". Nu: **Overzicht**,
  **Collectie** (catalogus, boekreeksen, artikelen zoeken, publicatie toevoegen),
  **Uitlenen** (uitleningen, boek uitlenen), **Rapportage**, en **Beheer**
  (importeren, verrijking, e-mailsjablonen) — het onderhoud onderaan, niet tussen
  het dagelijks werk.

## [1.6.1] — 2026-07-13

- **Zijbalk geordend op onderwerp.** De menugroepen stonden in de volgorde waarin
  ze toevallig waren samengevoegd (bij multi-rol) of later bijgeplakt. Nu één vaste
  volgorde: overzicht → de eigen administratie → geld → documenten en rapportage →
  gedeelde voorzieningen (Bibliotheek IUASR, Zelfservice) → hulp en beheer. Een
  groep die nog geen plek heeft gekregen, komt achteraan op alfabet — zichtbaar,
  niet verstopt.

## [1.6.0] — 2026-07-13

- **Publieke zoekpagina voor de bibliotheek-PC** (`/bibliotheek-zoeken`): zonder
  login kan een student opzoeken of een boek er is, in welke taal, op welk rek het
  ligt en of het beschikbaar is. Alleen GET, alleen bibliografische gegevens — geen
  leners, geen uitleenhistorie, geen interne opmerkingen. Met een verzoeklimiet.
- **Netwerkbeperking nu daadwerkelijk afgedwongen.** `SIS_TOEGESTANE_IPS` stond al
  in de configuratie, maar er was geen middleware die er iets mee deed: het systeem
  was in de praktijk vanaf elk netwerk bereikbaar. De nieuwe middleware
  `IpBeperking` controleert elk verzoek — ook het inlogscherm en de publieke
  bibliotheekpagina. Leeg gelaten = geen filter (lokale ontwikkeling); op de RDP en
  Plesk hoort het intranetbereik ingevuld te zijn.

## [1.5.0] — 2026-07-13

- **Bibliotheek IUASR**: de catalogus als **alleen-lezen** raadpleegscherm voor
  iedere ingelogde medewerker, zichtbaar in elke module. Docenten en collega's
  zoeken een boek op titel, auteur, ISBN of rekcode, zien in welke kast het ligt en
  of er een exemplaar beschikbaar is. Geen enkele mutatieroute: uitlenen, innemen en
  het beheer van de catalogus blijven achter de rol Bibliotheek.
- **Rek / plaats zichtbaar gemaakt.** De rekcode uit de oude Excel-bibliotheek
  ("F. 1070") stond al als apart veld in de database maar werd nergens getoond. Nu
  een eigen kolom in beide catalogusschermen, bovenaan op de boekenkaart, in de
  CSV-export, doorzoekbaar, en invulbaar bij een nieuwe titel.
- ISBN als eigen kolom in de catalogus.

## [1.4.0] — 2026-07-13

Verrijking van de bibliotheekcatalogus met ISBN, uitgavejaar en de juiste
schrijfwijze van de titel, via Open Library.

- **Alleen Nederlands, Engels en Turks** (keuze opdrachtgever). Arabische titels
  staan in de bron door elkaar in Arabisch schrift en transliteratie en worden door
  deze bronnen slecht gedekt; daar zou corrigeren neerkomen op gokken.
- **Zekerheid boven volledigheid** ("skip als je onzeker bent"): er wordt alleen
  iets gewijzigd bij een titelgelijkenis van minstens 92% én een overeenkomende
  auteur. Twijfelgevallen worden vastgelegd als *onzeker* en NIET toegepast; die
  lijst loopt een mens na via het scherm **Verrijking**, met Overnemen of Afwijzen.
- Een bestaand ISBN wordt nooit overschreven; de oude titel blijft bewaard en elke
  wijziging wordt gelogd (oud → nieuw), dus alles is terug te draaien.
- Commando `bibliotheek:verrijken --limiet=N [--proef]`, herhaalbaar: een al
  bevraagde titel wordt niet opnieuw bevraagd.
- Uitgaand verkeer alleen naar de whitelist-host; SSL-verificatie blijft aan.
- Gemeten opbrengst op deze collectie: ongeveer 15% zekere treffers.

## [1.3.0] — 2026-07-13

Import van de bestaande Excel-bibliotheek, en zelf-filterende filterbalken.

- **Importwizard** (`bibliotheek:importeren` + scherm onder Bibliotheek → Importeren).
  Eerst proefdraaien (inlezen, rapporteren, niets opslaan), dan pas importeren.
  Idempotent: een tweede import maakt niets dubbel.
- **Normalisatie van de bron**, die met de hand is gegroeid: het vakgebied komt uit
  de **rekletter** (A = Tafsir, F = Fiqh, …) in plaats van uit de vakgebiedkolom met
  144 spellingvarianten; taalfouten worden gecorrigeerd (Arabish → Arabisch,
  Nederlans → Nederlands); waarden die geen taal zijn (woordenboeken, Grammatica)
  verhuizen naar de opmerking. Frans, Duits, Spaans en Albanees toegevoegd als taal.
  De oorspronkelijke bronwaarden blijven bewaard in het opmerkingveld.
- **Aantal = exemplaren**: "F. 143" met aantal 3 wordt één titel met F.143-1, F.143-2
  en F.143-3, in kast F. Een onwaarschijnlijk aantal (de bron bevat één keer 41306)
  wordt teruggebracht tot 1 exemplaar en gemeld.
- **Streaming XLSX-lezer** in plaats van het hele bestand in het geheugen laden;
  12.470 regels paste niet in 128 MB.
- Resultaat op de dev-database: **10.969 titels, 15.928 exemplaren, 7.795 auteurs**,
  geen enkele titel zonder vakgebied. 188 bronregels overgeslagen (geen titel).
- **Filterbalken filteren nu vanzelf**: een keuzelijst of datum aanpassen verzendt
  het formulier direct; het zoekveld blijft op Enter werken. Met een regel die de
  actieve filters toont.

## [1.2.0] — 2026-07-13

Nieuwe module **Bibliotheek** (op verzoek van de opdrachtgever), met een nieuwe
rol `bibliotheek`.

- **Titel en exemplaar gescheiden** (model van Koha e.a.): de titel staat één keer
  in de catalogus, de fysieke boeken hangen eronder als exemplaren met een eigen
  serienummer, kastplek en status. Drie exemplaren van hetzelfde boek zijn dus los
  uitleenbaar zonder de titel te dupliceren.
- **Meertalig**: Arabisch, Turks, Engels en Nederlands. Een publicatie kan meerdere
  talen hebben; opslaan, zoeken en sorteren werkt met Arabisch schrift.
- **Boekreeksen**: de gedeelde gegevens één keer invoeren en alle delen in één
  scherm toevoegen (Tafsir Ibn Kathir deel 1–4). Elk deel blijft los uitleenbaar.
- **Tijdschriften met artikelen**: uitgaven met artikelen, auteurs, pagina's en
  trefwoorden. Zoeken op artikeltitel, auteur, trefwoord of tijdschriftnaam laat
  meteen zien in welk tijdschrift een artikel staat.
- **Uitlenen en innemen**: de lener is een bestaande student of medewerker (echte
  foreign key). Inname legt de staat van het materiaal vast; bij schade gaat het
  exemplaar automatisch uit de uitleen. 'Te laat' en 'op tijd' zijn afleidingen,
  geen kolommen.
- **E-mail**: vijf sjablonen (door de Beheerder aanpasbaar) met variabelen, altijd
  met CC naar de bibliotheekpostbus. Elke verzending wordt gelogd, ook een mislukte;
  een mislukte mail blokkeert de uitlening nooit. Automatische herinneringen via de
  scheduler (`bibliotheek:herinneringen`, idempotent).
- **Dashboard en rapportage**: KPI's, waarschuwingen (te laat, binnen 3 dagen terug),
  grafieken per maand/vakgebied/taal, overzichten per vakgebied, auteur en jaar, de
  meest uitgeleende titels, en een CSV-export.
- **Integratie met Studentenzaken**: te late studenten verschijnen op het
  Studentenzaken-dashboard met studentnummer, naam, materiaal, dagen te laat en het
  aantal verstuurde waarschuwingen.
- **Boete nog niet gebouwd**: de boeteregels zijn niet vastgesteld; er worden geen
  bedragen verzonnen. De uitleentermijnen (21 dagen student, 60 docent) staan in
  `config/sis.php` en zijn TE BEVESTIGEN.

## [1.1.0] — 2026-07-13

Nieuwe module **Balie / Receptie** (op verzoek van de opdrachtgever), met een
nieuwe rol `balie`.

- **Eén chronologisch logboek** (`balie_registraties`) voor alle vijf de stromen
  aan de ingang: telefoon inkomend/uitgaand, bezoekers, en post ontvangen/verzonden.
  Discriminatoren `soort` + `richting`; geen vijf losse tabellen, zodat zoeken,
  filteren en exporteren één implementatie is.
- **Bezoekersregistratie met aankomst én vertrek.** "Nu in het pand" wordt afgeleid
  (bezoek zonder vertrekmoment), niet opgeslagen; bedoeld als ontruimingsoverzicht.
- **Echte foreign keys**: `medewerker_id` (bestemd voor / afspraak met) met
  `afdeling` als terugval, en `geregistreerd_door_user_id`.
- **Zoeken en filteren** over onderwerp, naam, organisatie, afdeling en toelichting;
  filters op soort, richting, medewerker, periode en "alleen nog aanwezig".
  **CSV-export** die dezelfde filters respecteert.
- **Rolscheiding**: de Balie registreert en wijzigt; alleen het Schoolbestuur leest
  mee (alleen-lezen). De Directie heeft geen toegang: het logboek is een
  werkregister van de balie, geen opleidingsinformatie. De rol is bewust smal — geen studentdossiers, cijfers of
  personeelsdossiers. Registraties worden nooit verwijderd; aanmaak, wijziging,
  afmelden en export worden gelogd.
- Migratie breidt de enum `users.rol` uit en maakt de modulerij plus het
  balie-account aan (idempotent), zodat een bestaande database alleen
  `php artisan migrate` nodig heeft.

## [1.0.1] — 2026-07-13

Configuratie per omgeving rechtgezet. Gedeelde instellingen staan nu in Git in
plaats van in `.env`, zodat een nieuwe omgeving (RDP, Plesk) ze met `git pull`
krijgt en niet meer per machine kan afwijken.

- **Tijdzone hersteld naar Europe/Amsterdam.** Laravel 12 legt `timezone` hard op
  `UTC` vast zolang er geen eigen `config/app.php` is; `APP_TIMEZONE` in `.env`
  werd daardoor genegeerd en de hele applicatie rekende in UTC. `config/app.php`
  is toegevoegd (tijdzone, taal `nl`, faker `nl_NL`).
- **Sessies worden nu versleuteld.** De framework-standaard `SESSION_ENCRYPT=false`
  won van de bedoelde instelling. `config/session.php` legt `encrypt` op `true`
  vast; `secure` blijft per omgeving instelbaar (https → `true`).
- **`.env.example` teruggebracht** tot uitsluitend machine-specifieke sleutels
  (URL, database, debug, cookie, IP-bereik, e-mail) plus de gedeelde `APP_KEY`.
  De dode sleutel `SIS_STUDENTNUMMER_CIJFERS` is vervangen door
  `SIS_STUDENTNUMMER_VOLGNUMMER_LENGTE`, die de code daadwerkelijk leest.

## [1.0.0] — 2026-07-13

Eerste vastgelegde versie. Het systeem is uitgegroeid van een studentbeheersysteem
tot een intern managementplatform met meerdere modules:

- **Studentenzaken** — studenten en inschrijvingen, lifecycle (in-/uit-/her­inschrijven,
  schorsen, afstuderen/alumni met afstudeerproces), documenten, verklaringen,
  collegegeld in termijnen, rapporten.
- **Cijfers** — genormaliseerde resultaten, vaststelling, cijferlijst/transcript,
  aanwezigheidsregistratie, EC- en overgangsrapporten (rolgescheiden).
- **Cursussen Administratie** — cursussen, cursisten, cursusgelden, rapportage.
- **Relatiebeheer & Stage** — organisaties, contactpersonen, stages, agenda,
  documenten en dashboards (opleidinggebonden).
- **HR / Personeelszaken** — medewerkers, dienstverbanden, verlof en verzuim,
  gesprekken, onboarding/offboarding, signaleringen (Wet Verbetering Poortwachter).
- **Platform** — rolgescheiden dashboards, multi-rol per gebruiker, een samengevoegd
  schoolbestuursoverzicht, donkere modus (standaard) en een HTML-handleiding met
  hoofdstuknavigatie.

Volledige details en de onderbouwing per beslissing: zie `PROGRESS.md`.
