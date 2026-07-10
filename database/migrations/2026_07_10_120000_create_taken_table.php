<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eenvoudige takenlijst voor Studentenzaken, naar het model van Outlook Taken /
 * Microsoft To Do (titel, begindatum, vervaldatum, status, prioriteit).
 *
 * Gedeelde afdelingslijst: elke taak mag aan een medewerker zijn toegewezen
 * (`toegewezen_aan_id`), maar dat is niet verplicht — een niet-toegewezen taak
 * kan door iedereen bij Studentenzaken worden opgepakt.
 *
 * Een taak mag optioneel aan een studentdossier hangen (`student_id`). Wordt de
 * student verwijderd, dan blijft de taak bestaan zonder koppeling.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taken', function (Blueprint $table) {
            $table->id();
            $table->string('titel', 200);
            $table->text('omschrijving')->nullable();

            $table->foreignId('student_id')->nullable()->constrained('studenten')->nullOnDelete();
            $table->foreignId('toegewezen_aan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('aangemaakt_door_id')->nullable()->constrained('users')->nullOnDelete();

            $table->date('startdatum')->nullable();
            // Leeg = geen deadline; die taken vallen buiten de dashboardsignalering.
            $table->date('vervaldatum')->nullable();

            $table->string('status', 20)->default('open')->comment('open | bezig | afgerond');
            $table->string('prioriteit', 20)->default('normaal')->comment('laag | normaal | hoog');
            $table->timestamp('afgerond_op')->nullable();

            $table->timestamps();

            $table->index(['status', 'vervaldatum']);
            $table->index('toegewezen_aan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taken');
    }
};
