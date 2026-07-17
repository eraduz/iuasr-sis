<?php

use App\Enums\Quotesoort;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quotes in de zijbalk: de 99 Schone Namen van Allah (Asma ul-Husna) en eigen
 * spreuken, die om de vijf minuten wisselen. Bedoeld als bemoediging voor de
 * medewerkers, niet als functionaliteit — er hangt geen logica aan.
 *
 * Eén tabel voor beide soorten: een Schone Naam en een eigen spreuk verschillen
 * alleen in herkomst, niet in vorm (beide hebben tekst, eventueel Arabisch,
 * eventueel een afbeelding). `soort` houdt ze uit elkaar zodat de Beheerder de
 * 99 Namen kan filteren zonder ze door zijn eigen spreuken te hoeven zoeken.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id(); // surrogaatsleutel
            $table->enum('soort', Quotesoort::waarden())->index();
            // Bij een Schone Naam: de transliteratie ("Ar-Rahman"). Bij een eigen
            // spreuk optioneel een kop.
            $table->string('titel')->nullable();
            $table->string('arabisch')->nullable()->comment('Arabische tekst; wordt RTL weergegeven');
            $table->text('betekenis')->comment('Nederlandse betekenis of de spreuk zelf');
            $table->string('bron')->nullable()->comment('Bijv. een soera-verwijzing of auteur');
            // Op de PRIVATE schijf; uitgeserveerd via QuoteController::afbeelding,
            // zodat er geen storage:link nodig is en er niets in de webroot belandt.
            $table->string('afbeelding_pad')->nullable();
            $table->unsignedSmallInteger('volgorde')->default(0)->index();
            $table->boolean('actief')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
