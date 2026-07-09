<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presentieregistratie per college. Eén regel = één student (via de
 * inschrijving) × één vak × één onderwijsweek van het blok. `aanwezig`
 * true = 1 (aanwezig), false = 0 (afwezig). Het ONTBREKEN van een regel
 * betekent 'nog niet geregistreerd' — dat is iets anders dan afwezig.
 *
 * Vrijgestelde studenten krijgen geen registratie (zie Vaktoewijzing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presenties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inschrijving_id')->constrained('inschrijvingen')->cascadeOnDelete();
            $table->foreignId('vak_id')->constrained('vakken')->cascadeOnDelete();
            $table->unsignedTinyInteger('week');
            $table->boolean('aanwezig');
            $table->foreignId('geregistreerd_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['inschrijving_id', 'vak_id', 'week']);
            $table->index(['vak_id', 'week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presenties');
    }
};
