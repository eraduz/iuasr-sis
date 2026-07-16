<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bestuursleden van de stichting: zowel de leden van het Stichtingsbestuur
 * (voorzitter/penningmeester/secretaris/lid, met bevoegdheid) als de commissarissen
 * van de Raad van Toezicht. Onderscheid via `orgaan`. `datum_uit_functie` + `actief`
 * bewaren de historie (een afgetreden lid blijft in het register, alleen-lezen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bestuursleden', function (Blueprint $table) {
            $table->id();
            $table->string('orgaan', 30);   // Bestuursorgaan: stichtingsbestuur | raad_van_toezicht
            $table->string('titel', 30);    // Bestuurstitel
            $table->string('voornaam');
            $table->string('achternaam');
            $table->date('geboortedatum')->nullable();
            $table->string('adres')->nullable();
            $table->string('telefoon', 40)->nullable();
            $table->string('email')->nullable();
            $table->date('datum_in_functie')->nullable();
            $table->date('datum_uit_functie')->nullable();
            $table->string('bevoegdheid')->nullable(); // alleen voor het stichtingsbestuur
            $table->boolean('actief')->default(true);
            $table->timestamps();

            $table->index(['orgaan', 'actief']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bestuursleden');
    }
};
