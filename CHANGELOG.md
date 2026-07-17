# Changelog — IUASR Management Systeem

Alle noemenswaardige wijzigingen staan hier, nieuwste bovenaan. Het versienummer
staat onderaan elke pagina (`config/sis.php` → `sis.versie`) en volgt semantische
versienummering: **MAJOR.MINOR.PATCH**. De uitgebreide, technische geschiedenis
staat in `PROGRESS.md`.

Werkwijze bij een release: verhoog `sis.versie`, voeg hieronder een kort blok toe
(PATCH = bugfixes, MINOR = nieuwe functies, MAJOR = ingrijpende wijzigingen) en
noem de datum.

## [1.18.1] — 2026-07-17

- **Hersteld: Arabisch in de Plesk-dump.** De demo-dump toonde op Plesk alleen
  vreemde tekens. Oorzaak: de dump was met PowerShell nabewerkt, en dat leest een
  bestand zonder BOM als Windows-1252 in plaats van UTF-8 — waardoor alle
  Arabische tekst dubbel gecodeerd raakte en er een BOM voor kwam te staan. De
  database zelf was al die tijd in orde. De dump is opnieuw gemaakt en
  gecontroleerd: het Arabisch overleeft nu de export, de gzip én een testimport.
- **Hersteld: "Access denied ... Database: iuasr_sis_test" op de RDP.** De
  `.env.testing` die gisteren aan Git was toegevoegd, bevatte het
  databasewachtwoord van één machine. Omdat `php artisan serve` `APP_ENV`
  doorgeeft aan de webserver, laadde elk webverzoek op een machine met
  `APP_ENV=testing` in `.env` ineens dat bestand — en dus de verkeerde database.
  `.env.testing` staat nu **niet meer in Git** en wordt per machine afgeleid uit
  uw eigen `.env` door `scripts/env-testing.ps1` (loopt automatisch mee met
  `dev.ps1`). **Zet in uw `.env` `APP_ENV=local`**; `dev.ps1` waarschuwt nu als
  dat niet zo is.
- **Tests draaien nu op elke machine.** `phpunit.xml` legde ook host, poort,
  gebruiker en wachtwoord vast — waarden die per machine verschillen. Alleen de
  testdatabasenaam staat daar nu nog hard (die mag nooit wegvallen, anders wist
  `RefreshDatabase` de ontwikkeldatabase); de inloggegevens komen uit uw eigen
  `.env`.

## [1.18.0] — 2026-07-17

- **Geanonimiseerde demo-dump voor Plesk.** Twee nieuwe commando's:
  `sis:demo-anonimiseren` vervangt alle persoonsgegevens door synthetische, en
  `sis:demo-controleren` toetst het resultaat en slaat alarm zodra er iets
  doorheen glipt. Zo kunt u een demo op de publieke Plesk-server zetten mét de
  volledige bibliotheekcatalogus, maar zonder één echt persoonsgegeven.
- **Wat blijft en wat gaat.** Blijft: curriculum, 11.009 bibliotheektitels, 9.397
  artikelen, opzoektabellen, de 99 Schone Namen en alle aantallen — de demo blijft
  realistisch. Gaat: namen, adressen, e-mail, geboortedata, alle vrije tekst, het
  audit-logboek, de ondertekende documenten en de noodtoegang.
- **Veiligheidsgrendel.** `sis:demo-anonimiseren` weigert te draaien tenzij de
  databasenaam op `_demo` eindigt. Het commando overschrijft onomkeerbaar, en kan
  daarmee onmogelijk uw ontwikkeldatabase raken — ook niet als u het per ongeluk
  daar aanroept.
- **Bevinding (belangrijk).** De ontwikkeldatabase blijkt **echte**
  persoonsgegevens te bevatten: ~3.500 historische studentdossiers (1998–2026) en
  echte personeelsnamen. BSN en rekeningnummer zijn leeg. Dat wijkt af van de
  AVG-regel in `CLAUDE.md` (bouwen en testen uitsluitend op synthetische data).
  Zie `PROGRESS.md` — dit verdient een besluit van de opdrachtgever en de FG.

## [1.17.0] — 2026-07-17

- **Systeemmeldingen op elke pagina.** De Beheerder kan via **Beheer →
  Systeemmeldingen** een bericht plaatsen dat bovenaan **elke pagina van elke
  module** verschijnt — bijvoorbeeld: *"Vandaag is het systeem vanaf 18.00 uur
  niet beschikbaar wegens onderhoud."*
- **Verschijnt en verdwijnt vanzelf.** U geeft op vanaf wanneer en tot wanneer;
  standaard **24 uur**. Daarna is de melding weg zonder dat iemand iets hoeft te
  doen. U kunt een melding ook vooruit klaarzetten door *Vanaf* in de toekomst te
  leggen, en met **Nu stoppen** direct van alle schermen halen.
