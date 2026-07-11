<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contactmomenten bij een organisatie (module Relatiebeheer & Stagebeheer): elke
 * interactie met een relatie (telefoon, e-mail, bezoek, stagebezoek, overleg,
 * enz.). Historisch: contactmomenten worden niet verwijderd.
 *
 * `vervolgdatum` legt een afgesproken opvolging vast; het omzetten naar een taak
 * volgt in een latere fase (relatietaken).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contactmomenten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisatie_id')->constrained('organisaties')->cascadeOnDelete();
            $table->foreignId('contactpersoon_id')->nullable()->constrained('contactpersonen')->nullOnDelete();
            $table->foreignId('contactmoment_type_id')->nullable()->constrained('contactmoment_types')->nullOnDelete();
            // De medewerker die het contact had.
            $table->foreignId('medewerker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('datum');
            $table->time('tijd')->nullable();
            $table->string('onderwerp');
            $table->text('samenvatting')->nullable();
            $table->date('vervolgdatum')->nullable();
            $table->timestamps();

            $table->index(['organisatie_id', 'datum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contactmomenten');
    }
};
