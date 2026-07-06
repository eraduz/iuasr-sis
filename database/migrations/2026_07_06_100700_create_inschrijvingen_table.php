<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inschrijving — lifecycle per periode/leerjaar. Eén student kan meerdere
 * inschrijvingen hebben; bij herinschrijving blijft dezelfde student_id behouden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inschrijvingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('studenten')->cascadeOnDelete();
            $table->foreignId('opleiding_id')->constrained('opleidingen')->restrictOnDelete();
            $table->foreignId('klas_id')->nullable()->constrained('klassen')->nullOnDelete();
            $table->foreignId('periode_id')->constrained('perioden')->restrictOnDelete();

            $table->unsignedTinyInteger('leerjaar')->nullable();
            $table->string('status')->default('actief')
                ->comment('aangemeld | actief | uitgeschreven | afgestudeerd');

            $table->date('inschrijfdatum')->nullable()->comment('start collegegeldplicht');
            $table->date('invoerdatum')->nullable();
            $table->date('uitschrijfdatum')->nullable();
            $table->date('afstudeerdatum')->nullable();

            $table->string('betaalwijze')->nullable()->comment('termijnen | contant (informatief)');
            $table->text('opmerkingen')->nullable();
            $table->timestamps();

            // Eén actieve inschrijving per student per periode.
            $table->unique(['student_id', 'periode_id', 'opleiding_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inschrijvingen');
    }
};
