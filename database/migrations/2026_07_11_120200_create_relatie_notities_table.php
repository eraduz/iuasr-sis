<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vrije notities bij een organisatie (module Relatiebeheer & Stagebeheer), met
 * een optionele categorie en tags. De datum is de aanmaakdatum (created_at); de
 * auteur is de medewerker die de notitie plaatste.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relatie_notities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisatie_id')->constrained('organisaties')->cascadeOnDelete();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('categorie')->nullable();
            $table->string('tags')->nullable();
            $table->text('tekst');
            $table->timestamps();

            $table->index('organisatie_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relatie_notities');
    }
};
