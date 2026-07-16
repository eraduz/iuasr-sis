<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Het scriptietraject van een student (module Scriptie Coördinatie). Eén traject
 * per inschrijving (opleiding × studiejaar). Dit hoofdrecord bevat de identiteit
 * plus alle 1:1-velden van de elf stappen (voorstel, begeleiding, overeenkomst,
 * plan van aanpak, plagiaat, beoordeling, verdediging, afronding). De STAND per
 * stap (status, gereed, wie/wanneer) staat genormaliseerd in scriptie_stapstanden;
 * herhalende gegevens (checklistpunten, begeleidingsgesprekken, documenten) in hun
 * eigen tabellen. De ondertekende overeenkomst hergebruikt ondertekende_documenten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scripties', function (Blueprint $table) {
            $table->id();

            // Identiteit & kern (over de stappen heen).
            $table->string('scriptienummer', 20)->unique();
            $table->foreignId('student_id')->constrained('studenten')->cascadeOnDelete();
            $table->foreignId('inschrijving_id')->unique()->constrained('inschrijvingen')->cascadeOnDelete();
            $table->foreignId('opleiding_id')->constrained('opleidingen')->cascadeOnDelete();
            $table->foreignId('begeleider_id')->nullable()->constrained('docenten')->nullOnDelete();
            $table->foreignId('coordinator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titel_voorlopig')->nullable();
            $table->string('titel_definitief')->nullable();
            $table->string('taal', 40)->nullable();
            $table->string('status', 20)->default('lopend'); // lopend | afgerond | afgebroken
            $table->foreignId('gestart_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('gestart_op')->nullable();
            $table->timestamp('afgerond_op')->nullable();

            // Stap 1 — Toelating (momentopname van de controle bij de start).
            $table->decimal('toelating_ec', 5, 1)->nullable();
            $table->boolean('toelating_mt1_behaald')->nullable();
            $table->boolean('toelating_mt2_behaald')->nullable();

            // Stap 2 — Scriptievoorstel.
            $table->string('voorstel_onderwerp_keuze')->nullable();     // onderwerp uit de lijst
            $table->text('voorstel_onderwerp_eigen')->nullable();       // eigen voorstel
            $table->text('voorstel_omschrijving')->nullable();
            $table->text('voorstel_aanleiding')->nullable();
            $table->text('voorstel_probleemstelling')->nullable();
            $table->text('voorstel_hoofdvraag')->nullable();
            $table->string('voorstel_doelgroep')->nullable();
            $table->string('voorstel_voorkeur_begeleider')->nullable();
            $table->boolean('voorstel_contact_begeleider')->default(false);

            // Stap 3 — Onderwerpbeoordeling (besluit; de punten staan in de checklist).
            $table->date('onderwerp_beoordeeld_op')->nullable();
            $table->string('onderwerp_beoordelaar')->nullable();
            $table->text('onderwerp_toelichting')->nullable();
            $table->text('onderwerp_vereiste_aanpassingen')->nullable();
            $table->date('onderwerp_herindiening_uiterlijk')->nullable();

            // Stap 4 — Toewijzing scriptiebegeleider (naast de FK begeleider_id).
            $table->string('begeleider_naam')->nullable();
            $table->string('begeleider_email')->nullable();
            $table->string('begeleider_expertise')->nullable();
            $table->date('begeleider_toegewezen_op')->nullable();
            $table->unsignedSmallInteger('begeleiding_aantal_momenten')->nullable();
            $table->string('begeleiding_contactwijze')->nullable();
            $table->string('begeleiding_spreekuren')->nullable();
            $table->date('begeleiding_eerste_gesprek')->nullable();

            // Stap 5 — Scriptieovereenkomst.
            $table->text('overeenkomst_commissieleden')->nullable();
            $table->text('overeenkomst_onderzoeksvraag')->nullable();
            $table->string('overeenkomst_studielast')->nullable();
            $table->date('overeenkomst_deadline_pva')->nullable();
            $table->date('overeenkomst_startdatum')->nullable();
            $table->date('overeenkomst_einddatum')->nullable();
            $table->boolean('goedkeuring_student')->default(false);
            $table->date('goedkeuring_student_op')->nullable();
            $table->boolean('goedkeuring_begeleider')->default(false);
            $table->date('goedkeuring_begeleider_op')->nullable();
            $table->boolean('goedkeuring_coordinator')->default(false);
            $table->date('goedkeuring_coordinator_op')->nullable();
            $table->boolean('goedkeuring_directeur')->default(false);
            $table->date('goedkeuring_directeur_op')->nullable();
            $table->foreignId('overeenkomst_document_id')->nullable()
                ->constrained('ondertekende_documenten')->nullOnDelete();

            // Stap 6 — Plan van Aanpak (onderdelen; het document staat in scriptie_documenten).
            $table->text('pva_aanleiding')->nullable();
            $table->text('pva_probleembeschrijving')->nullable();
            $table->text('pva_toegevoegde_waarde')->nullable();
            $table->text('pva_maatschappelijke_relevantie')->nullable();
            $table->text('pva_wetenschappelijke_relevantie')->nullable();
            $table->text('pva_historische_context')->nullable();
            $table->text('pva_literatuuronderzoek')->nullable();
            $table->text('pva_doelgroep')->nullable();
            $table->text('pva_hoofdvraag')->nullable();
            $table->text('pva_deelvragen')->nullable();
            $table->text('pva_methode_verzameling')->nullable();
            $table->text('pva_methode_analyse')->nullable();
            $table->text('pva_planning')->nullable();
            $table->text('pva_risicos')->nullable();
            $table->text('pva_literatuurlijst')->nullable();

            // Stap 7 — Definitieve inlevering (de checklist staat apart).
            $table->date('definitief_ingeleverd_op')->nullable();

            // Stap 8 — Plagiaatcontrole.
            $table->date('plagiaat_datum')->nullable();
            $table->string('plagiaat_versienummer', 40)->nullable();
            $table->decimal('plagiaat_similariteit', 5, 2)->nullable(); // percentage
            $table->boolean('plagiaat_rapport_beschikbaar')->default(false);
            $table->string('plagiaat_beoordeeld_door')->nullable();
            $table->text('plagiaat_toelichting')->nullable();
            $table->text('plagiaat_vervolgstappen')->nullable();

            // Stap 9 — Beoordeling (de onderdelen staan in de checklist).
            $table->string('beoordelaar_1')->nullable();
            $table->string('beoordelaar_2')->nullable();
            $table->string('beoordelaar_3')->nullable();
            $table->date('beoordeling_datum')->nullable();
            $table->decimal('voorlopig_cijfer', 3, 1)->nullable();
            $table->decimal('definitief_cijfer', 3, 1)->nullable();
            $table->text('beoordeling_motivering')->nullable();
            $table->boolean('kalibratie_afgerond')->default(false);
            $table->string('beoordeling_eindbesluit')->nullable();

            // Stap 10 — Verdediging.
            $table->date('verdediging_datum')->nullable();
            $table->time('verdediging_tijd')->nullable();
            $table->string('verdediging_locatie')->nullable();
            $table->string('verdediging_online_link')->nullable();
            $table->text('verdediging_commissieleden')->nullable();
            $table->unsignedSmallInteger('verdediging_duur_presentatie')->nullable(); // minuten
            $table->unsignedSmallInteger('verdediging_duur_vragen')->nullable();      // minuten
            $table->text('verdediging_feedback')->nullable();
            $table->string('verdediging_eindbesluit')->nullable();

            // Stap 11 — Afronding (de checklist staat apart).
            $table->date('gearchiveerd_op')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('opleiding_id');
            $table->index('begeleider_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scripties');
    }
};
