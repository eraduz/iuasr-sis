<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opzoektabellen (referentiedata) die in het oude Access-systeem losse tabellen
 * met tekstuele sleutels waren. Hier: echte tabellen met surrogaatsleutels.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculteiten', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('naam');
            $table->timestamps();
        });

        Schema::create('landen', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('naam');
            $table->timestamps();
        });

        Schema::create('nationaliteiten', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->string('naam');
            $table->timestamps();
        });

        Schema::create('docenten', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('docentcode');
            $table->string('aanhef')->nullable();
            $table->string('voornaam')->nullable();
            $table->string('achternaam');
            $table->string('email')->nullable();
            $table->boolean('actief')->default(true);
            $table->timestamps();
        });

        Schema::create('perioden', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('bv. 2026-2027');
            $table->string('naam');
            $table->date('startdatum')->nullable();
            $table->date('einddatum')->nullable();
            $table->boolean('actief')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perioden');
        Schema::dropIfExists('docenten');
        Schema::dropIfExists('nationaliteiten');
        Schema::dropIfExists('landen');
        Schema::dropIfExists('faculteiten');
    }
};
