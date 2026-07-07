<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Betalingen die de Financiële Administratie per student/inschrijving
 * registreert. Het systeem leidt hieruit de betalingsachterstand af
 * (verschuldigd collegegeld − som van de betalingen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('betalingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inschrijving_id')->constrained('inschrijvingen')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('studenten')->cascadeOnDelete();
            $table->decimal('bedrag', 10, 2);
            $table->date('datum');
            $table->string('betaalwijze')->nullable()->comment('overboeking | contant | termijn | ...');
            $table->text('opmerking')->nullable();
            $table->foreignId('geregistreerd_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'datum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('betalingen');
    }
};
