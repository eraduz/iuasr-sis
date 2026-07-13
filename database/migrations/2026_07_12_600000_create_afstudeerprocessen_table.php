<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Afstudeerproces per inschrijving (examencommissie-gedreven). Eén lopend proces
 * per inschrijving: de examencommissie start het voor een student in het laatste
 * leerjaar; de bijbehorende stappen staan in `afstudeerprocesstappen`. Het proces
 * is 'afgerond' zodra de laatste stap (diploma uitgereikt) is afgevinkt — dan is
 * de student afgestudeerd.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('afstudeerprocessen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inschrijving_id')->unique()->constrained('inschrijvingen')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('studenten')->cascadeOnDelete();
            $table->foreignId('gestart_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('gestart_op')->nullable();
            $table->string('status', 20)->default('lopend'); // lopend | afgerond | afgebroken
            $table->timestamp('afgerond_op')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afstudeerprocessen');
    }
};
