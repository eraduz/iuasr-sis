<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notities van de EXAMENCOMMISSIE per student — hun eigen werkaantekeningen
 * (bevindingen, overwegingen), los van de interne notities van Studentenzaken.
 * Uitsluitend zichtbaar/beheerbaar voor de examencommissie. Elke notitie heeft een
 * datum (created_at) en de auteur. Administratieve informatie — geen BSN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examencommissie_notities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('studenten')->cascadeOnDelete();
            $table->foreignId('gebruiker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('tekst');
            $table->timestamps();

            $table->index(['student_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examencommissie_notities');
    }
};
