<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interne notities per student (Studentenzaken). Elke notitie heeft een datum
 * (created_at) en de auteur. Administratieve informatie — geen cijfers of BSN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_notities', function (Blueprint $table) {
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
        Schema::dropIfExists('student_notities');
    }
};
