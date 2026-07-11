<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stageplaatsen: het AANBOD/de capaciteit per organisatie, per opleiding en
 * (optioneel) leerjaar/periode. Los van een concrete student — de plaatsing van
 * een student staat in `stages`. De bezetting wordt afgeleid uit het aantal
 * lopende stages op de plaats.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stageplaatsen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisatie_id')->constrained('organisaties')->cascadeOnDelete();
            $table->foreignId('opleiding_id')->constrained('opleidingen')->cascadeOnDelete();
            $table->foreignId('periode_id')->nullable()->constrained('perioden')->nullOnDelete();
            $table->unsignedTinyInteger('leerjaar')->nullable();
            $table->unsignedSmallInteger('aantal_plaatsen')->default(1);
            $table->unsignedSmallInteger('max_studenten')->nullable();
            $table->text('eisen')->nullable();
            $table->string('specialisaties')->nullable();
            $table->string('werkdagen')->nullable();
            $table->boolean('actief')->default(true);
            $table->timestamps();

            $table->index(['organisatie_id', 'opleiding_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stageplaatsen');
    }
};
