<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR-documenten per medewerker (contract, diploma, identiteitsbewijs, overig) —
 * module HR. Bestanden op de PRIVATE schijf (buiten de webroot); inzage/afgifte
 * gelogd (AVG). Alleen HR en Beheer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_documenten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medewerker_id')->constrained('medewerkers')->cascadeOnDelete();
            $table->string('categorie', 40);
            $table->string('titel')->nullable();
            $table->string('bestandsnaam');
            $table->string('pad');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('grootte')->nullable();
            $table->foreignId('geupload_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['medewerker_id', 'categorie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_documenten');
    }
};
