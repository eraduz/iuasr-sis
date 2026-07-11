<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Koppeltabel Organisatie <-> Opleiding. Een organisatie kan voor meerdere
 * opleidingen relevant zijn (bijv. een instelling die zowel PABO- als
 * MGV-stagiairs neemt). De opleidinggebonden zichtbaarheid (relatiebeheerder,
 * stagecoördinator, directie) leunt op deze koppeling: men ziet uitsluitend de
 * organisaties van de eigen opleiding(en). Echte foreign keys, cascade delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisatie_opleidingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisatie_id')->constrained('organisaties')->cascadeOnDelete();
            $table->foreignId('opleiding_id')->constrained('opleidingen')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['organisatie_id', 'opleiding_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisatie_opleidingen');
    }
};
