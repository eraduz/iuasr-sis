<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Competentiebeoordelingen onder een HR-gesprek (module HR). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competentiescores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gesprek_id')->constrained('gesprekken')->cascadeOnDelete();
            $table->string('competentie');
            $table->string('score', 20); // onvoldoende | voldoende | goed | uitstekend
            $table->string('toelichting')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competentiescores');
    }
};
