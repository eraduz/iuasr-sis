<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR-gesprekken (module HR): beoordelings-, functionerings- en exitgesprekken.
 * Doelen (KPI's) en competentiescores hangen als detailregels onder een gesprek.
 * Historie: gesprekken worden bewaard, niet gewist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gesprekken', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medewerker_id')->constrained('medewerkers')->cascadeOnDelete();
            $table->string('type', 20);
            $table->date('datum');
            $table->foreignId('gespreksvoerder_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('gepland');
            $table->text('samenvatting')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->index(['medewerker_id', 'datum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gesprekken');
    }
};
