<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module Bibliotheek — de catalogus (fase A).
 *
 * DATAMODEL (naar het model van Koha en andere bibliotheeksystemen):
 * de TITEL (het bibliografische record) is gescheiden van het EXEMPLAAR (het
 * fysieke boek). Zo kunnen drie exemplaren van dezelfde titel los worden
 * uitgeleend, terwijl auteurs, talen en vakgebied maar één keer worden
 * vastgelegd. De status (beschikbaar/uitgeleend/...) hoort daarom bij het
 * exemplaar, niet bij de titel.
 *
 * Een BOEKREEKS (Tafsir Ibn Kathir) is een eigen record; de delen zijn gewone
 * publicaties met een verwijzing naar de reeks en een deelnummer. Zo blijft
 * elk deel apart vindbaar en uitleenbaar, maar hangen ze zichtbaar samen.
 *
 * Een TIJDSCHRIFT is een publicatie met uitgaven (afleveringen); onder elke
 * uitgave hangen de artikelen, elk met eigen auteurs, pagina's en trefwoorden.
 *
 * MEERTALIGHEID: de bibliotheek bevat Arabisch, Turks, Engels en Nederlands. De
 * verbinding staat op utf8mb4 met de Unicode-collatie utf8mb4_unicode_ci (zie
 * config/database.php), waardoor deze tabellen die overnemen: opslaan, zoeken en
 * accent-ongevoelig sorteren van Arabisch schrift en Turkse diakrieten verloopt
 * daarmee correct. Een publicatie kan MEERDERE talen hebben (n-op-n),
 * bijvoorbeeld een Arabisch werk met een Nederlandse vertaling.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Referentietabellen -------------------------------------------------

        Schema::create('bibliotheek_talen', function (Blueprint $table) {
            $table->id();
            $table->string('code', 5)->unique();          // ar, tr, en, nl
            $table->string('naam');
            $table->boolean('actief')->default(true);
            $table->timestamps();
        });

        Schema::create('bibliotheek_vakgebieden', function (Blueprint $table) {
            $table->id();
            $table->string('naam');                        // Tafsir, Hadith, Fiqh, ...
            $table->string('omschrijving')->nullable();
            $table->boolean('actief')->default(true);
            $table->unsignedSmallInteger('volgorde')->default(0);
            $table->timestamps();
            $table->index('naam');
        });

        Schema::create('bibliotheek_kasten', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();          // boekenkast / reknummer
            $table->string('omschrijving')->nullable();
            $table->boolean('actief')->default(true);
            $table->timestamps();
        });

        Schema::create('bibliotheek_auteurs', function (Blueprint $table) {
            $table->id();
            $table->string('naam');                        // volledige naam, ook in Arabisch schrift
            $table->string('opmerking')->nullable();
            $table->timestamps();
            $table->index('naam');
        });

        Schema::create('bibliotheek_reeksen', function (Blueprint $table) {
            $table->id();
            $table->string('titel');                       // bv. Tafsir Ibn Kathir
            $table->text('opmerking')->nullable();
            $table->timestamps();
            $table->index('titel');
        });

        // --- De titel (bibliografisch record) -----------------------------------

        Schema::create('bibliotheek_publicaties', function (Blueprint $table) {
            $table->id();
            $table->string('soort', 20);                   // PublicatieSoort
            $table->string('isbn', 20)->nullable();
            $table->string('titel');
            $table->unsignedSmallInteger('uitgavejaar')->nullable();
            $table->string('druknummer', 30)->nullable();

            $table->foreignId('vakgebied_id')->nullable()->constrained('bibliotheek_vakgebieden')->nullOnDelete();

            // Boekreeks: het deel verwijst naar de reeks en draagt zijn deelnummer.
            $table->foreignId('reeks_id')->nullable()->constrained('bibliotheek_reeksen')->nullOnDelete();
            $table->unsignedSmallInteger('deelnummer')->nullable();

            $table->text('opmerking')->nullable();
            $table->timestamps();

            $table->index('titel');
            $table->index('isbn');
            $table->index(['soort', 'uitgavejaar']);
            $table->index(['reeks_id', 'deelnummer']);
        });

        Schema::create('bibliotheek_publicatie_auteur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publicatie_id')->constrained('bibliotheek_publicaties')->cascadeOnDelete();
            $table->foreignId('auteur_id')->constrained('bibliotheek_auteurs')->cascadeOnDelete();
            $table->unique(['publicatie_id', 'auteur_id']);
        });

        Schema::create('bibliotheek_publicatie_taal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publicatie_id')->constrained('bibliotheek_publicaties')->cascadeOnDelete();
            $table->foreignId('taal_id')->constrained('bibliotheek_talen')->cascadeOnDelete();
            $table->unique(['publicatie_id', 'taal_id']);
        });

        // --- Het fysieke exemplaar ----------------------------------------------

        Schema::create('bibliotheek_exemplaren', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publicatie_id')->constrained('bibliotheek_publicaties')->cascadeOnDelete();
            $table->string('serienummer', 40)->unique();   // intern serienummer
            $table->foreignId('kast_id')->nullable()->constrained('bibliotheek_kasten')->nullOnDelete();
            $table->string('status', 20)->default('beschikbaar'); // ExemplaarStatus
            $table->text('opmerking')->nullable();
            $table->timestamps();

            $table->index(['publicatie_id', 'status']);
        });

        // --- Tijdschrift: uitgaven en artikelen ---------------------------------

        Schema::create('bibliotheek_uitgaven', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publicatie_id')->constrained('bibliotheek_publicaties')->cascadeOnDelete();
            $table->string('uitgavenummer', 40);           // bv. 2025/3
            $table->date('publicatiedatum')->nullable();
            $table->unsignedSmallInteger('jaar')->nullable();
            $table->string('locatie')->nullable();
            $table->text('opmerking')->nullable();
            $table->timestamps();

            $table->unique(['publicatie_id', 'uitgavenummer']);
            $table->index('jaar');
        });

        Schema::create('bibliotheek_artikelen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uitgave_id')->constrained('bibliotheek_uitgaven')->cascadeOnDelete();
            $table->string('titel');
            $table->string('paginas', 30)->nullable();     // bv. 12-27
            $table->string('trefwoorden')->nullable();     // komma-gescheiden
            $table->text('beschrijving')->nullable();
            $table->timestamps();

            $table->index('titel');
        });

        Schema::create('bibliotheek_artikel_auteur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artikel_id')->constrained('bibliotheek_artikelen')->cascadeOnDelete();
            $table->foreignId('auteur_id')->constrained('bibliotheek_auteurs')->cascadeOnDelete();
            $table->unique(['artikel_id', 'auteur_id']);
        });

        // Startdata: de vier talen uit de opdracht en de genoemde vakgebieden.
        $nu = now();
        DB::table('bibliotheek_talen')->insert([
            ['code' => 'ar', 'naam' => 'Arabisch', 'actief' => true, 'created_at' => $nu, 'updated_at' => $nu],
            ['code' => 'tr', 'naam' => 'Turks', 'actief' => true, 'created_at' => $nu, 'updated_at' => $nu],
            ['code' => 'en', 'naam' => 'Engels', 'actief' => true, 'created_at' => $nu, 'updated_at' => $nu],
            ['code' => 'nl', 'naam' => 'Nederlands', 'actief' => true, 'created_at' => $nu, 'updated_at' => $nu],
        ]);

        $vakgebieden = ['Tafsir', 'Hadith', 'Fiqh', 'Aqidah', 'Geschiedenis', 'Arabische taal', 'Overige'];
        DB::table('bibliotheek_vakgebieden')->insert(
            collect($vakgebieden)->values()->map(fn ($naam, $i) => [
                'naam' => $naam,
                'actief' => true,
                'volgorde' => $i + 1,
                'created_at' => $nu,
                'updated_at' => $nu,
            ])->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('bibliotheek_artikel_auteur');
        Schema::dropIfExists('bibliotheek_artikelen');
        Schema::dropIfExists('bibliotheek_uitgaven');
        Schema::dropIfExists('bibliotheek_exemplaren');
        Schema::dropIfExists('bibliotheek_publicatie_taal');
        Schema::dropIfExists('bibliotheek_publicatie_auteur');
        Schema::dropIfExists('bibliotheek_publicaties');
        Schema::dropIfExists('bibliotheek_reeksen');
        Schema::dropIfExists('bibliotheek_auteurs');
        Schema::dropIfExists('bibliotheek_kasten');
        Schema::dropIfExists('bibliotheek_vakgebieden');
        Schema::dropIfExists('bibliotheek_talen');
    }
};
