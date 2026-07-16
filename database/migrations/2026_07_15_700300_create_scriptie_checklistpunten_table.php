<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De ja/nee-checklistpunten van de stappen die er een hebben (onderwerpbeoordeling,
 * inleverchecklist, beoordelingsonderdelen, afronding). De tekst wordt PER traject
 * bewaard (uit Scriptiestap::checklistpunten()), zodat een latere wijziging van de
 * sjabloonlijst bestaande trajecten niet verandert. `waarde` null = nog niet
 * beantwoord, true = ja/akkoord, false = nee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scriptie_checklistpunten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scriptie_id')->constrained('scripties')->cascadeOnDelete();
            $table->string('stap', 40);           // Scriptiestap-waarde
            $table->string('sleutel', 60);
            $table->string('label');
            $table->unsignedTinyInteger('volgorde')->default(0);
            $table->boolean('waarde')->nullable();
            $table->text('toelichting')->nullable();
            $table->foreignId('beoordelaar_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('beoordeeld_op')->nullable();
            $table->timestamps();

            $table->unique(['scriptie_id', 'stap', 'sleutel']);
            $table->index(['scriptie_id', 'stap']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scriptie_checklistpunten');
    }
};
