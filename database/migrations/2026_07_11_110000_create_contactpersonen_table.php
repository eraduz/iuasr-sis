<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contactpersonen bij een organisatie (module Relatiebeheer & Stagebeheer). Elke
 * organisatie kan één of meerdere contactpersonen hebben.
 *
 * AVG: dit zijn persoonsgegevens van externen (professionele contactpersonen).
 * Minimale set, geen bijzondere persoonsgegevens; mutaties worden gelogd. Een
 * contactpersoon wordt op inactief gezet, niet verwijderd (historie).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contactpersonen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisatie_id')->constrained('organisaties')->cascadeOnDelete();
            $table->string('voornaam');
            $table->string('achternaam');
            $table->string('functie')->nullable();
            $table->string('email')->nullable();
            $table->string('mobiel', 30)->nullable();
            $table->string('telefoon', 30)->nullable();
            $table->string('afdeling')->nullable();
            // Voorkeurskanaal: e-mail / telefoon / teams.
            $table->string('voorkeur_communicatie', 20)->nullable();
            $table->string('linkedin')->nullable();
            $table->boolean('actief')->default(true);
            $table->timestamps();

            $table->index('organisatie_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contactpersonen');
    }
};
