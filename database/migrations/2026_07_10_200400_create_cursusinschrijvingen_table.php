<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inschrijving van een cursist op een cursus. `totaalbedrag` is een momentopname
 * van het cursusgeld bij inschrijving, zodat een latere tariefwijziging bestaande
 * inschrijvingen niet verandert. De betalingen volgen in een latere fase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursusinschrijvingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cursist_id')->constrained('cursisten')->cascadeOnDelete();
            $table->foreignId('cursus_id')->constrained('cursussen')->restrictOnDelete();
            $table->date('inschrijfdatum');
            $table->string('status', 20)->default('actief')->comment('aangemeld | actief | afgerond | geannuleerd');
            $table->decimal('totaalbedrag', 10, 2)->default(0);
            $table->text('opmerking')->nullable();
            $table->foreignId('ingeschreven_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cursus_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cursusinschrijvingen');
    }
};
