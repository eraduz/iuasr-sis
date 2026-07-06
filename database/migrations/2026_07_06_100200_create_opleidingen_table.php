<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opleidingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculteit_id')->constrained('faculteiten')->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('naam');
            $table->string('soort')->comment('bachelor | master | premaster | cursus | ...');
            $table->unsignedTinyInteger('nominale_jaren')->nullable();
            $table->unsignedSmallInteger('ec_totaal')->nullable();
            // OPENSTAANDE PARAMETERS — per opleiding vast te leggen, blijven null
            // tot bevestigd. Niet zelf invullen met een aanname.
            $table->decimal('voldoende_grens', 3, 1)->nullable()
                ->comment('TE BEVESTIGEN: cijfergrens voldoende per opleiding');
            $table->unsignedSmallInteger('ec_overgang_drempel')->nullable()
                ->comment('TE BEVESTIGEN: EC-drempel overgang leerjaar per opleiding');
            $table->boolean('actief')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opleidingen');
    }
};
