<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Koppeling student (via inschrijving) ↔ vak. Bij (her)inschrijving worden de
 * vakken van het betreffende studiejaar automatisch toegewezen; de
 * Studentenadministratie kan dit per student aanpassen. De koppeling per
 * inschrijving borgt de volledige studiehistorie per studiejaar en periode
 * (blok) — ook jaren later nog raadpleegbaar.
 *
 * Een vak dat aan studenten is toegewezen kan niet zomaar worden verwijderd
 * (restrictOnDelete), zodat de historie intact blijft.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaktoewijzingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inschrijving_id')->constrained('inschrijvingen')->cascadeOnDelete();
            $table->foreignId('vak_id')->constrained('vakken')->restrictOnDelete();
            $table->boolean('automatisch')->default(true)->comment('automatisch toegewezen of handmatig door SZ');
            $table->timestamps();

            $table->unique(['inschrijving_id', 'vak_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaktoewijzingen');
    }
};
