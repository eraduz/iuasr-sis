<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cijfers mailen (einde blok). Twee tabellen:
 *  - `cijferlijstsjablonen`: het bewerkbare standaard-e-mailsjabloon (onderwerp +
 *    tekst met variabelen), beheerd door de examencommissie. Eén actieve rij.
 *  - `cijferlijstverzendingen`: registreert per student per periode dat de
 *    cijferlijst is gemaild (of in de wachtrij staat / is mislukt). Uniek op
 *    (student, periode) — dat voorkomt dubbel versturen en toont de status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cijferlijstsjablonen', function (Blueprint $table) {
            $table->id();
            $table->string('onderwerp');
            $table->text('inhoud');
            $table->timestamps();
        });

        Schema::create('cijferlijstverzendingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('studenten')->cascadeOnDelete();
            $table->foreignId('periode_id')->constrained('perioden')->cascadeOnDelete();
            $table->foreignId('opleiding_id')->nullable()->constrained('opleidingen')->nullOnDelete();
            // in_wachtrij | verzonden | mislukt
            $table->string('status', 20)->default('in_wachtrij');
            $table->string('ontvanger')->nullable();
            $table->text('foutmelding')->nullable();
            $table->timestamp('verzonden_op')->nullable();
            $table->foreignId('verzonden_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'periode_id']);
            $table->index(['periode_id', 'opleiding_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cijferlijstverzendingen');
        Schema::dropIfExists('cijferlijstsjablonen');
    }
};