- **Drie soorten:** Mededeling, Let op en Urgent. Een urgente melding kan de
  medewerker niet wegklikken; bij de andere twee mag dat, en dan onthoudt zijn
  browser dat. Past u de melding daarna aan, dan verschijnt hij opnieuw — ook bij
  wie hem al had weggeklikt, zodat een correctie juist die mensen bereikt.
- **Gericht of voor iedereen.** Standaard ziet iedereen de melding; u kunt hem ook
  beperken tot bepaalde rollen, bijvoorbeeld alleen de bibliotheek.
- **Historie blijft.** Verlopen meldingen blijven in het overzicht staan — zo is
  terug te zien wat er is omgeroepen en door wie. Na 30 dagen worden ze
  automatisch opgeruimd.

## [1.16.0] — 2026-07-17

- **99 Schone Namen in de zijbalk.** Bovenaan het menu staat voortaan een Schone
  Naam van Allah (Asma ul-Husna) met de Arabische tekst en de Nederlandse
  betekenis, die **elke vijf minuten** wisselt. Alle 99 Namen zijn ingeladen; u
  hoeft alleen nog de afbeeldingen toe te voegen. Zonder afbeelding toont de
  zijbalk de Arabische tekst, dus het werkt meteen.
- **Iedereen ziet op hetzelfde moment dezelfde Naam.** Welke Naam er staat volgt
  uit de klok, niet uit uw eigen bezoek. Daardoor loopt de reeks door terwijl u
  het systeem gebruikt — bij een gewone carrousel zou hij bij elke paginawissel
  weer bij nummer 1 beginnen.
- **Eigen spreuken toevoegen** via Beheer → Quotes: kop, Arabische tekst,
  Nederlandse betekenis, bron en een afbeelding. Per quote aan of uit te zetten
  met één klik, en de volgorde is instelbaar.
- **Afbeeldingen:** vierkant, **456 × 456 pixels**, PNG met doorzichtige
  achtergrond, goud of wit lijnwerk. In de zijbalk staat hij op 152 pixels — dat
  is 4 cm.
- **Veiligheidsfix: `.env.testing` toegevoegd.** Zonder dat bestand valt
  `php artisan <commando> --env=testing` stilzwijgend terug op de gewone `.env`
  en dus op de ONTWIKKELdatabase. Zo is op 17 juli de complete ontwikkeldatabase
  gewist door een `migrate:fresh --env=testing`. Die vergissing kan nu niet meer.

## [1.15.0] — 2026-07-17

- **Noodtoegang (break-glass).** Maximaal **twee** beheerdersaccounts kunnen
  voortaan met gebruikersnaam en wachtwoord inloggen via `/noodtoegang`, voor het
  geval Microsoft Entra ID onbereikbaar is en niemand meer het systeem in kan. Alle
  overige medewerkers loggen uitsluitend via Entra ID in; die accounts hebben geen
  wachtwoord. Het maximum van twee wordt door de database zelf afgedwongen, niet
  alleen door de applicatie.
- **Beheerscherm Noodaccounts** (Beheer → Noodaccounts). Een beheerder wijst een
  noodaccount aan, wijzigt het wachtwoord of trekt de noodtoegang in. Het wachtwoord
  wijzigen vereist het e-mailadres exact over te typen — dezelfde dubbele beveiliging
  als bij het verwijderen van een student. Intrekken wist het wachtwoord; het account
  zelf blijft bestaan.
- **Bootstrap via de server.** `php artisan sis:noodaccount-instellen <e-mailadres>`
  zet het eerste wachtwoord. Dat kan niet anders: zolang er geen werkend inlogpad is,
  is het beheerscherm onbereikbaar. Het commando vraagt het wachtwoord met verborgen
  invoer, zodat het niet in de shell-historie belandt.
- **Volledige logging.** Elke inlogpoging staat in het audit-logboek — geslaagd én
  mislukt, met IP-adres en de geprobeerde gebruikersnaam. Het wachtwoord zelf komt er
  nooit in, ook de versleutelde vorm niet.
- **Verzoeklimiet.** Vijf pogingen per minuut per gebruikersnaam, twintig per
  IP-adres. Bewust géén blokkade van het account zelf: dan zou iemand met een handvol
  foute pogingen uw noodtoegang kunnen dichtzetten juist wanneer u die nodig heeft.
- **Alleen vanaf het interne netwerk.** De noodtoegang valt onder dezelfde
  netwerkbeperking als de rest van het systeem. Moet u van buiten werken, dan gaat u
  eerst de VPN op (keuze opdrachtgever).

## [1.14.0] — 2026-07-16

