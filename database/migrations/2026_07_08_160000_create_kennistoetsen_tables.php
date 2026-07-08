<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Landelijke kennistoetsen per opleiding (bv. PABO: RWT reken-/wiskundetoets en
 * de Landelijke Kennisbasistoetsen taal en rekenen). Studenten moeten deze
 * binnen een termijn (config sis.kennistoetsen.termijn_jaren) halen; dit wordt
 * bewaakt zoals het NT2-examen. Per (student × kennistoets) wordt de behaald-op
 * datum bijgehouden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kennistoetsen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opleiding_id')->constrained('opleidingen')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('naam', 120);
            $table->unsignedSmallInteger('volgorde')->default(0);
            $table->boolean('actief')->default(true);
            $table->timestamps();

            $table->unique(['opleiding_id', 'code']);
        });

        Schema::create('kennistoets_resultaten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('studenten')->cascadeOnDelete();
            $table->foreignId('kennistoets_id')->constrained('kennistoetsen')->cascadeOnDelete();
            $table->date('behaald_op')->nullable();
            $table->foreignId('geregistreerd_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'kennistoets_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kennistoets_resultaten');
        Schema::dropIfExists('kennistoetsen');
    }
};
