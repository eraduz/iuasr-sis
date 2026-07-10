<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modules van het platform. Na de login kiest de gebruiker een module; elke
 * module is een afzonderlijke administratie (Studentenzaken, Cursussen, en
 * later Stage, Scriptie, HR).
 *
 * `actief` = de module is gebouwd en bruikbaar. Nog niet gebouwde modules staan
 * op false en worden als 'binnenkort' getoond. Nieuwe modules toevoegen is later
 * één regel in deze tabel plus de bijbehorende schermen — geen ingreep in de kern.
 *
 * De vijf modules worden hier meteen ingevoegd, zodat zowel een verse als een
 * bestaande database ze heeft (deze rijen zijn onafhankelijk van andere data).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('sleutel', 40)->unique();
            $table->string('naam');
            $table->string('omschrijving')->nullable();
            $table->string('icoon', 40)->nullable();
            $table->boolean('actief')->default(false);
            $table->unsignedTinyInteger('volgorde')->default(0);
            $table->timestamps();
        });

        $nu = now();
        DB::table('modules')->insert([
            ['sleutel' => 'studentenzaken', 'naam' => 'Studentenzaken', 'omschrijving' => 'Identiteit, inschrijving, cijfers, collegegeld en documenten.', 'icoon' => 'students', 'actief' => true, 'volgorde' => 1, 'created_at' => $nu, 'updated_at' => $nu],
            ['sleutel' => 'cursussen', 'naam' => 'Cursussen Administratie', 'omschrijving' => 'Cursusbeheer, cursisten, inschrijvingen en cursusgelden.', 'icoon' => 'book', 'actief' => false, 'volgorde' => 2, 'created_at' => $nu, 'updated_at' => $nu],
            ['sleutel' => 'stage', 'naam' => 'Stage Administratie', 'omschrijving' => 'Stageplaatsen en -begeleiding.', 'icoon' => 'cert', 'actief' => false, 'volgorde' => 3, 'created_at' => $nu, 'updated_at' => $nu],
            ['sleutel' => 'scriptie', 'naam' => 'Scriptie Administratie', 'omschrijving' => 'Scriptiebegeleiding en -beoordeling.', 'icoon' => 'report', 'actief' => false, 'volgorde' => 4, 'created_at' => $nu, 'updated_at' => $nu],
            ['sleutel' => 'hr', 'naam' => 'HR / Personeelszaken', 'omschrijving' => 'Personeelsadministratie.', 'icoon' => 'users', 'actief' => false, 'volgorde' => 5, 'created_at' => $nu, 'updated_at' => $nu],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
