<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documenten bij een organisatie (module Relatiebeheer & Stagebeheer), met
 * versiebeheer. Bestanden staan op de PRIVATE schijf (buiten de webroot); inzage
 * en afgifte worden gelogd (AVG). Een nieuwe versie verwijst naar de vorige,
 * zodat de geschiedenis bewaard blijft.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relatie_documenten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisatie_id')->constrained('organisaties')->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('stages')->nullOnDelete();
            $table->string('categorie', 40);
            $table->string('titel')->nullable();
            $table->string('bestandsnaam');
            $table->string('pad');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('grootte')->nullable();
            $table->unsignedSmallInteger('versie')->default(1);
            $table->foreignId('vorige_versie_id')->nullable()->constrained('relatie_documenten')->nullOnDelete();
            $table->foreignId('geupload_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organisatie_id', 'categorie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relatie_documenten');
    }
};