- **Bibliotheek: taalcontrole op titels (spel-/typefouten).** Nieuw commando
  `bibliotheek:taalcontrole` dat boektitels in het **Turks, Engels en Nederlands**
  controleert op waarschijnlijke typefouten. Het gebruikt geen extern woordenboek,
  maar de **titels zelf**: een woord dat bijna nooit voorkomt en één letter afwijkt
  van een woord dat juist heel vaak voorkomt, is vermoedelijk een typefout — met dat
  vaker voorkomende woord als **suggestie**. De uitkomst is een reviewlijst (op het
  scherm en als CSV).
- **Zekerste correcties automatisch toegepast (Nederlands & Engels).** De veiligste
  correcties — de fout zit binnen in het woord (begin én einde kloppen), het juiste
  woord komt veel vaker voor — zijn **automatisch doorgevoerd**: 14 titels, elk
  **gelogd** (oude → nieuwe titel) en in een controle-CSV. Voorbeelden:
  *gellof → geloof*, *pcychologie → psychologie*, *geschidenis → geschiedenis*,
  *philisophy → philosophy*, *internatinal → international*. **Turks wordt bewust NIET
  automatisch gecorrigeerd** (te veel geldige woorden liggen daar één letter uiteen,
  bv. *kurban*/*kuran*, *hakkari*/*haklari*); die staan in de reviewlijst voor een
  handmatige beslissing.

## [1.13.0] — 2026-07-16

- **Nieuwe module: Stichtingsbestuur.** Voor het bijhouden van het bestuur en het
  toezicht van de stichting, met een **nieuwe rol Stichtingsbestuur**.
  - **Bestuursleden & Raad van Toezicht.** Twee organen: het **Stichtingsbestuur**
    (voorzitter, penningmeester, secretaris, lid — met bevoegdheid) en de **Raad van
    Toezicht** (commissarissen). Per lid: naam, achternaam, geboortedatum, adres,
    telefoon, e-mail, datum in functie, titel. Afgetreden leden blijven bewaard
    (historie).
  - **Vergaderingen.** Per (jaarlijkse) vergadering: datum, soort (Stichtingsbestuur
    of Raad van Toezicht), besproken onderwerpen, besluiten, en de **aanwezigheid**
    per lid — **fysiek**, **online** of **niet bijgewoond**.
  - Bewust een **afgeschermde** module (governance- en persoonsgegevens): alleen de
    rol Stichtingsbestuur en de Beheerder; alle mutaties worden gelogd.

## [1.12.0] — 2026-07-16

- **Nieuwe module: Scriptie Coördinatie.** Het volledige scriptietraject in **elf
  stappen**, elk een eigen tabblad met een formulier en/of checklist:
  1. Toelatingseisen · 2. Scriptievoorstel · 3. Onderwerpbeoordeling ·
  4. Begeleider · 5. Scriptieovereenkomst · 6. Plan van Aanpak ·
  7. Definitieve inlevering · 8. Plagiaatcontrole · 9. Beoordeling ·
  10. Verdediging · 11. Afronding. De stappen worden op volgorde afgevinkt.
- **Scriptie Kandidaten.** Een lijst van studenten die aan de toelatingseisen
  voldoen (minimaal **180 EC** behaald én **Methoden en Technieken I en II**
  afgerond) en nog geen traject hebben. Vanaf hier start de coördinator een traject.
- **Nieuwe rol: Scriptiecoördinator.** Regisseert het traject. De **docent**
  (begeleider), de **Directie** (opleidingsdirecteur) en de **Examencommissie**
  (scriptiecommissie/examinator) werken per stap mee; het **Schoolbestuur** kijkt
  mee (alleen-lezen). Elke stap kan alleen worden afgevinkt door de verantwoordelijke
  rol; academische stappen (onderwerp, beoordeling, verdediging) blijven bij de
  examencommissie.
- **Ondertekende scriptieovereenkomst, begeleidingsgesprekken en documenten.** De
  overeenkomst wordt als **ondertekende PDF** met verificatiecode gegenereerd;
  begeleidingsgesprekken en documenten (plan van aanpak, eindversie, plagiaatrapport,
  presentatie) worden per traject vastgelegd, met versiebeheer en op de private schijf.

## [1.11.0] — 2026-07-15

- **Uitleentermijnen bevestigd.** De standaardtermijnen zijn nu definitief: een
  **student leent 21 dagen**, een **docent 60 dagen**. De baliemedewerker mag de
  retourdatum per uitlening nog steeds zelf aanpassen.
- **Boete van € 10,00 per boek in de te-laat-mail.** Levert een student een boek te
  laat in, dan noemt de waarschuwingsmail voortaan een boete van **€ 10,00 per boek**.
  Let op: het systeem **int of administreert de boete niet** — dat regelt de
  bibliotheek buiten het systeem om. Docenten krijgen geen boete. De Beheerder kan
  de mailtekst aanpassen onder **E-mailsjablonen**; het bedrag komt automatisch in de
  plaats van de nieuwe variabele **{{Boete}}**.

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
