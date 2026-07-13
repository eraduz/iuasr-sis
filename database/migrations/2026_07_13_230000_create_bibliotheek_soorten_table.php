<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Publicatiesoort van een vaste lijst in de CODE naar een OPZOEKTABEL.
 *
 * Aanleiding (opdrachtgever 2026-07-13): de bibliotheek heeft ook cd's en dvd's,
 * en in de toekomst komen er soorten bij. Dat moet de bibliotheekmedewerker zelf
 * kunnen toevoegen, zonder programmeur.
 *
 * Een soort is méér dan een etiket: het systeem moet weten hoe het zich gedraagt.
 * Daarom twee eigenschappen per soort, in plaats van dat gedrag in de code:
 *
 *   - `heeft_exemplaren` : zijn er fysieke exemplaren die uitgeleend worden?
 *                          Een boek, cd en dvd wel; een digitaal document niet.
 *   - `heeft_uitgaven`   : bestaat het uit afleveringen met artikelen?
 *                          Alleen een tijdschrift.
 *
 * Zo kan een nieuwe soort (bijv. "Kaart" of "Scriptie") worden toegevoegd zonder
 * één regel code: de schermen lezen deze twee vlaggen.
 *
 * De kolom `publicaties.soort` (tekst) wordt vervangen door `soort_id` (echte
 * foreign key). De bestaande waarden worden overgezet; er gaat niets verloren.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bibliotheek_soorten', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();      // boek, tijdschrift, digitaal, cd, dvd, ...
            $table->string('naam');
            $table->boolean('heeft_exemplaren')->default(true);
            $table->boolean('heeft_uitgaven')->default(false);
            $table->boolean('actief')->default(true);
            $table->unsignedSmallInteger('volgorde')->default(0);
            $table->timestamps();
        });

        $nu = now();
        DB::table('bibliotheek_soorten')->insert([
            ['code' => 'boek', 'naam' => 'Boek', 'heeft_exemplaren' => true, 'heeft_uitgaven' => false, 'actief' => true, 'volgorde' => 1, 'created_at' => $nu, 'updated_at' => $nu],
            ['code' => 'tijdschrift', 'naam' => 'Tijdschrift', 'heeft_exemplaren' => true, 'heeft_uitgaven' => true, 'actief' => true, 'volgorde' => 2, 'created_at' => $nu, 'updated_at' => $nu],
            ['code' => 'digitaal', 'naam' => 'Digitaal document', 'heeft_exemplaren' => false, 'heeft_uitgaven' => false, 'actief' => true, 'volgorde' => 3, 'created_at' => $nu, 'updated_at' => $nu],
            // Nieuw, op verzoek van de opdrachtgever.
            ['code' => 'cd', 'naam' => 'Cd', 'heeft_exemplaren' => true, 'heeft_uitgaven' => false, 'actief' => true, 'volgorde' => 4, 'created_at' => $nu, 'updated_at' => $nu],
            ['code' => 'dvd', 'naam' => 'Dvd', 'heeft_exemplaren' => true, 'heeft_uitgaven' => false, 'actief' => true, 'volgorde' => 5, 'created_at' => $nu, 'updated_at' => $nu],
        ]);

        Schema::table('bibliotheek_publicaties', function (Blueprint $table) {
            $table->foreignId('soort_id')->nullable()->after('id')
                ->constrained('bibliotheek_soorten')->restrictOnDelete();
        });

        // Bestaande titels overzetten: de tekstwaarde wordt de verwijzing.
        foreach (DB::table('bibliotheek_soorten')->pluck('id', 'code') as $code => $id) {
            DB::table('bibliotheek_publicaties')->where('soort', $code)->update(['soort_id' => $id]);
        }

        Schema::table('bibliotheek_publicaties', function (Blueprint $table) {
            // De oude index stond op (soort, uitgavejaar); vervangen door soort_id.
            $table->dropIndex(['soort', 'uitgavejaar']);
            $table->dropColumn('soort');
            $table->index(['soort_id', 'uitgavejaar']);
        });
    }

    public function down(): void
    {
        Schema::table('bibliotheek_publicaties', function (Blueprint $table) {
            $table->string('soort', 20)->nullable()->after('id');
        });

        foreach (DB::table('bibliotheek_soorten')->pluck('code', 'id') as $id => $code) {
            DB::table('bibliotheek_publicaties')->where('soort_id', $id)->update(['soort' => $code]);
        }

        Schema::table('bibliotheek_publicaties', function (Blueprint $table) {
            $table->dropIndex(['soort_id', 'uitgavejaar']);
            $table->dropConstrainedForeignId('soort_id');
            $table->index(['soort', 'uitgavejaar']);
        });

        Schema::dropIfExists('bibliotheek_soorten');
    }
};
