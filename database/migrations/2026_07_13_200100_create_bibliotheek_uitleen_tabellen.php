<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module Bibliotheek — uitlenen, innemen en de e-mailadministratie (fase B/C).
 *
 * LENER: een uitlening gaat naar een STUDENT of naar een MEDEWERKER (docent),
 * beide via een echte foreign key naar het bestaande dossier — nooit als
 * ingetypte naam. Telefoon en e-mail komen daarmee uit één bron, en het
 * te-laat-signaal op het Studentenzaken-dashboard is betrouwbaar. Precies één
 * van beide is gevuld; dat wordt in de applicatie afgedwongen (Uitlening::lener).
 *
 * TERMIJN: de standaard uitleentermijn per lenerstype staat in config/sis.php
 * (`sis.bibliotheek.uitleentermijn_*`) en is env-overschrijfbaar; de
 * baliemedewerker mag de retourdatum per uitlening aanpassen.
 *
 * BOETE: bewust NIET gebouwd. De boeteregels zijn nog niet vastgesteld door de
 * opdrachtgever; er worden geen bedragen verzonnen. Te laat leidt nu tot een
 * waarschuwingsmail en een signaal op het dashboard. De kolom `boete_bedrag`
 * bestaat wel alvast (nullable) zodat een latere fase geen schemawijziging
 * nodig heeft.
 *
 * RETOUR: de inname legt de retourdatum, de staat van het materiaal en een
 * opmerking vast op DEZELFDE regel; er is geen aparte retourtabel, want een
 * retour hoort altijd bij precies één uitlening (1-op-1). 'Op tijd ingeleverd'
 * is een AFLEIDING (retour_op <= verwachte_retour_op), geen kolom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bibliotheek_uitleningen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exemplaar_id')->constrained('bibliotheek_exemplaren')->cascadeOnDelete();

            // Precies één van beide is gevuld (student OF medewerker).
            $table->foreignId('student_id')->nullable()->constrained('studenten')->nullOnDelete();
            $table->foreignId('medewerker_id')->nullable()->constrained('medewerkers')->nullOnDelete();

            $table->date('uitgeleend_op');
            $table->date('verwachte_retour_op');
            $table->date('retour_op')->nullable();          // leeg = nog niet terug

            $table->string('staat', 30)->nullable();        // Materiaalstaat, bij inname
            $table->text('retour_opmerking')->nullable();
            $table->decimal('boete_bedrag', 8, 2)->nullable(); // nog niet in gebruik; zie docblock

            $table->foreignId('uitgeleend_door_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ingenomen_door_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // De lopende uitleningen en de te-late worden het vaakst bevraagd.
            $table->index(['retour_op', 'verwachte_retour_op']);
            $table->index('student_id');
            $table->index('medewerker_id');
        });

        Schema::create('bibliotheek_emailsjablonen', function (Blueprint $table) {
            $table->id();
            $table->string('soort', 40)->unique();          // BibliotheekMailsoort
            $table->string('onderwerp');
            $table->text('inhoud');
            $table->boolean('actief')->default(true);
            $table->timestamps();
        });

        Schema::create('bibliotheek_emaillogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uitlening_id')->constrained('bibliotheek_uitleningen')->cascadeOnDelete();
            $table->string('soort', 40);                    // BibliotheekMailsoort
            $table->string('ontvanger');
            $table->string('cc')->nullable();
            $table->boolean('gelukt')->default(false);
            $table->text('foutmelding')->nullable();
            $table->dateTime('verzonden_op');

            // Append-only logboek: een verzonden e-mail wordt nooit gewijzigd.
            $table->index(['uitlening_id', 'soort']);
            $table->index('verzonden_op');
        });

        // De vijf sjablonen uit de opdracht, met de ondersteunde variabelen.
        $nu = now();
        $voorwaarden = 'Bibliotheekvoorwaarden: behandel het materiaal met zorg en lever het uiterlijk op de retourdatum in bij de bibliotheek.';

        DB::table('bibliotheek_emailsjablonen')->insert([
            [
                'soort' => 'uitleenbevestiging',
                'onderwerp' => 'Uitleenbevestiging: {{Titel}}',
                'inhoud' => "Geachte {{Naam}},\n\nU heeft de volgende publicatie geleend uit de bibliotheek van IUASR:\n\n  Titel: {{Titel}}\n  Uitleendatum: {{Uitleendatum}}\n  Uiterste retourdatum: {{Retourdatum}}\n\n{$voorwaarden}\n\nMet vriendelijke groet,\nBibliotheek IUASR",
                'actief' => true, 'created_at' => $nu, 'updated_at' => $nu,
            ],
            [
                'soort' => 'herinnering_vooraf',
                'onderwerp' => 'Herinnering: {{Titel}} moet binnenkort terug',
                'inhoud' => "Geachte {{Naam}},\n\nDe uiterste retourdatum van de door u geleende publicatie nadert:\n\n  Titel: {{Titel}}\n  Uiterste retourdatum: {{Retourdatum}}\n\nWilt u de publicatie op tijd inleveren bij de bibliotheek?\n\nMet vriendelijke groet,\nBibliotheek IUASR",
                'actief' => true, 'created_at' => $nu, 'updated_at' => $nu,
            ],
            [
                'soort' => 'te_laat_student',
                'onderwerp' => 'Te laat: {{Titel}} ({{AantalDagenTeLaat}} dagen)',
                'inhoud' => "Geachte {{Naam}},\n\nDe door u geleende publicatie is te laat ingeleverd:\n\n  Titel: {{Titel}}\n  Uiterste retourdatum: {{Retourdatum}}\n  Aantal dagen te laat: {{AantalDagenTeLaat}}\n\nLever de publicatie zo spoedig mogelijk in. Zolang de publicatie niet is ingeleverd, kan de bibliotheek u verdere uitleningen weigeren.\n\nMet vriendelijke groet,\nBibliotheek IUASR",
                'actief' => true, 'created_at' => $nu, 'updated_at' => $nu,
            ],
            [
                'soort' => 'te_laat_docent',
                'onderwerp' => 'Herinnering: {{Titel}} is {{AantalDagenTeLaat}} dagen te laat',
                'inhoud' => "Geachte {{Naam}},\n\nDe door u geleende publicatie is nog niet retour:\n\n  Titel: {{Titel}}\n  Uiterste retourdatum: {{Retourdatum}}\n  Aantal dagen te laat: {{AantalDagenTeLaat}}\n\nWilt u de publicatie inleveren bij de bibliotheek?\n\nMet vriendelijke groet,\nBibliotheek IUASR",
                'actief' => true, 'created_at' => $nu, 'updated_at' => $nu,
            ],
            [
                'soort' => 'retourbevestiging',
                'onderwerp' => 'Retourbevestiging: {{Titel}}',
                'inhoud' => "Geachte {{Naam}},\n\nWij hebben de volgende publicatie retour ontvangen:\n\n  Titel: {{Titel}}\n  Retourdatum: {{Retourdatum}}\n\nDank voor het tijdig inleveren.\n\nMet vriendelijke groet,\nBibliotheek IUASR",
                'actief' => true, 'created_at' => $nu, 'updated_at' => $nu,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('bibliotheek_emaillogs');
        Schema::dropIfExists('bibliotheek_emailsjablonen');
        Schema::dropIfExists('bibliotheek_uitleningen');
    }
};
