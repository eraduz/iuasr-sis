<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interne notities per medewerker (module HR / Personeelszaken). Elke notitie
 * heeft een datum (created_at) en de auteur. Bedoeld voor het vastleggen van
 * contactmomenten — e-mails, telefoongesprekken, gespreksverslagen — als
 * doorlopend logboek per medewerker. Werkinformatie, geen BSN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medewerker_notities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medewerker_id')->constrained('medewerkers')->cascadeOnDelete();
            $table->foreignId('gebruiker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('tekst');
            $table->timestamps();

            $table->index(['medewerker_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medewerker_notities');
    }
};
