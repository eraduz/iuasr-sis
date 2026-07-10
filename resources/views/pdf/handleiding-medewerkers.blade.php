<!DOCTYPE html>
<html lang="nl">
@php
  $logoPad = public_path('assets/img/iuasr-logo.png');
  $logo = is_file($logoPad) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPad)) : null;
@endphp
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 42px 46px 64px 46px; }
    body { font-family: "DejaVu Sans", sans-serif; color: #1E1446; font-size: 10.5pt; line-height: 1.5; }
    #footer { position: fixed; bottom: -44px; left: 0; right: 0; height: 30px; border-top: 1px solid #ddd; padding-top: 6px; font-size: 8pt; color: #666; }
    #footer .r { text-align: right; }
    #footer .num:after { content: counter(page); }
    .cover { border-bottom: 3px solid #1E1446; padding-bottom: 14px; margin-bottom: 20px; }
    .cover img { height: 96px; }
    .cover h1 { font-size: 24pt; font-weight: bold; margin: 14px 0 2px; }
    .cover .sub { font-size: 11pt; color: #666; margin: 0; }
    h2 { font-size: 14pt; color: #C8102E; margin: 20px 0 6px; border-bottom: 1px solid #eee; padding-bottom: 3px; page-break-after: avoid; }
    h3 { font-size: 11.5pt; margin: 14px 0 4px; page-break-after: avoid; }
    p { margin: 0 0 9px; }
    ul, ol { margin: 0 0 10px; padding-left: 20px; }
    li { margin: 0 0 4px; }
    .tip { background: #F5F3FA; border-left: 3px solid #1E1446; padding: 8px 12px; margin: 10px 0; font-size: 10pt; }
    .let { background: #FBEFEF; border-left: 3px solid #C8102E; padding: 8px 12px; margin: 10px 0; font-size: 10pt; }
    table.rol { width: 100%; border-collapse: collapse; font-size: 9.5pt; margin: 8px 0 12px; }
    table.rol th { background: #1E1446; color: #fff; text-align: left; padding: 6px 8px; font-size: 8.5pt; }
    table.rol td { border-bottom: 1px solid #eee; padding: 6px 8px; vertical-align: top; }
    b { color: #1E1446; }
  </style>
</head>
<body>
  <div id="footer">
    <table style="width:100%;"><tr>
      <td>IUASR SIS — Handleiding voor medewerkers · {{ now()->format('d-m-Y') }}</td>
      <td class="r">Pagina <span class="num"></span></td>
    </tr></table>
  </div>

  <div class="cover">
    @if ($logo)<img src="{{ $logo }}" alt="IUASR">@endif
    <h1>Handleiding voor medewerkers</h1>
    <p class="sub">Intern Studentbeheersysteem (SIS) · Islamic University of Applied Sciences Rotterdam</p>
  </div>

  <h2>1. Waarvoor is dit systeem?</h2>
  <p>Het Studentbeheersysteem (SIS) is de interne administratie voor Studentenzaken en de opleidingen. U beheert er studenten, inschrijvingen, cijfers, collegegeld, documenten en rapporten. Het systeem draait uitsluitend op het interne netwerk en is gescheiden van het publieke aanmeldportaal.</p>
  <div class="let">Alle gegevens in de ontwikkel-/testomgeving zijn <b>synthetisch</b> (verzonnen). Ga zorgvuldig om met echte persoonsgegevens; deze vallen onder de AVG.</div>

  <h2>2. Inloggen &amp; een module kiezen</h2>
  <p>U logt in via uw IUASR-account (Microsoft-account, Single Sign-On). Na het inloggen verschijnt het <b>modulekeuzescherm</b>: het systeem groeit uit tot een platform met meerdere onderdelen. Kies met welke module u wilt werken.</p>
  <ul>
    <li><b>Studentenzaken</b> — het huidige systeem (inschrijving, cijfers, collegegeld, documenten).</li>
    <li><b>Cursussen Administratie</b> — cursusbeheer, cursisten en cursusgelden (boekhouding).</li>
    <li><b>Stage</b>, <b>Scriptie</b> en <b>HR / Personeelszaken</b> — later te ontwikkelen; nu zichtbaar als "Binnenkort".</li>
  </ul>
  <p>Welke modules u kunt openen, hangt af van uw <b>rol</b>. De meeste medewerkers zien alleen Studentenzaken; de Financiële Administratie en de Beheerder zien meerdere modules. Nog niet gebouwde modules staan grijs met de melding <b>Binnenkort</b>. Rechtsboven in elk scherm brengt de knop <b>Modules</b> u terug naar het keuzescherm.</p>

  <h2>3. Rollen: wie mag wat</h2>
  <p>Het systeem kent een strikte rolscheiding. Deze wordt aan de serverkant afgedwongen — u ziet alleen de schermen die bij uw rol horen.</p>
  <table class="rol">
    <tr><th>Rol</th><th>Wat u doet</th></tr>
    <tr><td><b>Studentenzaken</b></td><td>Studenten in-/uitschrijven, gegevens en documenten beheren, collegegeld, verklaringen, vrijstellingen registreren, de 50%-aanwezigheidsregeling vastleggen. <b>Geen</b> cijferinzage.</td></tr>
    <tr><td><b>Docent</b></td><td>Cijfers invoeren voor <b>uw eigen vakken</b> en indienen bij de examencommissie. De <b>aanwezigheid</b> per college registreren (verplicht).</td></tr>
    <tr><td><b>Examencommissie</b></td><td>Cijferlijsten vaststellen, tentamenlijsten en cijferlijsten inzien, studievoortgang en aanwezigheid beoordelen.</td></tr>
    <tr><td><b>Directie</b></td><td>Cijfers, aanwezigheid en rapporten inzien van <b>uitsluitend de eigen opleiding(en)</b>. Een directielid wordt door Beheer aan één of meer opleidingen gekoppeld en ziet alleen die studenten.</td></tr>
    <tr><td><b>Financiële Administratie</b></td><td>Collegegeldbetalingen registreren en achterstanden bewaken.</td></tr>
    <tr><td><b>Schoolbestuur</b></td><td>Kerncijfers, aanwezigheidsstatistiek, het <b>alumni-rapport</b> en alle ondertekende documenten inzien. <b>Geen</b> cijferinzage.</td></tr>
    <tr><td><b>Beheerder</b></td><td>Gebruikers/rollen, referentietabellen, audit-log, back-ups en het volledig verwijderen van foutieve studentrecords.</td></tr>
  </table>

  <h2>4. Uw dashboard</h2>
  <p>Na het inloggen ziet u uw dashboard met kerncijfers en grafieken die bij uw rol passen — bijvoorbeeld aantallen studenten, instroom, slaagpercentages of financiële cijfers. Signaleringen (zoals NT2-deadlines of nog aan te leveren documenten) staan er ook.</p>
  <p>Onderaan het dashboard staat een lijst <b>Studenten met vrijstelling</b>, met per student de vrijgestelde vakken. Deze is zichtbaar voor alle rollen behalve Beheer en de Financiële Administratie.</p>
  <p>Daarnaast toont het dashboard (voor Studentenzaken, Directie en de Financiële Administratie) een lijst <b>Studenten met een dubbele inschrijving</b>: studenten die twee opleidingen tegelijk volgen, met beide opleidingen erbij. Directie ziet hierin alleen studenten van de eigen opleiding(en).</p>
  <p>Op het dashboard staat ook het venster <b>Studenten met 50%-aanwezigheidsregeling</b>, met de opleiding en het studiejaar waarvoor de regeling geldt. Docenten zien daarin alleen de studenten uit hun eigen vakken; Directie alleen die van de eigen opleiding(en); de Financiële Administratie ziet dit venster niet. Voor docenten, directie en schoolbestuur verschijnt daarnaast het venster <b>Aanwezigheidsregistratie nog niet volledig</b>, met de vakken en de weken die nog ontbreken.</p>

  <h2>5. Werken met studenten (Studentenzaken)</h2>
  <h3>Student zoeken</h3>
  <p>Ga naar <b>Alle studenten</b> en gebruik de zoekbalk (studentnummer of naam). Klik op een student om het dossier te openen.</p>
  <h3>Nieuwe student inschrijven</h3>
  <p>Kies <b>Student inschrijven</b>, vul de gegevens in en bevestig. Het studentnummer wordt automatisch toegekend en de vakken van het studiejaar worden toegewezen. Voor grote aantallen bestaat <b>Bulk inschrijven</b> (CSV-export van het aanmeldportaal), met een controlestap vóór definitief importeren. In die controlestap <b>kiest u het studiejaar</b> waarin de studenten worden ingeschreven (bijvoorbeeld het komende jaar); bij een toekomstig studiejaar wordt de inschrijfdatum automatisch de startdatum (1 september).</p>
  <h3>Gegevens wijzigen</h3>
  <p>Open het dossier, tabblad <b>Persoonsgegevens</b>, en kies <b>Wijzig gegevens</b>. Onder <b>Acties</b> vindt u herinschrijven, uitschrijven en schorsen.</p>
  <h3>Herinschrijven (met of zonder studiewissel)</h3>
  <p>Bij <b>Herinschrijven</b> maakt u een nieuwe inschrijving terwijl het studentnummer en de persoonsgegevens gelijk blijven. U kiest de <b>opleiding</b>: dezelfde opleiding (vervolg naar een volgend leerjaar of hetzelfde jaar overdoen) óf een <b>andere opleiding</b> (studiewissel — bijvoorbeeld van een cursus naar een bacheloropleiding). Kiest u een andere opleiding, dan wordt het leerjaar automatisch 1 en verschijnen alleen de klassen van die opleiding. De vakken worden op de gekozen opleiding en het gekozen leerjaar toegewezen; eerder behaalde resultaten blijven op de vorige inschrijving bewaard.</p>
  <h3>Twee opleidingen tegelijk (dubbele inschrijving)</h3>
  <p>Een student mag twee opleidingen tegelijk volgen. Kies op het dossier onder <b>Acties</b> de knop <b>Tweede opleiding</b>, selecteer een <b>andere</b> opleiding en (meestal) het huidige studiejaar. Er ontstaat een tweede actieve inschrijving; beide opleidingen staan bovenaan het dossier met de vermelding <b>dubbele inschrijving</b>. Dezelfde opleiding twee keer in hetzelfde studiejaar is niet mogelijk. Het <b>collegegeld wordt per studiejaar één keer</b> berekend, ook bij twee opleidingen.</p>
  <h3>Interne notities</h3>
  <p>Op het dossier staat een kaart met <b>interne notities</b> voor de onderlinge communicatie. <b>Studentenzaken</b> en <b>Beheer</b> kunnen notities <b>toevoegen en verwijderen</b>; <b>Directie</b> en <b>Schoolbestuur</b> kunnen ze <b>meelezen</b> (alleen-lezen). Directie ziet uiteraard alleen notities van studenten binnen de eigen opleiding(en).</p>
  <h3>Documenten</h3>
  <p>In het dossier uploadt u documenten (identiteitsbewijs, diploma, cijferlijst, pasfoto, overig). Ontbreekt iets nog, zet dan <b>“levert later aan”</b> aan — de student verschijnt dan als herinnering op het dashboard.</p>
  <h3>Landelijke kennistoetsen (PABO)</h3>
  <p>Studenten van de PABO moeten de landelijke kennistoetsen halen (de RWT reken-/wiskundetoets en de LKT-kennisbasistoetsen taal en rekenen), binnen <b>twee jaar</b> na inschrijving. Zodra een student zich voor de PABO inschrijft, verschijnt op het dossier (kaart <b>Landelijke kennistoetsen</b>) automatisch de status per toets met de deadline. Registreer een behaalde toets door de datum in te vullen en op <b>Opslaan</b> te klikken. Op het dashboard van Studentenzaken staan de studenten met openstaande of verstreken toetsen — vergelijkbaar met de NT2-bewaking. De set kennistoetsen per opleiding wordt beheerd door Beheer via <b>Opzoektabellen &rarr; Landelijke kennistoetsen</b>.</p>

  <h3>50%-aanwezigheidsregeling</h3>
  <p>Normaal wordt van een student <b>80%</b> aanwezigheid verwacht. Met de <b>50%-aanwezigheidsregeling</b> hoeft de student minimaal de <b>helft</b> van de lessen, practica en colleges bij te wonen. De regeling wordt <b>toegestaan door de directie</b> en door Studentenzaken vastgelegd.</p>
  <p>Open het dossier, tabblad <b>Inschrijving &amp; klas</b>. Onder <b>Huidige inschrijving</b> staat het vinkje <b>50% Aanwezigheidsregeling</b>. Zet het aan (of uit) en klik op <b>Opslaan</b>. De wijziging wordt gelogd.</p>
  <div class="tip">De regeling hangt aan de <b>inschrijving</b>: zij geldt voor één opleiding in één studiejaar. Bij <b>herinschrijven</b> — of bij een tweede opleiding — moet de regeling dus <b>bewust opnieuw</b> worden toegekend. Zo blijft het een jaarlijks besluit van de directie en loopt zij niet stilzwijgend door.</div>
  <p>De regeling is zichtbaar voor Studentenzaken, Docenten, Examencommissie, Directie, Schoolbestuur en Beheer — op het studentdossier, op het dashboard en (voor de docent) op de aanwezigheidslijst, waar de student het label <b>50%</b> achter de naam krijgt.</p>

  <h3>Taken (takenlijst Studentenzaken)</h3>
  <p>Onder <b>Taken</b> staat de <b>gedeelde takenlijst</b> van de afdeling, opgezet naar het model van Outlook Taken. Alleen Studentenzaken en Beheer hebben er toegang toe.</p>
  <p>Een taak heeft een <b>onderwerp</b> (het enige verplichte veld), een optionele <b>begindatum</b>, een <b>vervaldatum</b> (“moet af op”), een <b>prioriteit</b> (laag, normaal, hoog) en een <b>status</b>: Open, Bezig of Afgerond. Klik op <b>Nieuwe taak</b> om er een toe te voegen.</p>
  <p>De lijst is <b>gedeeld</b>: iedereen ziet alle taken. Een taak kunt u aan uzelf of aan een collega toewijzen, of aan niemand — dan is zij vrij op te pakken. Met het vinkje <b>Alleen mijn taken</b> filtert u de lijst.</p>
  <div class="tip"><b>Te laat</b> is geen status die u zelf zet. Een taak wordt automatisch als te laat gemarkeerd zodra de vervaldatum is verstreken en zij nog niet is afgerond. Een taak <b>zonder</b> vervaldatum staat onderaan de lijst en verschijnt niet in de signalering.</div>
  <p>Vinkt u een taak af met het <b>rondje links</b>, dan wordt zij afgerond en wordt vastgelegd <b>wanneer</b> en door <b>wie</b>. Nogmaals klikken heropent haar; het afrondmoment en de afvinker vervallen dan weer.</p>
  <p>Onderaan de takenlijst staat de sectie <b>Afgerond</b> met de 25 laatst afgeronde taken, inclusief de kolommen <b>Toegewezen aan</b> en <b>Afgevinkt door</b>. Dat hoeft niet dezelfde persoon te zijn: een taak die aan een collega is toegewezen mag u gewoon afvinken, en dan staat úw naam bij <b>Afgevinkt door</b>. Bij taken die al vóór deze functie waren afgerond blijft die kolom leeg.</p>
  <p><b>Koppeling met een student:</b> geef bij een taak een student op, dan verschijnt de taak ook op het dossier van die student (kaart <b>Taken</b>), waar u met één regel een nieuwe taak kunt toevoegen. Wordt een student later verwijderd, dan blijft de taak bestaan zonder koppeling.</p>
  <p><b>Op uw dashboard</b> staat het venster <b>Mijn taken</b>: uw eigen taken en de taken die aan niemand zijn toegewezen, met een vervaldatum binnen zeven dagen of al verstreken, op urgentie gesorteerd. U kunt ze daar direct afvinken.</p>

  <h3>Vakstructuur: het curriculum</h3>
  <p>Onder <b>Vakstructuur</b> legt u per opleiding, per studiejaar en per blok de vakken vast. Het IUASR-curriculum staat er al in: Bachelor Islamitische Theologie, Pre-Master GV en Master GV (PABO volgt later).</p>
  <p>Drie dingen om te weten:</p>
  <ul>
    <li><b>Halve studiepunten</b> zijn toegestaan. Vakken als Standaard Arabisch en Qoranrecitatie tellen 2,5 EC.</li>
    <li>Een vak zonder blok loopt het <b>hele studiejaar</b>. Dat geldt voor de stages, de bachelorscriptie en de masterthesis. In de vakstructuur staan zij onder het kopje <b>Hele studiejaar</b>.</li>
    <li>Vakken uit de <b>keuzeruimte</b> zijn gemarkeerd als <b>keuzevak</b>. Zij worden bij inschrijving <b>niet automatisch</b> toegewezen: de student kiest, en u legt die keuze vast via <b>Vakken aanpassen</b> op het dossier (tabblad Inschrijving &amp; klas). Bachelor jaar 4 bestaat daardoor uit 40 EC verplicht plus 20 EC uit de keuzeruimte.</li>
  </ul>
  <div class="tip">Dezelfde vakcode mag in twee opleidingen voorkomen. Zo wordt <b>B-QR02</b> zowel in de bachelor als in de pre-master aangeboden; het zijn aparte vakken met een eigen cijferlijst, docent en aanwezigheidslijst.</div>

  <h3>Vrijstellingen (workflow examencommissie &rarr; Studentenzaken)</h3>
  <p>Een vrijstelling wordt <b>verleend door de examencommissie</b>. De examencommissie legt het besluit vast en stuurt het intern naar Studentenzaken; het verschijnt dan als taak op uw dashboard onder <b>Vrijstellingsbesluiten van de examencommissie</b>. Klik op <b>Verwerken</b> en de vrijstelling wordt automatisch op het vak van de student vastgelegd — u hoeft niets over te typen. Is het vak nog niet toegewezen, dan krijgt u een melding om het eerst toe te wijzen.</p>
  <p>U kunt een vrijstelling ook <b>handmatig</b> vastleggen onder <b>Inschrijving &amp; klas</b> (bijvoorbeeld bij een papieren besluit), altijd met de <b>referentie van het examencommissie-besluit</b>. Een vrijstelling levert de volledige studiepunten (EC) op zonder cijfer (vermelding “VR”).</p>

  <h2>6. Cijfers en aanwezigheid (Docent)</h2>
  <h3>Cijfers invoeren</h3>
  <p>Ga naar <b>Cijferinvoer</b> en kies een van uw vakken. Voer per toetsonderdeel de 1e poging en eventueel de herkansing in; het systeem berekent het gewogen eindcijfer en de EC. Als u klaar bent, <b>dient u de lijst in</b> bij de examencommissie. Daarna is de lijst vergrendeld.</p>
  <div class="tip">U ziet en wijzigt uitsluitend uw eigen vakken. Bij een vrijstelling vervallen de invoervelden en geldt “VR”.</div>

  <h3>Aanwezigheid registreren (verplicht)</h3>
  <p>Tijdens elk college registreert u de aanwezigheid van uw studenten. <b>Dit is verplicht.</b> Een blok telt <b>acht onderwijsweken</b>; per week legt u per student één waarde vast:</p>
  <table class="rol">
    <tr><th>Waarde</th><th>Betekenis</th></tr>
    <tr><td><b>1</b></td><td>De student was aanwezig.</td></tr>
    <tr><td><b>0</b></td><td>De student was afwezig.</td></tr>
    <tr><td><b>–</b></td><td>Nog niet geregistreerd. Dit telt <b>niet</b> als afwezigheid.</td></tr>
  </table>
  <p>Ga naar <b>Mijn vakken</b> en klik bij een vak op <b>Aanwezigheid</b> (of kies <b>Aanwezigheid</b> in het menu voor een overzicht van al uw vakken). U ziet een tabel met uw studenten en de acht weken. Met het knopje <b>alle 1</b> boven een week zet u die hele week in één klik op aanwezig; daarna past u de uitzonderingen aan. Klik op <b>Aanwezigheid opslaan</b>. Het percentage per student wordt direct berekend.</p>
  <p>Twee dingen ziet u meteen op de lijst:</p>
  <ul>
    <li>Studenten met een <b>vrijstelling</b> voor uw vak volgen de colleges niet. Bij hen vult u <b>niets</b> in; hun rij toont “Vrijgesteld — geen aanwezigheidsplicht”.</li>
    <li>Studenten met de <b>50%-aanwezigheidsregeling</b> hebben het label <b>50%</b> achter hun naam en worden aan de <b>50%-norm</b> getoetst in plaats van 80%. De kolom <b>Norm</b> toont per student welke norm geldt.</li>
  </ul>
  <p>Op <b>Mijn vakken</b> en op uw dashboard ziet u per vak of de registratie <b>volledig</b> is. Een week telt pas als geregistreerd wanneer <b>álle</b> presentieplichtige studenten een waarde hebben. Ontbrekende weken worden expliciet benoemd. Uw dashboard toont daarnaast de gemiddelde aanwezigheid, het aantal studenten onder de norm, en grafieken per vak.</p>
  <div class="tip">De aanwezigheidslijst is te <b>printen</b> (knop <b>Printen</b>), bijvoorbeeld om hem tijdens het college op papier bij te houden. Verwar deze lijst niet met de <b>presentielijst voor tentamens</b> (met handtekeningvak), die u vindt onder <b>Tentamenlijst</b>.</div>

  <h2>7. Cijfers vaststellen &amp; lijsten (Examencommissie/Directie)</h2>
  <p>In <b>Cijferoverzicht</b> ziet u alle vakken met status. Een ingediende lijst kunt u <b>vaststellen</b> of met een opmerking <b>terugsturen</b>. Per vak downloadt u een <b>presentielijst</b>: een tentamenlijst om <b>tijdens het tentamen</b> te gebruiken, met per student een vak voor de <b>handtekening</b> (aanwezigheidsbevestiging). Deze lijst bevat bewust <b>geen cijfers of studiepunten</b> — die privé-informatie mag niet zichtbaar zijn voor medestudenten. Per student maakt u via <b>Cijferlijst</b> een officieel transcript (mét cijfers). Beide zijn te printen (knop <b>Printen</b>) en als ondertekende PDF te downloaden. Directie ziet hierbij alleen de vakken en studenten van de eigen opleiding(en).</p>
  <p><b>Vrijstelling voorstellen:</b> op het dossier van een student (tabblad <b>Inschrijving &amp; klas</b>) legt u een vrijstellingsbesluit vast en stuurt u het met <b>Naar Studentenzaken sturen</b> door. Studentenzaken verwerkt het; u ziet de status (openstaand/verwerkt) terug op het dossier en kunt een openstaand besluit annuleren.</p>
  <p><b>Aanwezigheid:</b> via het menu-item <b>Aanwezigheid</b> opent u het <b>aanwezigheidsoverzicht</b>: per vak de docent, het aantal deelnemers, of de registratie volledig is, de gemiddelde aanwezigheid en hoeveel studenten onder hun norm zitten. Klik op <b>Aanwezigheidslijst</b> voor de lijst per week. U kijkt alleen mee — registreren doet uitsluitend de docent van het vak. Directie ziet alleen de eigen opleiding(en). Zo is zichtbaar of docenten de verplichte registratie bijhouden en hoe de aanwezigheid zich per opleiding ontwikkelt.</p>
  <p><b>Resultaten mailen (einde blok):</b> in <b>Cijferlijst</b> kiest u onderaan een opleiding om alle studenten te zien. Met <b>Definitieve resultaten mailen</b> komt u op een controlescherm dat toont wie een e-mail krijgt en wie wordt overgeslagen (geen vastgestelde resultaten of geen e-mailadres). Na bevestiging ontvangt <b>elke student individueel</b> zijn/haar eigen officiële cijferlijst als PDF-bijlage. Alleen door de examencommissie vastgestelde resultaten worden verstuurd; de verzending wordt gelogd.</p>

  <h2>8. Collegegeld &amp; betalingen</h2>
  <h3>Termijnen: vijf facturen per jaar</h3>
  <p>Het collegegeld wordt elke twee maanden gefactureerd: in <b>september, november, januari, maart en mei</b>. Elke termijn is het jaarbedrag gedeeld door vijf; een afrondingsverschil van enkele centen komt op de laatste termijn, zodat de som exact het jaarbedrag is.</p>
  <p>Op het studentdossier (kaart <b>Collegegeld</b>) staat een tabel met alle termijnen: vervaldatum, bedrag, betaald, openstaand en de status. Zo ziet u in één oogopslag welke termijn wel en niet is voldaan.</p>
  <table class="rol">
    <tr><th>Status</th><th>Betekenis</th></tr>
    <tr><td><b>Betaald</b></td><td>De termijn is volledig voldaan.</td></tr>
    <tr><td><b>Achterstallig</b></td><td>De vervaldatum is verstreken en de termijn is niet (volledig) betaald. Dit bepaalt de betalingsachterstand.</td></tr>
    <tr><td><b>Deels betaald</b></td><td>Er is een deelbetaling gedaan, maar de vervaldatum is nog niet verstreken.</td></tr>
    <tr><td><b>Nog niet vervallen</b></td><td>De vervaldatum ligt in de toekomst. Dit is <b>geen</b> achterstand.</td></tr>
    <tr><td><b>Vervallen</b></td><td>De student is uitgeschreven vóór deze vervaldatum; er wordt niet meer gefactureerd.</td></tr>
  </table>
  <div class="tip">Een student heeft pas een <b>betalingsachterstand</b> wanneer een <b>vervallen</b> termijn niet is voldaan. Dat een student in oktober nog € 3.200 openstaan heeft voor de rest van het jaar is dus geen achterstand — die termijnen moeten immers nog vervallen. Bij een echte achterstand worden herinschrijven en verklaringen geblokkeerd.</div>

  <h3>Eén factuur voor het hele jaar</h3>
  <p>Wil een student het volledige jaarbedrag in één keer betalen, dan legt <b>Studentenzaken</b> dat vast op het dossier onder <b>Collegegeld &rarr; Betaalregeling</b>: kies <b>Eén factuur (volledig)</b> en klik op <b>Opslaan</b>. Er ontstaat dan één termijn die op 1 september vervalt. De keuze staat ook in het inschrijfformulier. De regeling hangt aan de <b>inschrijving</b> en geldt dus per studiejaar: bij herinschrijving stelt u haar opnieuw vast. Elke wijziging wordt gelogd.</p>

  <h3>Betalingen boeken (Financiële Administratie)</h3>
  <p>Op het financiële scherm van een student staat per studiejaar hetzelfde termijnoverzicht, met achter elke openstaande termijn de knop <b>Boek € …</b>. Eén klik boekt die termijn volledig op de datum van vandaag. Voor een deelbetaling of een afwijkend bedrag gebruikt u het formulier <b>Betaling registreren</b>; daar kiest u de termijn, of laat u <b>automatisch</b> staan — dan gaat de betaling naar de <b>oudste openstaande</b> termijn.</p>
  <p>Bij de <b>bulk-import</b> (CSV) is er een extra, optionele kolom <b>termijn</b> (1 t/m 5). Laat u die leeg, dan wordt de betaling automatisch toegerekend. Bestaande bestanden zonder termijnkolom blijven gewoon werken: de kolommen worden op naam herkend.</p>

  <h3>Twee opleidingen: elk een eigen rekening</h3>
  <p>Collegegeld wordt <b>per opleiding</b> geheven. Volgt een student twee opleidingen, dan heeft <b>elke inschrijving</b> een eigen jaartarief, een eigen termijnschema en eigen facturen. De bedragen tellen op; het totaal ziet u bovenaan het financiële scherm en op het studentdossier.</p>
  <p>Op de tweede opleiding kan <b>Studentenzaken</b> een <b>korting</b> vastleggen. Dat gebeurt op het dossier, in de kaart <b>Collegegeld</b>, onder <b>Korting op &lt;opleiding&gt;</b>: vul het percentage in (0 t/m 100) en een <b>reden</b> — die is verplicht zodra u korting geeft, want er wordt minder gefactureerd dan het tarief. Alle wijzigingen worden gelogd. Het systeem beslist nooit zelf welke opleiding “de tweede” is; wat u invult, is wat er gefactureerd wordt.</p>
  <table class="rol">
    <tr><th>Voorbeeld</th><th>Bedrag</th></tr>
    <tr><td>ISLTH, jaartarief</td><td>&euro; 4.000,00</td></tr>
    <tr><td>PABO, jaartarief &euro; 2.530,00 met 50% korting</td><td>&euro; 1.265,00</td></tr>
    <tr><td><b>Totaal verschuldigd dit studiejaar</b></td><td><b>&euro; 5.265,00</b></td></tr>
  </table>
  <div class="tip">Bij 100% korting ontstaan er geen facturen voor die opleiding. Een <b>achterstand bij één van de twee</b> opleidingen blokkeert herinschrijven en verklaringen: de student heeft dan een schuld aan de instelling. De boekhouding boekt elke betaling op de opleiding waar zij bij hoort; geld wordt nooit tussen opleidingen verschoven.</div>

  <h3>Betalingsafspraak: blokkade tijdelijk opheffen</h3>
  <p>Bij een betalingsachterstand kan Studentenzaken <b>geen verklaring afgeven</b> en de student <b>niet herinschrijven</b>. Maakt de student een afspraak om alsnog te betalen, dan legt de <b>Financiële Administratie</b> die afspraak vast op het financiële scherm van de student, in de kaart <b>Betalingsafspraak</b>: vul in <b>vóór welke datum</b> betaald wordt en <b>wat er is afgesproken</b>. Beide zijn verplicht.</p>
  <p>Zolang de afspraak loopt vervallen <b>beide blokkades</b>: verklaringen en herinschrijven zijn weer mogelijk. De <b>schuld verdwijnt niet</b> — het achterstallige bedrag blijft op het dossier staan, met de melding dat er een afspraak loopt en tot wanneer.</p>
  <table class="rol">
    <tr><th>Situatie</th><th>Verklaring / herinschrijven</th></tr>
    <tr><td>Achterstand, geen afspraak</td><td><b>Geblokkeerd</b></td></tr>
    <tr><td>Achterstand, lopende afspraak</td><td>Toegestaan</td></tr>
    <tr><td>Afspraak verlopen, nog niet betaald</td><td><b>Geblokkeerd</b> (automatisch)</td></tr>
    <tr><td>Afspraak ingetrokken, nog niet betaald</td><td><b>Geblokkeerd</b></td></tr>
    <tr><td>Schuld voldaan</td><td>Toegestaan</td></tr>
  </table>
  <div class="tip">Na de einddatum keert de blokkade <b>vanzelf</b> terug als er niet is betaald; u hoeft niets te doen. Legt u een nieuwe afspraak vast terwijl er al één loopt, dan wordt de oude automatisch ingetrokken — er is altijd hoogstens één geldende afspraak. Op het dashboard van de Financiële Administratie staat het venster <b>Lopende betalingsafspraken</b> met de einddatum en het aantal resterende dagen.</div>
  <div class="let">Alleen de <b>Financiële Administratie</b> (en Beheer) kan een afspraak vastleggen of intrekken. Studentenzaken kan dat bewust niet: anders zou de rol die de verklaring wil afgeven ook de betaalblokkade kunnen wegnemen. Vastleggen en intrekken worden gelogd.</div>

  <h3>Een betaling corrigeren of verwijderen</h3>
  <p>Staat er een betaling verkeerd geboekt, dan herstelt de <b>Financiële Administratie</b> dat zelf. Klik in de lijst <b>Geregistreerde betalingen</b> op <b>Wijzigen</b>: u past opleiding, termijn, bedrag, datum, betaalwijze en opmerking aan. Met <b>Verwijderen</b> haalt u een betaling helemaal weg, na een bevestiging.</p>
  <div class="let">Beide handelingen worden <b>gelogd</b> in de audit-log, met de <b>oude én de nieuwe waarden</b> en de naam van degene die het deed. Zo blijft elke mutatie op een geldbedrag herleidbaar. Alleen de Financiële Administratie en Beheer kunnen dit; Studentenzaken niet.</div>

  <h3>Tussentijdse uitschrijving</h3>
  <p>Schrijft een student zich halverwege uit, dan is hij alleen de verstreken maanden verschuldigd (jaarbedrag ÷ 12 × maanden, t/m het einde van de uitschrijfmaand). De termijnen ná de uitschrijfdatum krijgen de status <b>Vervallen</b> en de laatste nog geldende termijn wordt naar beneden bijgesteld. Is er meer betaald dan verschuldigd, dan verschijnt de student op het Financiën-overzicht onder <b>terugbetalingen</b>.</p>

  <h2>9. Verklaringen &amp; ondertekende documenten</h2>
  <p>Bij <b>Verklaring opstellen</b> genereert u bewijzen (bv. bewijs van inschrijving) op officieel briefpapier, digitaal gewaarmerkt met een verificatiecode. In <b>Ondertekende documenten</b> vindt u uw eigen gewaarmerkte documenten terug; u kunt ook zelf een PDF uploaden en laten waarmerken. Echtheid is te controleren op de publieke verificatiepagina.</p>

  <h2>10. Rapporten</h2>
  <p>Onder <b>Rapporten</b> maakt u o.a. de klassenlijst, het alumni-rapport, een Excel-export van actieve studenten (met IBAN, zonder BSN), het EC-rapport, het overgangsadvies en de cijfer-/tentamenlijsten. Elke lijst heeft een zoekbalk.</p>
  <p>Het <b>alumni-rapport</b> (afgestudeerde studenten met hun contactgegevens) is toegankelijk voor <b>Studentenzaken</b>, <b>Directie</b> en het <b>Schoolbestuur</b>. Het bevat geen cijfers en geen BSN. Directie ziet alleen de alumni van de eigen opleiding(en); Studentenzaken en het Schoolbestuur zien ze allemaal. Het Schoolbestuur vindt de lijst in het menu onder <b>Rapporten &rarr; Alumni</b>.</p>

  <h2>11. Cursussen Administratie (aparte module)</h2>
  <p>Naast Studentenzaken is er de module <b>Cursussen Administratie</b> voor de cursussen die buiten Studentenzaken vallen. U opent haar via het <b>modulekeuzescherm</b> (rechtsboven <b>Modules</b>). De toegang is per rol verdeeld: een <b>cursusdirecteur</b> (rol Cursusadministratie) beheert uitsluitend de <b>eigen cursus(sen)</b>; de <b>Financiële Administratie</b> (boekhouding) ziet de cursusgelden van alle cursussen; het <b>Schoolbestuur</b> kijkt mee (dashboard/statistieken en cursisten, alleen-lezen); de <b>Beheerder</b> ziet en doet alles.</p>
  <h3>Cursusdirecteuren (toegang per cursus)</h3>
  <p>Elke cursus heeft een <b>directeur</b>. Die directeur ziet en beheert op het dashboard, in het cursusbeheer en in het cursistenoverzicht <b>alleen zijn eigen cursus(sen)</b> en de cursisten die daarop zijn ingeschreven — niet die van andere cursussen. Op een cursistdossier ziet een directeur alleen de inschrijving(en) op de eigen cursus. Zo blijft de administratie van elke cursus gescheiden (need-to-know).</p>
  <p>Het <b>toewijzen van een directeur</b> aan een cursus, en het <b>aanmaken of verwijderen</b> van een cursus, doet uitsluitend de <b>Beheerder</b> (via het veld <i>Cursusdirecteur</i> op het cursusformulier). Een cursusdirecteur kan zichzelf dus geen extra cursussen geven; hij mag wel de gegevens van de eigen cursus wijzigen. De Financiële Administratie en het Schoolbestuur zijn niet cursusgebonden: zij zien alle cursussen.</p>
  <h3>Cursussen</h3>
  <p>Onder <b>Cursusbeheer</b> staan de cursussen met hun cursusgeld. De huidige cursussen zijn <b>Arabische Taal</b> (€ 265), <b>Hifz Programma</b> (€ 330) en <b>Certificaatprogramma / Ijaaza</b> (€ 430). Nieuwe cursussen en tarieven voegt u hier gewoon toe; een cursus met inschrijvingen kunt u niet verwijderen (historie) maar wel op inactief zetten.</p>
  <h3>Cursisten</h3>
  <p>Cursisten staan los van de studenten en krijgen een eigen <b>cursistnummer</b> (bijv. C260001). U voegt ze <b>handmatig</b> toe of in <b>bulk</b> via een Excel- of CSV-bestand. Bij de bulk-import mag u een kolom <b>cursuscode</b> meegeven; staat daar een geldige code, dan wordt de cursist meteen op die cursus ingeschreven. Zoals altijd ziet u eerst een controlescherm voordat er iets wordt opgeslagen.</p>
  <h3>Inschrijven</h3>
  <p>Op het dossier van een cursist schrijft u hem in op een cursus. Het <b>cursusgeld</b> wordt als momentopname vastgelegd, zodat een latere tariefwijziging bestaande inschrijvingen niet verandert. De status van een inschrijving (aangemeld, actief, afgerond, geannuleerd) past u daar ook aan.</p>
  <h3>Cursusgelden (boekhouding)</h3>
  <p>De <b>Financiële Administratie</b> opent binnen de module de pagina <b>Cursusgelden</b>. Daar staat per inschrijving het cursusgeld, het reeds betaalde bedrag en het openstaande saldo, met een status <b>Voldaan</b>, <b>Deels betaald</b> of <b>Openstaand</b>. U kunt filteren op cursus, op status en zoeken op cursistnummer of naam.</p>
  <p>Via <b>Beheer</b> bij een inschrijving registreert u een betaling: <b>bedrag</b>, <b>datum</b>, <b>betaalmethode</b> (iDEAL/online, bankoverschrijving of contant) en <b>status</b>. Alleen betalingen met status <b>Betaald</b> tellen mee voor het voldane bedrag; een betaling <i>in afwachting</i>, <i>mislukt</i> of <i>terugbetaald</i> telt niet mee. Bestaande betalingen kunt u <b>wijzigen</b> of <b>verwijderen</b>; elke wijziging en verwijdering wordt gelogd. Een cursist mag het cursusgeld ook in delen betalen — de deelbetalingen worden bij elkaar opgeteld.</p>
  <p>De cursusadministratie ziet de betaalstatus op het cursistdossier alleen ter informatie; het registreren en corrigeren van betalingen is voorbehouden aan de boekhouding.</p>
  <div class="tip">De cursusadministratie is een aparte administratie met een lichter regime: geen BSN of DUO-gegevens. Belangrijke acties (cursus/cursist aanmaken, inschrijven, importeren, betaling registreren/wijzigen/verwijderen) worden gelogd.</div>

  <h2>12. Vragen of problemen?</h2>
  <p>Neem bij vragen contact op met de systeembeheerder of Studentenzaken (szaken@iuasr.nl). Werkt iets niet zoals verwacht, meld dan wat u deed en wat u zag.</p>

  <div class="tip">Deze handleiding wordt bijgewerkt zodra er nieuwe functies bij komen. Kijk bij twijfel of u de meest recente versie hebt (zie de datum onderaan).</div>
</body>
</html>
