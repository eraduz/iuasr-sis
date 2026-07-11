<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Doelen / KPI's onder een HR-gesprek (module HR). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gespreksdoelen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gesprek_id')->constrained('gesprekken')->cascadeOnDelete();
            $table->string('omschrijving');
            $table->string('status', 20)->default('open'); // open | behaald | niet_behaald
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gespreksdoelen');
    }
};
