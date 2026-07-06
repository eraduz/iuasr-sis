<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resultaat — één rij per behaald deelresultaat (genormaliseerd). Legt poging,
 * vrijstelling, cijfer, toetsdatum en invoerder vast. Inzage/mutatie gelogd.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resultaten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inschrijving_id')->constrained('inschrijvingen')->cascadeOnDelete();
            // student_id gedenormaliseerd als snelkoppeling; FK blijft afgedwongen.
            $table->foreignId('student_id')->constrained('studenten')->cascadeOnDelete();
            $table->foreignId('toetsonderdeel_id')->constrained('toetsonderdelen')->restrictOnDelete();

            $table->string('poging')->default('tentamen')->comment('tentamen | herkansing | extra_kans');
            $table->unsignedTinyInteger('poging_nr')->default(1);
            $table->boolean('vrijstelling')->default(false);

            $table->decimal('cijfer', 3, 1)->nullable()->comment('null bij vrijstelling / nog niet beoordeeld');
            $table->boolean('voldoende')->nullable();
            $table->date('toetsdatum')->nullable();

            // WIE voerde in — onderdeel van het auditspoor (docent/examencommissie).
            $table->foreignId('ingevoerd_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('definitief')->default(false)->comment('vastgesteld door examencommissie');
            $table->text('opmerking')->nullable();
            $table->timestamps();

            $table->index(['toetsonderdeel_id', 'poging']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resultaten');
    }
};
